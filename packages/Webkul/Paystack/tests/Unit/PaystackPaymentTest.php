<?php

use Webkul\Core\Models\CoreConfig;
use Webkul\Paystack\Payment\Paystack;

beforeEach(function () {
    $this->paystack = app(Paystack::class);
});

it('returns the correct payment method code', function () {
    // Act
    $code = $this->paystack->getCode();

    // Assert
    expect($code)->toBe('paystack');
});

it('returns the payment method title from configuration', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.title',
        'value' => 'Pay with Paystack',
        'channel_code' => 'default',
        'locale_code' => 'en',
    ]);

    // Act
    $title = $this->paystack->getTitle();

    // Assert
    expect($title)->toBe('Pay with Paystack');
});

it('returns the payment method description from configuration', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.description',
        'value' => 'Pay securely using Paystack',
        'channel_code' => 'default',
        'locale_code' => 'en',
    ]);

    // Act
    $description = $this->paystack->getDescription();

    // Assert
    expect($description)->toBe('Pay securely using Paystack');
});

it('returns the configured public and secret keys', function () {
    // Arrange
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

    // Act & Assert
    expect($this->paystack->getPublicKey())->toBe('pk_test_fake_key')
        ->and($this->paystack->getSecretKey())->toBe('sk_test_fake_key');
});

it('checks if credentials are valid when both keys are configured', function () {
    // Arrange
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

    // Act
    $hasValidCredentials = $this->paystack->hasValidCredentials();

    // Assert
    expect($hasValidCredentials)->toBeTrue();
});

it('returns false if either credential is missing', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.public_key',
        'value' => 'pk_test_fake_key',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.secret_key',
        'value' => '',
        'channel_code' => 'default',
    ]);

    // Act
    $hasValidCredentials = $this->paystack->hasValidCredentials();

    // Assert
    expect($hasValidCredentials)->toBeFalse();
});

it('is not available when credentials are invalid', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.active',
        'value' => '1',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.public_key',
        'value' => '',
        'channel_code' => 'default',
    ]);

    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.secret_key',
        'value' => '',
        'channel_code' => 'default',
    ]);

    // Act
    $isAvailable = $this->paystack->isAvailable();

    // Assert
    expect($isAvailable)->toBeFalse();
});

it('is available when active and credentials are valid', function () {
    // Arrange
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

    // Act
    $isAvailable = $this->paystack->isAvailable();

    // Assert
    expect($isAvailable)->toBeTrue();
});

it('returns payment method image from config', function () {
    // Arrange
    CoreConfig::factory()->create([
        'code' => 'sales.payment_methods.paystack.image',
        'value' => 'paystack/custom-logo.png',
        'channel_code' => 'default',
    ]);

    // Act
    $image = $this->paystack->getImage();

    // Assert
    expect($image)->toContain('paystack/custom-logo.png');
});

it('returns default payment method image when not configured', function () {
    // Act
    $image = $this->paystack->getImage();

    // Assert
    expect($image)->toContain('paystack')
        ->and($image)->toContain('.png');
});

it('returns the correct redirect URL', function () {
    // Act
    $url = $this->paystack->getRedirectUrl();

    // Assert
    expect($url)->toBe(route('paystack.standard.redirect'));
});
