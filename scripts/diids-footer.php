<?php

$ch = core()->getCurrentChannel();
$f = \Webkul\Theme\Models\ThemeCustomization::where('type', 'footer_links')
    ->where('theme_code', $ch->theme)
    ->where('channel_id', $ch->id)
    ->first();

if (! $f) { echo "no footer\n"; return; }

$f->options = [
    'column_1' => [
        ['title' => 'Our Story', 'url' => '/page/about-us', 'sort_order' => 1],
        ['title' => 'Contact', 'url' => '/contact-us', 'sort_order' => 2],
        ['title' => 'Customer Service', 'url' => '/page/customer-service', 'sort_order' => 3],
    ],
    'column_2' => [
        ['title' => 'Shipping', 'url' => '/page/shipping-policy', 'sort_order' => 1],
        ['title' => 'Returns', 'url' => '/page/return-policy', 'sort_order' => 2],
        ['title' => 'Refunds', 'url' => '/page/refund-policy', 'sort_order' => 3],
        ['title' => 'My Account', 'url' => '/customer/account/profile', 'sort_order' => 4],
    ],
    'column_3' => [
        ['title' => 'Privacy Policy', 'url' => '/page/privacy-policy', 'sort_order' => 1],
        ['title' => 'Payment Policy', 'url' => '/page/payment-policy', 'sort_order' => 2],
        ['title' => 'Terms & Conditions', 'url' => '/page/terms-conditions', 'sort_order' => 3],
    ],
];
$f->save();

echo "footer links cleaned\n";
