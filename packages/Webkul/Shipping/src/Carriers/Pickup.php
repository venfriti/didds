<?php

namespace Webkul\Shipping\Carriers;

use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\CartShippingRate;

/**
 * Store pickup: the customer collects the order in person, free of charge.
 *
 * No carrier is involved, so nothing is booked with DHL - the automatic
 * shipment listener only acts on dhl_dhl. The pickup address travels with
 * the rate as its description, and the order stores it as its
 * shipping_description, so an order keeps the address it was placed
 * against even if the store later moves.
 */
class Pickup extends AbstractShipping
{
    /**
     * Shipping method carrier code.
     *
     * @var string
     */
    protected $code = 'pickup';

    /**
     * Shipping method code.
     *
     * @var string
     */
    protected $method = 'pickup_pickup';

    /**
     * Calculate rate for store pickup.
     *
     * @return CartShippingRate|false
     */
    public function calculate()
    {
        if (! $this->isAvailable() || ! $this->servesDestination()) {
            return false;
        }

        return $this->getRate();
    }

    /**
     * Get rate.
     */
    public function getRate(): CartShippingRate
    {
        $cartShippingRate = new CartShippingRate;

        $cartShippingRate->carrier = $this->getCode();
        $cartShippingRate->carrier_title = $this->getConfigData('title');
        $cartShippingRate->method = $this->getMethod();
        $cartShippingRate->method_title = $this->getConfigData('title');
        $cartShippingRate->method_description = $this->pickupDetails();
        $cartShippingRate->price = 0;
        $cartShippingRate->base_price = 0;

        return $cartShippingRate;
    }

    /**
     * The address, followed by any collection instructions.
     */
    protected function pickupDetails(): string
    {
        return collect([
            trim((string) $this->getConfigData('address')),
            trim((string) $this->getConfigData('instructions')),
        ])->filter()->implode('. ');
    }

    /**
     * Collection is in person, so by default it is only offered when the
     * order is going to an address in the store's own country - a buyer in
     * London is not going to collect from Lekki.
     */
    protected function servesDestination(): bool
    {
        if (! $this->getConfigData('domestic_only')) {
            return true;
        }

        $destination = strtoupper((string) Cart::getCart()?->shipping_address?->country);

        $storeCountry = strtoupper((string) (core()->getConfigData('sales.carriers.dhl.origin_country_code') ?: 'NG'));

        return $destination === '' || $destination === $storeCountry;
    }
}
