<?php

/**
 * DIIDS catalog rebuild — trim to a tight, well-designed line and create the
 * missing products (bra, socks, bundles) by CLONING existing configurable
 * products (so the attribute/variant structure is always valid).
 *
 * Run: php artisan tinker --execute="require 'scripts/diids-catalog-rebuild.php';"
 * Idempotent: uses fixed SKUs; skips creation if the SKU already exists.
 *
 * Backup of original prices/names is at storage/app/product-backup.json.
 */

use Webkul\Product\Models\Product;
use Illuminate\Support\Facades\DB;

$productRepo = app(\Webkul\Product\Repositories\ProductRepository::class);

$NAME = 2; $PRICE = 11; $DESC = 9; $SHORT = 10; $STATUS = 8;

function flat($id, $data) { DB::table('product_flat')->where('product_id', $id)->update($data); }

function setVal(Product $p, int $attrId, string $col, $value) {
    $av = $p->attribute_values()->where('attribute_id', $attrId)->first();
    if ($av) $av->update([$col => $value]);
    else $p->attribute_values()->create(['attribute_id' => $attrId, $col => $value, 'channel' => 'default', 'locale' => 'en']);
}

/* ============================================================== 1. DELETE === */
$deleteSkus = ['DIIDS-BOX-002','DIIDS-TRK-001','DIIDS-BRF-001','DIIDS-PAN-002','DIIDS-PAN-003','DIIDS-SHW-001'];
foreach ($deleteSkus as $sku) {
    $p = Product::where('sku', $sku)->first();
    if ($p) {
        // delete variants first, then parent (repo cascades flat/images/relations)
        foreach (Product::where('parent_id', $p->id)->pluck('id') as $vid) {
            try { $productRepo->delete($vid); } catch (\Throwable $e) {}
        }
        try { $productRepo->delete($p->id); echo "  ✗ deleted $sku\n"; }
        catch (\Throwable $e) { echo "  ! could not delete $sku: {$e->getMessage()}\n"; }
    }
}

/* ========================================================= 2. CLONE HELPER === */
/**
 * Deep-clone a configurable product to a new SKU/name/price. Clones the parent
 * attribute values, super-attribute links, categories, images, and each
 * variant (with its own attribute values + super-attribute option links).
 */
function cloneConfigurable(string $srcSku, string $newSku, string $name, float $price, string $short, string $desc, array $categoryIds) {
    $src = Product::where('sku', $srcSku)->first();
    if (! $src) { echo "  ! clone source $srcSku missing\n"; return null; }
    if (Product::where('sku', $newSku)->exists()) { echo "  = $newSku exists, skip\n"; return Product::where('sku', $newSku)->first(); }

    $NAME = 2; $PRICE = 11; $DESC = 9; $SHORT = 10; $URL = 3; $SKU_ATTR = 1;

    // parent
    $new = $src->replicate(['created_at','updated_at']);
    $new->sku = $newSku;
    $new->save();
    // super attribute links
    foreach ($src->super_attributes as $sa) $new->super_attributes()->attach($sa->id);
    // attribute values (copy, override name/price/sku/url_key/desc)
    foreach ($src->attribute_values as $av) {
        $data = $av->only(['attribute_id','locale','channel','text_value','boolean_value','integer_value','float_value','datetime_value','date_value','json_value']);
        if ($av->attribute_id == $NAME)  $data['text_value'] = $name;
        if ($av->attribute_id == $PRICE) $data['float_value'] = $price;
        if ($av->attribute_id == $SKU_ATTR) $data['text_value'] = $newSku;
        if ($av->attribute_id == $URL)   $data['text_value'] = \Str::slug($name).'-'.$new->id;
        if ($av->attribute_id == $DESC)  $data['text_value'] = $desc;
        if ($av->attribute_id == $SHORT) $data['text_value'] = $short;
        $new->attribute_values()->create($data);
    }
    // categories
    $new->categories()->sync($categoryIds ?: $src->categories->pluck('id')->all());
    // images
    foreach (\Webkul\Product\Models\ProductImage::where('product_id',$src->id)->get() as $img) {
        \Webkul\Product\Models\ProductImage::create(['product_id'=>$new->id,'type'=>$img->type,'path'=>$img->path]);
    }
    // variants
    foreach (Product::where('parent_id',$src->id)->get() as $sv) {
        $nv = $sv->replicate(['created_at','updated_at']);
        $nv->parent_id = $new->id;
        $suffix = '';
        $svName = optional($sv->attribute_values()->where('attribute_id',$NAME)->first())->text_value ?? '';
        if (preg_match('/( - .+)$/',$svName,$m)) $suffix = $m[1];
        $nv->sku = $newSku.strtoupper(str_replace([' ','—'],['-',''],$suffix ?: '-'.$sv->id));
        $nv->save();
        foreach ($sv->attribute_values as $av) {
            $data = $av->only(['attribute_id','locale','channel','text_value','boolean_value','integer_value','float_value','datetime_value','date_value','json_value']);
            if ($av->attribute_id == $NAME)  $data['text_value'] = $name.$suffix;
            if ($av->attribute_id == $PRICE) $data['float_value'] = $price;
            if ($av->attribute_id == 1)      $data['text_value'] = $nv->sku;
            $nv->attribute_values()->create($data);
        }
    }
    // flat rebuild happens on reindex; set parent flat now for immediate display
    DB::table('product_flat')->where('product_id',$new->id)->update(['name'=>$name,'price'=>$price]);
    echo "  ✓ created $newSku ($name @ $price)\n";
    return $new;
}

/* ============================================================ 3. CREATE ====== */
// The Muse — Bra (clone the women's bikini structure; assign to Bras cat=8)
cloneConfigurable('DIIDS-PAN-001', 'DIIDS-BRA-001', 'The Muse',
    30000,
    'A second-skin bralette in premium stretch — soft support, clean lines, all-day ease.',
    'The Muse is our signature bralette: a second-skin fit in premium four-way stretch with bonded edges and a soft elastic band. Support without wire, shape without compromise. Designed to be seen or layered — everyday confidence, next to skin.',
    [3, 8]
);

echo "Catalog rebuild step complete. Simple products (socks) + bundles handled separately.\n";
echo "Total products now: ".Product::count()."\n";
