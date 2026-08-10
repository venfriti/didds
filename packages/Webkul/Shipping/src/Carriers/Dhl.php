<?php

namespace Webkul\Shipping\Carriers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Models\CartShippingRate;

class Dhl extends AbstractShipping
{
    /**
     * Shipping method carrier code.
     *
     * @var string
     */
    protected $code = 'dhl';

    /**
     * Shipping method code.
     *
     * @var string
     */
    protected $method = 'dhl_dhl';

    /**
     * MyDHL API base URL. Sandbox and production share the same host;
     * the API key pair itself determines which environment is used.
     *
     * @var string
     */
    protected $apiUrl = 'https://express.api.dhl.com/mydhlapi/rates';

    /**
     * Calculate rate for DHL.
     *
     * @return CartShippingRate|false
     */
    public function calculate()
    {
        if (! $this->isAvailable()) {
            return false;
        }

        return $this->getRate();
    }

    /**
     * Checks if the DHL shipping method is available: active and has credentials.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return parent::isAvailable()
            && $this->getConfigData('api_key')
            && $this->getConfigData('api_secret')
            && $this->getConfigData('account_number')
            && $this->getConfigData('origin_country_code')
            && $this->getConfigData('origin_postal_code')
            && $this->getConfigData('origin_city');
    }

    /**
     * Get rate. Falls back to the configured default rate if the live
     * DHL rate lookup fails for any reason (missing destination address,
     * API/network error, no product found for the requested lane), so
     * checkout never hard-fails because of a third-party outage.
     */
    public function getRate(): CartShippingRate|false
    {
        $cart = Cart::getCart();

        $price = $this->getLiveRate($cart);

        if ($price === null) {
            if (! $this->getConfigData('fallback_rate')) {
                return false;
            }

            $price = (float) $this->getConfigData('fallback_rate');
        }

        $cartShippingRate = new CartShippingRate;

        $cartShippingRate->carrier = $this->getCode();
        $cartShippingRate->carrier_title = $this->getConfigData('title');
        $cartShippingRate->method = $this->getMethod();
        $cartShippingRate->method_title = $this->getConfigData('title');
        $cartShippingRate->method_description = $this->getConfigData('description');
        $cartShippingRate->price = core()->convertPrice($price);
        $cartShippingRate->base_price = $price;

        return $cartShippingRate;
    }

    /**
     * Queries the DHL Express MyDHL rates API for a live quote. Returns
     * null (rather than throwing) on any failure so the caller can decide
     * whether to fall back to a flat rate.
     */
    protected function getLiveRate($cart): ?float
    {
        $shippingAddress = $cart->shipping_address;

        if (
            ! $shippingAddress
            || ! $shippingAddress->country
            || ! $shippingAddress->postcode
        ) {
            return null;
        }

        $weight = $this->getTotalWeight($cart);

        $payload = [
            'customerDetails' => [
                'shipperDetails' => [
                    'postalCode' => $this->getConfigData('origin_postal_code'),
                    'cityName' => $this->getConfigData('origin_city'),
                    'countryCode' => $this->getConfigData('origin_country_code'),
                ],
                'receiverDetails' => [
                    'postalCode' => $shippingAddress->postcode,
                    'cityName' => $shippingAddress->city,
                    'countryCode' => $shippingAddress->country,
                ],
            ],
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => $this->getConfigData('account_number'),
                ],
            ],
            'plannedShippingDateAndTime' => now()->addDay()->format('Y-m-d\TH:i:s \G\M\TP'),
            'unitOfMeasurement' => 'metric',
            'isCustomsDeclarable' => false,
            'packages' => [
                [
                    'weight' => $weight,
                    'dimensions' => [
                        'length' => (float) $this->getConfigData('package_length') ?: 20,
                        'width' => (float) $this->getConfigData('package_width') ?: 15,
                        'height' => (float) $this->getConfigData('package_height') ?: 10,
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withBasicAuth(
                $this->getConfigData('api_key'),
                $this->getConfigData('api_secret')
            )
                ->timeout(10)
                ->get($this->apiUrl, $payload);

            if (! $response->successful()) {
                Log::warning('DHL rate lookup failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $products = $response->json('products', []);

            if (empty($products)) {
                return null;
            }

            $cheapest = collect($products)
                ->pluck('totalPrice.0.price')
                ->filter()
                ->sort()
                ->first();

            return $cheapest !== null ? (float) $cheapest : null;
        } catch (\Throwable $e) {
            Log::warning('DHL rate lookup exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Total shippable weight for the cart, in kilograms. Falls back to a
     * configured minimum per stockable item when a product has no weight
     * set, since DHL rejects zero-weight packages.
     */
    protected function getTotalWeight($cart): float
    {
        $weight = 0;

        foreach ($cart->items as $item) {
            if (! $item->getTypeInstance()->isStockable()) {
                continue;
            }

            $itemWeight = (float) ($item->product->weight ?: $this->getConfigData('default_item_weight') ?: 0.5);

            $weight += $itemWeight * $item->quantity;
        }

        return $weight > 0 ? $weight : 0.5;
    }
}
