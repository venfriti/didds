<?php

/**
 * DIIDS catalog update — rename + reprice existing products to the DIIDS line,
 * and record which parents map to which line. Run with:
 *   php artisan tinker --execute="require 'scripts/diids-catalog-update.php';"
 *
 * Idempotent: safe to run multiple times. Backs up originals once.
 */

use Webkul\Product\Models\Product;

$NAME_ATTR  = 2;
$PRICE_ATTR = 11;

// ---- backup (once) -------------------------------------------------------
$backupPath = storage_path('app/product-backup.json');
if (! file_exists($backupPath)) {
    $backup = [];
    foreach (Product::all() as $p) {
        $backup[$p->id] = [
            'sku'   => $p->sku,
            'name'  => optional($p->attribute_values()->where('attribute_id', $NAME_ATTR)->first())->text_value,
            'price' => optional($p->attribute_values()->where('attribute_id', $PRICE_ATTR)->first())->float_value,
        ];
    }
    file_put_contents($backupPath, json_encode($backup, JSON_PRETTY_PRINT));
    echo 'Backed up ' . count($backup) . " products.\n";
} else {
    echo "Backup already exists (skipping).\n";
}

// ---- helper: set a text/float attribute value + mirror to product_flat ---
function setAttr($product, $attrId, $column, $value) {
    $av = $product->attribute_values()->where('attribute_id', $attrId)->first();
    if ($av) { $av->update([$column => $value]); }
    \DB::table('product_flat')->where('product_id', $product->id)->update([
        $attrId === 2 ? 'name' : 'price' => $value,
    ]);
}

/**
 * Map: configurable parent SKU => [new line name, new price NGN].
 * Simple variants inherit the parent's line name (with their color/size) and price.
 */
$plan = [
    // Men's bottoms -> The Core @ 45,000
    'DIIDS-BOX-001' => ['The Core Boxer',        45000],
    'DIIDS-BOX-002' => ['The Core Boxer — Comfort', 45000],
    'DIIDS-TRK-001' => ['The Core Trunk',        45000],
    'DIIDS-BRF-001' => ['The Core Brief',        45000],
    // Women's pants -> The Bare
    'DIIDS-PAN-001' => ['The Bare — Bikini',     30000],
    'DIIDS-PAN-002' => ['The Bare — Thong',      28000],
    'DIIDS-PAN-003' => ['The Bare — Full Cover', 30000],
    'DIIDS-SHW-001' => ['The Bare — Shaping Short', 40000],
];

foreach ($plan as $sku => [$name, $price]) {
    $parent = Product::where('sku', $sku)->where('type', 'configurable')->first();
    if (! $parent) { echo "  ! missing $sku\n"; continue; }

    setAttr($parent, $NAME_ATTR, 'text_value', $name);
    setAttr($parent, $PRICE_ATTR, 'float_value', $price);

    // variants: rename to "<line> - <color> - <size>" and match price
    foreach (Product::where('parent_id', $parent->id)->get() as $variant) {
        $old = optional($variant->attribute_values()->where('attribute_id', $NAME_ATTR)->first())->text_value ?? '';
        // keep the trailing " - Color - Size" suffix if present
        $suffix = '';
        if (preg_match('/( - .+ - .+)$/', $old, $m)) { $suffix = $m[1]; }
        setAttr($variant, $NAME_ATTR, 'text_value', $name . $suffix);
        setAttr($variant, $PRICE_ATTR, 'float_value', $price);
    }
    echo "  ✓ $sku -> $name @ $price\n";
}

echo "Done. Clearing product-flat is not needed (updated in place).\n";
