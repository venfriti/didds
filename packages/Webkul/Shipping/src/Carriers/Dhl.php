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
     * MyDHL API version. Required on every request via the x-version
     * header; DHL's own spec pins this and does not expect it to change
     * unless explicitly upgrading to a newer API revision.
     *
     * @var string
     */
    protected $apiVersion = '3.3.1';

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
            || ! $shippingAddress->city
        ) {
            return null;
        }

        $weight = $this->getTotalWeight($cart);

        // GET /rates takes flat query params, not a nested JSON body -
        // this is a single-piece rate request per DHL's MyDHL API spec.
        $query = [
            'accountNumber' => $this->getConfigData('account_number'),
            'originCountryCode' => $this->getConfigData('origin_country_code'),
            'originCityName' => $this->getConfigData('origin_city'),
            'destinationCountryCode' => $shippingAddress->country,
            'destinationCityName' => $shippingAddress->city,
            'weight' => $weight,
            'weightUnit' => 'KGM',
            'length' => (float) $this->getConfigData('package_length') ?: 20,
            'width' => (float) $this->getConfigData('package_width') ?: 15,
            'height' => (float) $this->getConfigData('package_height') ?: 10,
            'dimensionsUnit' => 'CM',
            'plannedShippingDate' => now()->addDay()->format('Y-m-d'),
            'isCustomsDeclarable' => 'false',
            'unitOfMeasurement' => 'metric',
        ];

        if ($this->getConfigData('origin_postal_code')) {
            $query['originPostalCode'] = $this->getConfigData('origin_postal_code');
        }

        if ($shippingAddress->postcode) {
            $query['destinationPostalCode'] = $shippingAddress->postcode;
        }

        try {
            $response = Http::withBasicAuth(
                $this->getConfigData('api_key'),
                $this->getConfigData('api_secret')
            )
                ->withHeaders(['x-version' => $this->apiVersion])
                ->timeout(10)
                ->get($this->getBaseUrl().'/rates', $query);

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

            $cheapest = null;

            foreach ($products as $product) {
                $price = $this->extractPriceInBaseCurrency($product);

                if (
                    $price !== null
                    && ($cheapest === null || $price < $cheapest)
                ) {
                    $cheapest = $price;
                }
            }

            return $cheapest;
        } catch (\Throwable $e) {
            Log::warning('DHL rate lookup exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * DHL quotes each product in several currencies at once (the billing
     * currency, the payer's local currency and a EUR base), and which one
     * sits at which index varies. Blindly taking the first entry means a
     * NGN amount can end up charged as if it were the store's own currency,
     * so the quote is matched on currency code and converted deliberately.
     */
    protected function extractPriceInBaseCurrency(array $product): ?float
    {
        $baseCurrency = core()->getBaseCurrencyCode();

        $prices = [];

        foreach ($product['totalPrice'] ?? [] as $totalPrice) {
            $currency = $totalPrice['priceCurrency'] ?? null;
            $price = $totalPrice['price'] ?? null;

            if (
                ! $currency
                || ! is_numeric($price)
            ) {
                continue;
            }

            $prices[strtoupper($currency)] = (float) $price;
        }

        if (empty($prices)) {
            return null;
        }

        /**
         * Best case DHL already quoted in the store's own currency.
         */
        if (isset($prices[$baseCurrency])) {
            return $prices[$baseCurrency];
        }

        /**
         * Otherwise convert from whichever currency we did get, preferring
         * the DHL account's own currency since that's what the shipment is
         * actually billed in.
         */
        $sourceCurrency = isset($prices[$this->accountCurrencyCode()])
            ? $this->accountCurrencyCode()
            : array_key_first($prices);

        $converted = $this->convertToBaseCurrency($prices[$sourceCurrency], $sourceCurrency);

        if ($converted === null) {
            Log::warning('DHL rate returned no usable currency', [
                'available' => array_keys($prices),
                'base' => $baseCurrency,
            ]);
        }

        return $converted;
    }

    /**
     * Converts a DHL-quoted amount into the store's base currency using the
     * configured exchange rates. Returns null when no rate is available,
     * so the caller falls back to the flat rate rather than charging a
     * wildly wrong number.
     */
    protected function convertToBaseCurrency(float $amount, string $fromCurrency): ?float
    {
        $baseCurrency = core()->getBaseCurrencyCode();

        if ($fromCurrency === $baseCurrency) {
            return $amount;
        }

        $rate = core()->getExchangeRate(
            core()->getAllCurrencies()->where('code', $fromCurrency)->first()?->id
        );

        if (! $rate || ! $rate->rate) {
            return null;
        }

        /**
         * Exchange rates are stored as "1 base currency = rate target
         * currency", so converting back into base divides rather than
         * multiplies.
         */
        return round($amount / (float) $rate->rate, 2);
    }

    /**
     * The currency the DHL account itself bills in, derived from the
     * configured origin country.
     */
    protected function accountCurrencyCode(): string
    {
        $currencyByCountry = [
            'NG' => 'NGN',
            'GH' => 'GHS',
            'GB' => 'GBP',
            'US' => 'USD',
            'ZA' => 'ZAR',
            'KE' => 'KES',
        ];

        $country = strtoupper((string) $this->getConfigData('origin_country_code'));

        return $currencyByCountry[$country] ?? 'USD';
    }

    /**
     * MyDHL API base URL - sandbox and production use different paths
     * under the same host.
     */
    protected function getBaseUrl(): string
    {
        return $this->getConfigData('sandbox_mode')
            ? 'https://express.api.dhl.com/mydhlapi/test'
            : 'https://express.api.dhl.com/mydhlapi';
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
