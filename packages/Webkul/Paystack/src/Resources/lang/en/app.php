<?php

return [
    'title' => 'Paystack',

    'description' => 'Pay securely with Paystack (card, bank transfer, USSD, mobile money).',

    'response' => [
        'provide-credentials' => 'Please provide valid Paystack credentials.',
        'cart-not-found' => 'Cart not found.',
        'cart-processed' => 'This order has already been processed.',
        'invalid-reference' => 'Invalid payment reference.',
        'amount-mismatch' => 'Payment amount does not match the order total.',
        'verification-failed' => 'Payment verification failed',
        'payment-failed' => 'Payment failed',
        'payment-success' => 'Payment completed successfully.',
        'payment-cancelled' => 'Payment was cancelled.',
        'saved-card-not-found' => 'The selected saved card could not be found.',
        'saved-card-charge-failed' => 'Charging the saved card failed. Please try another payment method.',
    ],
];
