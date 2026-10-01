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
     * Working days to try when DHL refuses a pickup date - enough to clear
     * the longest run of Nigerian holidays (Christmas into the weekend).
     */
    protected const PICKUP_DATE_ATTEMPTS = 5;

    /**
     * Whether the last rates call was refused for its date (996).
     */
    protected bool $dateUnavailable = false;

    /**
     * DHL's estimated delivery date and time for the quoted service.
     */
    protected ?string $estimatedDelivery = null;

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

        $this->estimatedDelivery = null;

        $price = $this->getLiveRate($cart);

        if ($price === null) {
            $price = $this->getFallbackRate($cart);
        }

        if ($price === null) {
            return false;
        }

        $cartShippingRate = new CartShippingRate;

        $cartShippingRate->carrier = $this->getCode();
        $cartShippingRate->carrier_title = $this->getConfigData('title');
        $cartShippingRate->method = $this->getMethod();
        $cartShippingRate->method_title = $this->getConfigData('title');
        $cartShippingRate->method_description = $this->rateDescription();
        $cartShippingRate->price = core()->convertPrice($price);
        $cartShippingRate->base_price = $price;

        return $cartShippingRate;
    }

    /**
     * Price to charge when DHL can't quote the lane at all. A single flat
     * figure can't serve both a ~$5 domestic hop and a ~$55 international
     * leg without badly over- or under-charging one of them, so domestic
     * and international fall back separately. Returns null when no
     * fallback is configured, in which case DHL simply isn't offered.
     */
    protected function getFallbackRate($cart): ?float
    {
        $destination = strtoupper((string) ($cart?->shipping_address?->country ?? ''));

        $origin = strtoupper((string) $this->getConfigData('origin_country_code'));

        $isDomestic = $destination !== '' && $destination === $origin;

        $configured = $isDomestic
            ? $this->getConfigData('fallback_rate')
            : ($this->getConfigData('fallback_rate_international') ?: $this->getConfigData('fallback_rate'));

        if (! $configured) {
            return null;
        }

        Log::warning('DHL live rate unavailable, using fallback', [
            'destination' => $destination,
            'domestic' => $isDomestic,
            'fallback' => $configured,
        ]);

        return (float) $configured;
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
            'plannedShippingDate' => $this->nextPickupDate()->format('Y-m-d'),
            'isCustomsDeclarable' => 'false',
            'unitOfMeasurement' => 'metric',
        ];

        if ($this->getConfigData('origin_postal_code')) {
            $query['originPostalCode'] = $this->getConfigData('origin_postal_code');
        }

        if ($shippingAddress->postcode) {
            $query['destinationPostalCode'] = $shippingAddress->postcode;
        }

        /**
         * DHL validates the destination city against its own gazetteer and
         * rejects the whole request with "The destination location is
         * invalid" when it doesn't recognise the name. Customers routinely
         * enter a neighbourhood ("Okoko, Ojo") rather than the city DHL
         * knows ("Lagos"), so the lookup is retried against progressively
         * broader place names before giving up.
         */
        $pickupDate = $this->nextPickupDate();

        /**
         * Public holidays fail the same way weekends do - DHL answers 996
         * for 1 October or Christmas Day - and no fixed calendar keeps up
         * with Nigeria's moveable ones. So a 996 moves the pickup to the
         * next working day and asks again, a few times at most.
         */
        for ($attempt = 0; $attempt < self::PICKUP_DATE_ATTEMPTS; $attempt++) {
            $query['plannedShippingDate'] = $pickupDate->format('Y-m-d');

            $this->dateUnavailable = false;

            foreach ($this->destinationCityCandidates($shippingAddress) as $cityName) {
                $query['destinationCityName'] = $cityName;

                $price = $this->requestRate($query);

                if ($price !== null) {
                    return $price;
                }

                // The date, not the city, was refused - another city won't help.
                if ($this->dateUnavailable) {
                    break;
                }
            }

            if (! $this->dateUnavailable) {
                return null;
            }

            $pickupDate = $this->followingWorkingDay($pickupDate);
        }

        return null;
    }

    /**
     * Destination names to try against DHL, most specific first. Falls back
     * to the state, which for Nigerian addresses is normally the city DHL
     * actually serves.
     */
    protected function destinationCityCandidates($shippingAddress): array
    {
        $candidates = [];

        $city = trim((string) $shippingAddress->city);

        if ($city !== '') {
            $candidates[] = $city;

            /**
             * "Okoko, Ojo" and "Ikeja GRA" style entries: try the leading
             * segment on its own before widening to the state.
             */
            if (str_contains($city, ',')) {
                $candidates[] = trim(explode(',', $city)[0]);
            }
        }

        $state = trim((string) $shippingAddress->state);

        if ($state !== '') {
            $candidates[] = $state;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Performs a single rates lookup and returns the cheapest usable price
     * in the store's base currency, or null when DHL can't quote this lane.
     */
    protected function requestRate(array $query): ?float
    {
        try {
            $response = Http::withBasicAuth(
                $this->getConfigData('api_key'),
                $this->getConfigData('api_secret')
            )
                ->withHeaders(['x-version' => $this->apiVersion])
                ->timeout(10)
                ->get($this->getBaseUrl().'/rates', $query);

            if (! $response->successful()) {
                // 996: no product on the requested pickup date (weekend or holiday).
                $this->dateUnavailable = str_contains((string) $response->json('detail'), '996');

                /**
                 * A rejected destination name is an expected outcome here -
                 * the caller retries with a broader one - so it's logged at
                 * debug rather than warning to avoid filling the log on
                 * every checkout.
                 */
                Log::debug('DHL rate lookup failed', [
                    'status' => $response->status(),
                    'city' => $query['destinationCityName'] ?? null,
                    'body' => $response->body(),
                ]);

                return null;
            }

            $products = $response->json('products', []);

            if (empty($products)) {
                return null;
            }

            $cheapest = null;

            $cheapestEta = null;

            foreach ($products as $product) {
                $price = $this->extractPriceInBaseCurrency($product);

                if (
                    $price !== null
                    && ($cheapest === null || $price < $cheapest)
                ) {
                    $cheapest = $price;

                    $cheapestEta = $product['deliveryCapabilities']['estimatedDeliveryDateAndTime'] ?? null;
                }
            }

            if ($cheapest !== null) {
                $this->estimatedDelivery = $this->bookedProductEta($products, $query) ?? $cheapestEta;
            }

            return $cheapest;
        } catch (\Throwable $e) {
            Log::warning('DHL rate lookup exception: '.$e->getMessage());

            return null;
        }
    }

    /**
     * DHL's delivery estimate for the service that will actually be booked:
     * Express Domestic (N) within the country, Express Worldwide (P, quoted
     * as D for non-customs rate requests) abroad. Null when DHL did not
     * offer that service, so the caller falls back to the cheapest one's.
     */
    protected function bookedProductEta(array $products, array $query): ?string
    {
        $domestic = strtoupper((string) ($query['originCountryCode'] ?? ''))
            === strtoupper((string) ($query['destinationCountryCode'] ?? ''));

        $codes = $domestic ? ['N'] : ['P', 'D'];

        foreach ($codes as $code) {
            foreach ($products as $product) {
                if (
                    ($product['productCode'] ?? null) === $code
                    && ! empty($product['deliveryCapabilities']['estimatedDeliveryDateAndTime'])
                ) {
                    return $product['deliveryCapabilities']['estimatedDeliveryDateAndTime'];
                }
            }
        }

        return null;
    }

    /**
     * The checkout line under DHL: the estimated delivery date when DHL
     * gave one, otherwise the configured description.
     */
    protected function rateDescription(): ?string
    {
        if (! $this->estimatedDelivery) {
            return $this->getConfigData('description');
        }

        try {
            $date = \Carbon\Carbon::parse($this->estimatedDelivery)
                ->locale(app()->getLocale())
                ->translatedFormat('l, j F');
        } catch (\Throwable) {
            return $this->getConfigData('description');
        }

        return trans('shop::app.checkout.onepage.shipping.estimated-delivery', ['date' => $date]);
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

    /**
     * The next day DHL will actually collect.
     *
     * The carrier asked for "tomorrow", which on a Friday or Saturday is a
     * weekend - DHL does not collect domestically then and answers /rates
     * with 404 "product(s) not available for the requested pickup date".
     * The effect was that checkout offered no shipping at all, and since
     * DHL is the only method, nobody could buy over a weekend.
     */
    protected function nextPickupDate(): \Carbon\Carbon
    {
        return $this->followingWorkingDay(now());
    }

    /**
     * The first weekday after the given date.
     */
    protected function followingWorkingDay(\Carbon\Carbon $date): \Carbon\Carbon
    {
        $date = $date->copy()->addDay();

        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }
}
