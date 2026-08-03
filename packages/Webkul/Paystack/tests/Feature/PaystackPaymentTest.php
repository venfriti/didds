<?php

use Illuminate\Support\Facades\Http;
use Webkul\Checkout\Facades\Cart;
use Webkul\Core\Models\CoreConfig;
use Webkul\Customer\Models\CustomerPaymentMethod;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\OrderTransaction;

beforeEach(function () {
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.active',
        'value' => '1',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.public_key',
        'value' => 'pk_test_fake_key',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.secret_key',
        'value' => 'sk_test_fake_key',
        'channel_code' => 'default',
    ]);
});

it('redirects to cart when paystack credentials are invalid', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.secret_key',
        'value' => '',
        'channel_code' => 'default',
    ]);

    // Act
    $response = $this->get(route('paystack.standard.redirect'));

    // Assert
    $response->assertRedirect(route('shop.checkout.cart.index'));

    $response->assertSessionHas('error');
});

it('redirects to cart when cart is not found', function () {
    // Arrange
    Cart::shouldReceive('getCart')->andReturn(null);

    // Act
    $response = $this->get(route('paystack.standard.redirect'));

    // Assert
    $response->assertRedirect(route('shop.checkout.cart.index'));

    $response->assertSessionHas('error');
});

it('redirects to cart when initialize call fails', function () {
    // Arrange
    $this->createCartWithItems('paystack');

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => false, 'message' => 'Invalid key'], 400),
    ]);

    // Act
    $response = $this->get(route('paystack.standard.redirect'));

    // Assert
    $response->assertRedirect(route('shop.checkout.cart.index'));

    $response->assertSessionHas('error');
});

it('redirects to paystack authorization url on successful initialize', function () {
    // Arrange
    $this->createCartWithItems('paystack');

    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response([
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'abc123',
                'reference' => 'TXN-test-ref',
            ],
        ], 200),
    ]);

    // Act
    $response = $this->get(route('paystack.standard.redirect'));

    // Assert
    $response->assertRedirect('https://checkout.paystack.com/abc123');
});

it('redirects to cart when callback reference is missing', function () {
    // Act
    $response = $this->get(route('paystack.payment.callback'));

    // Assert
    $response->assertRedirect(route('shop.checkout.cart.index'));

    $response->assertSessionHas('error');
});

it('redirects to cart when verification fails', function () {
    // Arrange
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(['status' => false], 400),
    ]);

    // Act
    $response = $this->get(route('paystack.payment.callback', ['reference' => 'bad-ref']));

    // Assert
    $response->assertRedirect(route('shop.checkout.cart.index'));

    $response->assertSessionHas('error');
});

it('redirects to cart when cart is already processed', function () {
    // Arrange
    $cart = $this->createCartWithItems('paystack', [
        'is_active' => 0,
        'base_grand_total' => 100.00,
        'grand_total' => 100.00,
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'TXN-already-processed',
                'amount' => 10000,
                'currency' => 'NGN',
                'channel' => 'card',
                'metadata' => ['cart_id' => $cart->id],
                'authorization' => [],
            ],
        ], 200),
    ]);

    // Act
    $response = $this->get(route('paystack.payment.callback', ['reference' => 'TXN-already-processed']));

    // Assert
    $response->assertRedirect(route('shop.checkout.cart.index'));

    $response->assertSessionHas('error');
});

