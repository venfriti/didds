<?php

use Webkul\Paystack\Payment\Paystack;

return [
    'paystack' => [
        'class' => Paystack::class,
        'code' => 'paystack',
        'title' => 'Paystack',
        'description' => 'Paystack',
        'active' => false,
        'sort' => 8,
    ],
];
