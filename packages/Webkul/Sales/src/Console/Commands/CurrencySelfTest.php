<?php

namespace Webkul\Sales\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Support\OrderCurrencyAudit;
use Webkul\Sales\Transformers\OrderResource;

/**
 * Replays the mixed-currency failure against the live code, every night.
 *
 * For each store currency and for a home and an overseas address it prices
 * a cart as a shopper browsing in that currency would, then builds the
 * order data the way a payment confirmation does - from a request that has
 * chosen no currency, which is what turned a $84.54 checkout into a
 * "NGN 15,073" order. The order data must match the checkout and be in a
 * single currency.
 *
 * Nothing is saved: it runs inside a transaction that is rolled back, and
 * stops at the order data rather than creating an order, so no order
 * numbers are used. DHL is answered locally, so nothing is sent to it.
 */
class CurrencySelfTest extends Command
{
    protected $signature = 'orders:currency-self-test {--notify : Email the admin if a check fails}';

    protected $description = 'Check that orders keep the checkout currency (rolled back; sends nothing to DHL)';

    public function handle(OrderCurrencyAudit $audit): int
    {
        $product = $this->sampleProduct();

        if (! $product) {
            $this->warn('No purchasable product with stock to test with.');

            return self::SUCCESS;
        }

        $this->fakeDhl();

        $home = strtoupper((string) (core()->getConfigData('sales.carriers.dhl.origin_country_code') ?: 'NG'));

        $addresses = [
            $home => ['Lagos', 'Lagos', '100001', '08012345678'],
            'GB' => ['London', 'Greater London', 'SW1A 1AA', '442079250918'],
        ];

        $currencies = core()->getCurrentChannel()->currencies->pluck('code')->all();

        $failures = [];

        $checked = 0;

        DB::beginTransaction();

        try {
            foreach ($currencies as $currency) {
                foreach ($addresses as $country => [$city, $state, $postcode, $phone]) {
                    $label = $currency.' shopper, '.$country.' address';

                    // The shop request: the Currency middleware has chosen the browsing currency.
                    $this->forgetRequestState();
                    core()->setCurrentCurrency($currency);

                    Cart::addProduct($product['model'], $product['params']);

                    $address = [
                        'first_name' => 'Currency', 'last_name' => 'Check', 'email' => 'currency-check@diids.co',
                        'address' => ['1 Test Street'], 'city' => $city, 'state' => $state,
                        'country' => $country, 'postcode' => $postcode, 'phone' => $phone,
                    ];

                    Cart::saveAddresses(['billing' => $address + ['use_for_shipping' => true], 'shipping' => $address]);

                    $rates = \Webkul\Shipping\Facades\Shipping::collectRates();

                    $method = $this->pickShippingMethod($rates);

                    if (! $method || ! Cart::saveShippingMethod($method)) {
                        $failures[] = $label.': no shipping method could be selected';

                        continue;
                    }

                    Cart::savePaymentMethod(['method' => 'paystack']);
                    Cart::collectTotals();

                    $cart = Cart::getCart()->fresh();

                    $seen = [
                        'currency' => $cart->cart_currency_code,
                        'grand_total' => (float) $cart->grand_total,
                        'shipping_amount' => (float) $cart->shipping_amount,
                    ];

                    // The payment confirmation: no currency chosen.
                    $this->forgetRequestState();
                    Cart::setCart($cart);
                    Cart::collectTotals();

                    $data = (new OrderResource(Cart::getCart()->fresh()))->jsonSerialize();

                    $problems = $audit->problems($data, $data['items'] ?? []);

                    if (($data['order_currency_code'] ?? null) !== $seen['currency']) {
                        $problems[] = 'order currency '.($data['order_currency_code'] ?? '?').', checkout was '.$seen['currency'];
                    }

                    foreach (['grand_total', 'shipping_amount'] as $column) {
                        if (abs((float) ($data[$column] ?? 0) - $seen[$column]) > 0.02) {
                            $problems[] = $column.' '.round((float) ($data[$column] ?? 0), 2).', checkout showed '.round($seen[$column], 2);
                        }
                    }

                    $checked++;

                    $this->line(sprintf('  %-28s %s %s  %s',
                        $label, $seen['currency'], number_format($seen['grand_total'], 2),
                        $problems ? 'FAIL' : 'ok'));

                    foreach ($problems as $problem) {
                        $failures[] = $label.': '.$problem;
                    }

                    Cart::deActivateCart();
                }
            }
        } catch (\Throwable $e) {
            $failures[] = 'The check itself failed: '.$e->getMessage().' at '.basename($e->getFile()).':'.$e->getLine();
        } finally {
            DB::rollBack();

            $this->forgetRequestState();

            $this->restoreHttp();
        }

        if (! $failures) {
            $this->info($checked.' checkout(s) checked; every order stayed in its checkout currency.');

            return self::SUCCESS;
        }

        $this->error(count($failures).' problem(s):');

        foreach ($failures as $failure) {
            $this->line('  - '.$failure);
        }

        Log::critical('Currency self-test failed', ['failures' => $failures]);

        if ($this->option('notify') && ($admin = core()->getConfigData('emails.configure.email_settings.admin_email'))) {
            try {
                Mail::raw(
                    "The nightly currency check found orders that would not match their checkout currency.\n\n"
                    .implode("\n", $failures)
                    ."\n\nNo order was affected by the check itself. Recent code changes to checkout, cart or payments are the likely cause.",
                    fn ($message) => $message->to($admin)->subject('Currency self-test failed')
                );
            } catch (\Throwable $e) {
                Log::warning('Could not email currency self-test result: '.$e->getMessage());
            }
        }

        return self::FAILURE;
    }

