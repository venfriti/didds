<?php

namespace Webkul\Shop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Webkul\Checkout\Facades\Cart;
use Webkul\Core\Repositories\CurrencyRepository;

class Currency
{
    /**
     * Country (ISO-2) -> currency code. Anything not listed falls back to USD.
     */
    protected array $countryCurrency = [
        'NG' => 'NGN',
        'GB' => 'GBP', 'UK' => 'GBP',
        // Eurozone
        'AT' => 'EUR', 'BE' => 'EUR', 'HR' => 'EUR', 'CY' => 'EUR', 'EE' => 'EUR',
        'FI' => 'EUR', 'FR' => 'EUR', 'DE' => 'EUR', 'GR' => 'EUR', 'IE' => 'EUR',
        'IT' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'LU' => 'EUR', 'MT' => 'EUR',
        'NL' => 'EUR', 'PT' => 'EUR', 'SK' => 'EUR', 'SI' => 'EUR', 'ES' => 'EUR',
    ];

    /**
     * Create a middleware instance.
     *
     * @return void
     */
    public function __construct(protected CurrencyRepository $currencyRepository) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $currencies = core()->getCurrentChannel()->currencies->pluck('code')->toArray();
        $currencyCode = core()->getRequestedLocaleCode('currency', false);

        // explicit choice this request?
        $explicit = $currencyCode && in_array($currencyCode, $currencies);

        // session (a prior explicit choice or a prior geo result)
        if (! $explicit && ($session = session()->get('currency')) && in_array($session, $currencies)) {
            $currencyCode = $session;
        }

        // no choice yet -> geolocate once and remember
        if (! $currencyCode || ! in_array($currencyCode, $currencies)) {
            $currencyCode = $this->currencyFromLocation($request, $currencies);
        }

        // final fallback
        if (! $currencyCode || ! in_array($currencyCode, $currencies)) {
            $currencyCode = core()->getCurrentChannel()->base_currency->code;
        }

        core()->setCurrentCurrency($currencyCode);
        session()->put('currency', $currencyCode);

        // mark explicit choices so geo never overrides them later
        if ($explicit) {
            session()->put('currency_explicit', true);
        }

        unset($request['currency']);

        $this->refreshStaleCartTotals($currencyCode);

        return $next($request);
    }

    /**
     * Cart totals (shipping_amount in particular, since it's cached on
     * selected_shipping_rate rather than recomputed per-request like item
     * prices) are only recalculated when collectTotals() runs. If the
     * resolved currency has changed since the cart's totals were last
     * collected — e.g. a cart created under the old base currency before an
     * admin currency migration, or a visitor whose geo-resolved currency
     * flips between requests — the cached amounts silently mismatch the
     * currency symbol shown elsewhere on the same page. Force a recollect
     * so every amount on the page reflects the same currency consistently.
     */
    protected function refreshStaleCartTotals(string $currencyCode): void
    {
        $cart = Cart::getCart();

        if (! $cart) {
            return;
        }

        if ($cart->cart_currency_code !== $currencyCode) {
            Cart::collectTotals();

            return;
        }

        /**
         * The currency code matching is not proof the amounts are current.
         * A cart can have its code updated without its display totals being
         * recomputed, which leaves naira figures labelled as dollars - the
         * displayed total then disagrees with what the customer is actually
         * charged (base_grand_total). Converting the base total back should
         * reproduce the displayed one; when it doesn't, the display side is
         * stale and has to be recollected.
         */
        $expected = (float) core()->convertPrice($cart->base_grand_total, $currencyCode);

        if (abs($expected - (float) $cart->grand_total) > 0.01) {
            Cart::collectTotals();
        }
    }

    /**
     * Resolve a currency from the visitor's country, defaulting to USD.
     * Uses (in order): Cloudflare CF-IPCountry header, then a free IP API.
     * Result is cached in session for the visit.
     */
    protected function currencyFromLocation(Request $request, array $allowed): ?string
    {
        $country = $request->headers->get('CF-IPCountry'); // set when behind Cloudflare

        if (! $country) {
            $ip = $request->ip();

            // don't geolocate local/private IPs — default to USD in dev
            if (in_array($ip, ['127.0.0.1', '::1']) || str_starts_with((string) $ip, '192.168.')) {
                return in_array('USD', $allowed) ? 'USD' : null;
            }

            try {
                $resp = Http::timeout(2)->get("https://ipapi.co/{$ip}/country/");
                if ($resp->ok()) {
                    $country = trim($resp->body());
                }
            } catch (\Throwable $e) {
                // network/geo failure -> USD default
            }
        }

        $currency = $country && isset($this->countryCurrency[strtoupper($country)])
            ? $this->countryCurrency[strtoupper($country)]
            : 'USD';

        return in_array($currency, $allowed) ? $currency : (in_array('USD', $allowed) ? 'USD' : null);
    }
}
