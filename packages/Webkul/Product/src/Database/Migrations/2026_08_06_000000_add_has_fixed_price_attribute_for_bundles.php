<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The attribute code this migration adds. Bundle-type products read
     * this to decide whether their own `price` attribute is a fixed,
     * customer-facing bundle price (true) or should be ignored in favor of
     * summing the selected option products (false/unset — the stock
     * Bagisto behavior). See Webkul\Product\Type\Bundle::getFinalPrice().
     */
    private string $attributeCode = 'has_fixed_price';

    public function up(): void
    {
        if (DB::table('attributes')->where('code', $this->attributeCode)->exists()) {
            return;
        }

        $now = now();

        $attributeId = DB::table('attributes')->insertGetId([
            'code' => $this->attributeCode,
            'admin_name' => 'Use Fixed Bundle Price',
            'type' => 'boolean',
            'validation' => null,
            'position' => 999,
            'is_required' => 0,
            'is_unique' => 0,
            'value_per_locale' => 0,
            'value_per_channel' => 0,
            'default_value' => 0,
            'is_filterable' => 0,
            'is_configurable' => 0,
            'is_user_defined' => 1,
            'is_visible_on_front' => 0,
            'is_comparable' => 0,
            'enable_wysiwyg' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Attach to every attribute family's "settings" group (the same
        // group that already holds status/featured/guest_checkout) so the
        // toggle shows up for bundles regardless of which family they use.
        $settingsGroups = DB::table('attribute_groups')->where('code', 'settings')->get(['id']);

        foreach ($settingsGroups as $group) {
            $maxPosition = DB::table('attribute_group_mappings')
                ->where('attribute_group_id', $group->id)
                ->max('position');

            DB::table('attribute_group_mappings')->insert([
                'attribute_id' => $attributeId,
                'attribute_group_id' => $group->id,
                'position' => ($maxPosition ?? 0) + 1,
            ]);
        }
    }

    public function down(): void
    {
        $attribute = DB::table('attributes')->where('code', $this->attributeCode)->first();

        if (! $attribute) {
            return;
        }

        DB::table('attribute_group_mappings')->where('attribute_id', $attribute->id)->delete();
        DB::table('product_attribute_values')->where('attribute_id', $attribute->id)->delete();
        DB::table('attributes')->where('id', $attribute->id)->delete();
    }
};