    /**
     * A configurable product's in-stock variant, or an in-stock simple one.
     *
     * @return array{model: mixed, params: array}|null
     */
    protected function sampleProduct(): ?array
    {
        $repository = app(\Webkul\Product\Repositories\ProductRepository::class);

        $inStock = DB::table('product_inventories')->where('qty', '>', 0)->pluck('product_id');

        $variant = DB::table('products as child')
            ->join('products as parent', 'parent.id', '=', 'child.parent_id')
            ->join('product_flat as flat', fn ($join) => $join->on('flat.product_id', '=', 'parent.id')->where('flat.status', 1))
            ->where('parent.type', 'configurable')
            ->whereIn('child.id', $inStock)
            ->select('parent.id as parent_id', 'child.id as child_id')
            ->first();

        if ($variant) {
            return [
                'model' => $repository->find($variant->parent_id),
                'params' => ['product_id' => $variant->parent_id, 'quantity' => 1, 'selected_configurable_option' => $variant->child_id],
            ];
        }

        $simple = DB::table('products')->where('type', 'simple')->whereNull('parent_id')->whereIn('id', $inStock)->value('id');

        return $simple ? ['model' => $repository->find($simple), 'params' => ['product_id' => $simple, 'quantity' => 1]] : null;
    }

    /**
     * DHL first, so shipping carries a real, non-zero amount to check.
     */
    protected function pickShippingMethod(array|false $rates): ?string
    {
        $methods = [];

        foreach (($rates['shippingMethods'] ?? []) as $carrier) {
            foreach ($carrier['rates'] ?? [] as $rate) {
                $methods[] = is_array($rate) ? $rate['method'] : $rate->method;
            }
        }

        return in_array('dhl_dhl', $methods, true) ? 'dhl_dhl' : ($methods[0] ?? null);
    }

    /**
     * Answer DHL locally: a fixed naira quote, and nothing booked.
     */
    protected function fakeDhl(): void
    {
        Http::fake([
            'express.api.dhl.com/*' => function ($request) {
                if (str_contains($request->url(), '/rates')) {
                    return Http::response(['products' => [[
                        'productCode' => 'N',
                        'totalPrice' => [['currencyType' => 'BILLC', 'priceCurrency' => 'NGN', 'price' => 9365.36]],
                        'deliveryCapabilities' => ['estimatedDeliveryDateAndTime' => now()->addDays(3)->format('Y-m-d\T23:59:00')],
                    ]]]);
                }

                return Http::response(['detail' => 'Not available in the currency self-test'], 503);
            },
        ]);
    }

    /**
     * Put the real HTTP client back. Scheduled commands share one process
     * here (InProcessSchedule), so a fake left in place would answer the
     * next job's genuine DHL calls - the tracking sync among them.
     */
    protected function restoreHttp(): void
    {
        app()->forgetInstance(\Illuminate\Http\Client\Factory::class);

        Http::clearResolvedInstance(\Illuminate\Http\Client\Factory::class);
    }

    /**
     * Start the next step as a fresh request would: no currency chosen,
     * no channel or cart remembered.
     */
    protected function forgetRequestState(): void
    {
        (function () {
            $this->currentCurrency = null;
            $this->currencyChosen = false;
            $this->currentChannel = null;
        })->call(core());

        (function () {
            $this->cart = null;
        })->call(Cart::getFacadeRoot());
    }
}
