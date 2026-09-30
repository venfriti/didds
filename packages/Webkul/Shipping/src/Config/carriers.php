<?php

return [
    'flatrate' => [
        'code' => 'flatrate',
        'title' => 'Flat Rate',
        'description' => 'Flat Rate Shipping',
        'active' => true,
        'default_rate' => '10',
        'type' => 'per_unit',
        'class' => 'Webkul\Shipping\Carriers\FlatRate',
    ],

    'free' => [
        'code' => 'free',
        'title' => 'Free Shipping',
        'description' => 'Free Shipping',
        'active' => true,
        'default_rate' => '0',
        'class' => 'Webkul\Shipping\Carriers\Free',
    ],

    'dhl' => [
        'code' => 'dhl',
        'title' => 'DHL Express',
        'description' => 'DHL Express Shipping',
        'active' => false,
        'fallback_rate' => '0',
        'default_item_weight' => '0.5',
        'class' => 'Webkul\Shipping\Carriers\Dhl',
    ],

    'pickup' => [
        'code' => 'pickup',
        'title' => 'Store Pickup',
        'active' => false,
        'domestic_only' => true,
        'default_rate' => '0',
        'class' => 'Webkul\Shipping\Carriers\Pickup',
    ],
];
