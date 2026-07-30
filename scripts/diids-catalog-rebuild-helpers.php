<?php
use Webkul\Product\Models\Product;
use Illuminate\Support\Facades\DB;

if (! function_exists('cloneConfigurable')) {
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
}
