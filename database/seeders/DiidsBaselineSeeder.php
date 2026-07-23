<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the DIIDS-specific baseline: brand settings, homepage content,
 * footer links, CMS page copy, and the demo admin accounts. Runs after
 * Bagisto's own core seeder, which is what creates the channel, currency,
 * locale, and placeholder CMS page rows this seeder updates in place.
 *
 * This does NOT seed the product catalog - that stays database-managed
 * and grows independently of what ships in git (see README.md).
 */
class DiidsBaselineSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedChannelBranding();
        $this->seedThemeCustomizations();
        $this->seedCmsPages();
        $this->seedAdminAccounts();
    }

    /**
     * Logo, site name/SEO, and currency - the settings that make the site
     * read as "DIIDS" rather than a generic Bagisto install.
     */
    private function seedChannelBranding(): void
    {
        $channel = DB::table('channels')->where('code', 'default')->first();

        if (! $channel) {
            return;
        }

        DB::table('channels')->where('id', $channel->id)->update([
            'logo' => 'channel/1/diids-logo.svg',
            'hostname' => config('app.url'),
        ]);

        DB::table('channel_translations')
            ->where('channel_id', $channel->id)
            ->where('locale', 'en')
            ->update([
                'name' => 'DIIDS',
                'home_seo' => json_encode([
                    'meta_title' => 'DIIDS',
                    'meta_keywords' => 'DIIDS, premium underwear, mens underwear, womens underwear, Nigeria',
                    'meta_description' => 'DIIDS is a premium underwear brand for men and women, built for everyday confidence, comfort, and style.',
                ]),
            ]);

        // Admin logo config - checked by every fallback-logo template
        // (login, dashboard, password reset, 2FA, transactional emails).
        DB::table('core_config')->updateOrInsert(
            ['code' => 'general.design.admin_logo.logo_image'],
            ['value' => 'channel/1/diids-logo.svg']
        );
    }

    /**
     * Homepage sections (hero carousel, lookbook, product carousels,
     * category tiles), footer links, and the services strip. Matches
     * whatever's in database/seeders/diids/theme-customizations.json -
     * update that file and re-run this seeder to change the baseline.
     */
    private function seedThemeCustomizations(): void
    {
        $channel = DB::table('channels')->where('code', 'default')->first();

        if (! $channel) {
            return;
        }

        $blocks = json_decode(
            file_get_contents(__DIR__.'/diids/theme-customizations.json'),
            true
        );

        // Fresh baseline: clear whatever the core seeder or a previous run
        // put here for this channel, then insert the DIIDS set in order.
        $existingIds = DB::table('theme_customizations')
            ->where('channel_id', $channel->id)
            ->pluck('id');

        DB::table('theme_customization_translations')->whereIn('theme_customization_id', $existingIds)->delete();
        DB::table('theme_customizations')->whereIn('id', $existingIds)->delete();

        foreach ($blocks as $block) {
            $id = DB::table('theme_customizations')->insertGetId([
                'theme_code' => 'default',
                'type' => $block['type'],
                'name' => $block['name'],
                'sort_order' => $block['sort_order'],
                'status' => $block['status'],
                'channel_id' => $channel->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('theme_customization_translations')->insert([
                'theme_customization_id' => $id,
                'locale' => 'en',
                'options' => json_encode($block['options'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    /**
     * About Us + every policy page. Bagisto's core seeder already creates
     * the 10 cms_pages/cms_page_translations rows with placeholder copy;
     * this only updates html_content and meta fields in place.
     */
    private function seedCmsPages(): void
    {
        $pages = json_decode(
            file_get_contents(__DIR__.'/diids/cms-pages.json'),
            true
        );

        foreach ($pages as $page) {
            DB::table('cms_page_translations')
                ->where('url_key', $page['url_key'])
                ->where('locale', 'en')
                ->update([
                    'page_title' => $page['page_title'],
                    'html_content' => $page['html_content'],
                    'meta_title' => $page['meta_title'],
                    'meta_description' => $page['meta_description'],
                    'meta_keywords' => $page['meta_keywords'],
                ]);
        }
    }

    /**
     * Two accounts: an unrestricted Administrator (renames Bagisto's
     * default seeded admin) and a demo restricted "Order Manager" role,
     * showing Bagisto's built-in per-admin permission checklist
     * (Admin -> Settings -> Roles) in action out of the box.
     *
     * Passwords here are placeholders meant to be changed after first
     * login, not real production credentials.
     */
    private function seedAdminAccounts(): void
    {
        DB::table('admins')->where('email', 'admin@example.com')->update([
            'name' => 'DIIDS Admin',
            'email' => 'admin@diids.com',
            'password' => bcrypt('Diids@Admin1'),
        ]);

        $orderManagerRoleId = DB::table('roles')->where('name', 'Order Manager')->value('id');

        if (! $orderManagerRoleId) {
            $permissions = [
                'dashboard', 'sales', 'sales.orders', 'sales.orders.create', 'sales.orders.view',
                'sales.orders.cancel', 'sales.invoices', 'sales.invoices.view', 'sales.invoices.create',
                'sales.invoices.update', 'sales.shipments', 'sales.shipments.view', 'sales.shipments.create',
                'sales.refunds', 'sales.refunds.view', 'sales.refunds.create', 'sales.transactions',
                'sales.transactions.view', 'sales.rma', 'sales.rma.requests', 'sales.rma.reasons',
                'sales.rma.rules', 'sales.rma.statuses', 'sales.rma.custom-fields', 'sales.eu_withdrawals',
                'sales.eu_withdrawals.view', 'sales.eu_withdrawals.decline', 'sales.eu_withdrawals.mark_refunded',
                'sales.eu_withdrawals.resend_confirmation',
            ];

            $orderManagerRoleId = DB::table('roles')->insertGetId([
                'name' => 'Order Manager',
                'description' => 'Can view and manage orders, invoices, shipments, and refunds only.',
                'permission_type' => 'custom',
                'permissions' => json_encode($permissions),
            ]);
        }

        DB::table('admins')->updateOrInsert(
            ['email' => 'orders@diids.com'],
            [
                'name' => 'Order Manager',
                'password' => bcrypt('Diids@Orders1'),
                'role_id' => $orderManagerRoleId,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