it('successfully processes paystack payment and creates order with invoice', function () {
    // Arrange
    $cart = $this->createCartWithItems('paystack');

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'TXN-success-123',
                'amount' => (int) round($cart->grand_total * 100),
                'currency' => 'NGN',
                'channel' => 'card',
                'paid_at' => now()->toIso8601String(),
                'transaction_date' => now()->toIso8601String(),
                'metadata' => ['cart_id' => $cart->id],
                'authorization' => [
                    'authorization_code' => 'AUTH_test123',
                    'reusable' => true,
                    'card_type' => 'visa',
                    'last4' => '4081',
                    'exp_month' => '12',
                    'exp_year' => '2030',
                    'bank' => 'Test Bank',
                ],
            ],
        ], 200),
    ]);

    // Act
    $response = $this->get(route('paystack.payment.callback', ['reference' => 'TXN-success-123']));

    // Assert
    $response->assertRedirect(route('shop.checkout.onepage.success'));

    $response->assertSessionHas('success');

    $response->assertSessionHas('order_id');

    $order = Order::where('customer_id', $cart->customer_id)->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe('processing')
        ->and($order->payment->additional['paystack_authorization_code'])->toBe('AUTH_test123')
        ->and($order->payment->additional['paystack_authorization_reusable'])->toBeTrue();

    $orderTransaction = OrderTransaction::where('transaction_id', 'TXN-success-123')->first();

    expect($orderTransaction)->not->toBeNull()
        ->and($orderTransaction->order_id)->toBe($order->id)
        ->and($orderTransaction->status)->toBe('success');

    $invoice = Invoice::where('order_id', $order->id)->first();

    expect($invoice)->not->toBeNull();

    $cart->refresh();

    expect($cart->is_active)->toBe(0);
});

it('shows error message on payment cancellation', function () {
    // Act
    $response = $this->get(route('paystack.payment.cancel'));

    // Assert
    $response->assertRedirect(route('shop.checkout.cart.index'));

    $response->assertSessionHas('error');
});

it('does not create a duplicate order when webhook fires for an already-processed reference', function () {
    // Arrange
    $cart = $this->createCartWithItems('paystack');

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'TXN-webhook-dup',
                'amount' => (int) round($cart->grand_total * 100),
                'currency' => 'NGN',
                'channel' => 'card',
                'metadata' => ['cart_id' => $cart->id],
                'authorization' => [],
            ],
        ], 200),
    ]);

    $this->get(route('paystack.payment.callback', ['reference' => 'TXN-webhook-dup']));

    $ordersCountAfterCallback = Order::count();

    $secretKey = 'sk_test_fake_key';

    $payload = json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => 'TXN-webhook-dup',
            'status' => 'success',
            'amount' => (int) round($cart->grand_total * 100),
            'channel' => 'card',
            'metadata' => ['cart_id' => $cart->id],
            'authorization' => [],
        ],
    ]);

    $signature = hash_hmac('sha512', $payload, $secretKey);

    // Act
    $response = $this->call('POST', route('paystack.webhook'), [], [], [], [
        'HTTP_x-paystack-signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    // Assert
    $response->assertOk();

    expect(Order::count())->toBe($ordersCountAfterCallback);
});

it('rejects webhook requests with an invalid signature', function () {
    // Arrange
    $payload = json_encode([
        'event' => 'charge.success',
        'data' => ['reference' => 'TXN-invalid-sig'],
    ]);

    // Act
    $response = $this->call('POST', route('paystack.webhook'), [], [], [], [
        'HTTP_x-paystack-signature' => 'not-a-valid-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    // Assert
    $response->assertStatus(400);
});

it('charges a saved card server-side without redirecting to paystack hosted page', function () {
    // Arrange
    $cart = $this->createCartWithItems('paystack');

    $savedCard = CustomerPaymentMethod::factory()->create([
        'customer_id' => $cart->customer_id,
        'gateway' => 'paystack',
        'authorization_code' => 'AUTH_saved123',
        'card_type' => 'visa',
        'last4' => '4081',
        'exp_month' => 12,
        'exp_year' => 2030,
        'is_default' => true,
    ]);

    $this->actingAs($cart->customer, 'customer');

    session(['paystack_saved_card_id' => $savedCard->id]);

    Http::fake([
        'api.paystack.co/transaction/charge_authorization' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'TXN-saved-card-charge',
                'amount' => (int) round($cart->grand_total * 100),
                'currency' => 'NGN',
                'channel' => 'card',
                'authorization' => [
                    'authorization_code' => 'AUTH_saved123',
                    'reusable' => true,
                ],
            ],
        ], 200),
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true], 200),
    ]);

    // Act
    $response = $this->get(route('paystack.standard.redirect'));

    // Assert
    $response->assertRedirect(route('shop.checkout.onepage.success'));

    Http::assertNotSent(function ($request) {
        return str_contains($request->url(), 'transaction/initialize');
    });

    $order = Order::where('customer_id', $cart->customer_id)->first();

    expect($order)->not->toBeNull();
});
