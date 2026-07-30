<?php

/**
 * DIIDS catalog extras — Socks (configurable clone) + two "bundle" products
 * (created as simple products with a set price, cloned from a configurable's
 * parent so attribute values exist, then flattened to simple).
 *
 * Run: php artisan tinker --execute="require 'scripts/diids-catalog-extras.php';"
 * Idempotent by SKU.
 */

use Webkul\Product\Models\Product;
use Illuminate\Support\Facades\DB;

$NAME = 2; $PRICE = 11; $DESC = 9; $SHORT = 10; $SKU_ATTR = 1; $URL = 3; $WEIGHT = 12;

/* Reuse the clone helper defined in the rebuild script if present, else inline. */
if (! function_exists('cloneConfigurable')) {
    require __DIR__ . '/diids-catalog-rebuild-helpers.php';
}

/* ---- Socks: clone The Core boxer (unisex sizing) -> DIIDS-SOCK-001 --------- */
cloneConfigurable('DIIDS-BOX-001', 'DIIDS-SOCK-001', 'DIIDS Socks',
    9999,
    'Ribbed cotton-blend crew socks with a cushioned sole and the DIIDS cuff.',
    'Everyday crew socks in a breathable cotton blend — ribbed for grip, cushioned underfoot, finished with the signature DIIDS cuff. Sold in a pack, built to outlast the drawer.',
    [2, 3] // both men & women roots
);

/* ---- Bundles as SIMPLE products ------------------------------------------- */
/**
 * Create a simple product from scratch (no variants) with a fixed price.
 */
function createSimple(string $sku, string $name, float $price, string $short, string $desc, array $categoryIds, ?int $imageFromProductId = null) {
    $NAME = 2; $PRICE = 11; $DESC = 9; $SHORT = 10; $SKU_ATTR = 1; $URL = 3; $STATUS = 8;
    $WEIGHT = 12; $NEW = 6; $FEATURED = 7; $VISIBLE = 25; $GUEST = 26; $INSTOCK = null;

    if (Product::where('sku', $sku)->exists()) { echo "  = $sku exists, skip\n"; return Product::where('sku',$sku)->first(); }

    // clone attribute FAMILY structure from an existing simple variant to get all required attrs
    $template = Product::where('type','simple')->whereNotNull('parent_id')->first();

    $p = new Product();
    $p->sku = $sku;
    $p->type = 'simple';
    $p->attribute_family_id = $template->attribute_family_id;
    $p->save();

    $set = function($attrId,$col,$val) use ($p){ $p->attribute_values()->create(['attribute_id'=>$attrId,'channel'=>'default','locale'=>'en',$col=>$val]); };
    $set(1,'text_value',$sku);
    $set(2,'text_value',$name);
    $set(3,'text_value',\Str::slug($name).'-'.$p->id);
    $set(8,'boolean_value',1);        // status
    $set(9,'text_value',$desc);
    $set(10,'text_value',$short);
    $set(11,'float_value',$price);
    $set(6,'boolean_value',1);        // new
    $set(7,'boolean_value',1);        // featured
    $set(25,'boolean_value',1);       // visible_individually
    $set(26,'boolean_value',1);       // guest_checkout

    // inventory: mark in stock
    $p->inventories()->create(['inventory_source_id'=>1,'qty'=>100,'vendor_id'=>0]);

    $p->categories()->sync($categoryIds);

    if ($imageFromProductId) {
        $img = \Webkul\Product\Models\ProductImage::where('product_id',$imageFromProductId)->first();
        if ($img) \Webkul\Product\Models\ProductImage::create(['product_id'=>$p->id,'type'=>$img->type,'path'=>$img->path]);
    }

    DB::table('product_flat')->updateOrInsert(
        ['product_id'=>$p->id, 'channel'=>'default','locale'=>'en'],
        ['sku'=>$sku,'name'=>$name,'price'=>$price,'status'=>1,'visible_individually'=>1,'product_number'=>$sku,'created_at'=>now(),'updated_at'=>now()]
    );
    echo "  ✓ created $sku ($name @ $price)\n";
    return $p;
}

$bra  = Product::where('sku','DIIDS-BRA-001')->first();
$pant = Product::where('sku','DIIDS-PAN-001')->first();
$box  = Product::where('sku','DIIDS-BOX-001')->first();

createSimple('DIIDS-SET-DUO', 'The Duo — Bra + Pant', 55000,
    'The Muse bralette and The Bare pant, together — save on the set.',
    'Our two women\'s essentials as one set: The Muse bralette and The Bare pant, matched and priced to save. Everyday confidence, top to bottom.',
    [3], $bra?->id);

createSimple('DIIDS-SET-CORE', 'The Set — Bra + Boxer', 75000,
    'His and hers: The Muse bralette and The Core boxer as a matched set.',
    'A shared set — The Muse bralette and The Core boxer, in matching DIIDS trims. Two people, one line.',
    [2,3], $box?->id);

echo "Extras complete. Total products: ".Product::count()."\n";
