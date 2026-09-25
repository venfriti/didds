<?php

namespace Webkul\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateFloatingExchangeRates extends Command
{
    /**
     * @var string
     */
    protected $signature = 'currency:rates:refresh {--dry-run : Show what would change without writing}';

    /**
     * @var string
     */
    protected $description = 'Refresh exchange rates from live FX data, leaving pinned currencies alone';

    /**
     * Currencies whose rate is set deliberately and must not float.
     *
     * USD is pinned because the shop prices against a chosen rate rather
     * than the market: naira is the stored price, and the dollar figure
     * Paystack charges should stay predictable rather than moving daily.
     *
     * @var array<int, string>
     */
    protected array $pinned = ['USD'];

    /**
     * The free tier of exchangerate-api. No key required, updated daily.
     */
    protected string $endpoint = 'https://open.er-api.com/v6/latest/';

    public function handle(): int
    {
        $base = core()->getBaseCurrencyCode();

        $response = Http::timeout(20)->get($this->endpoint.$base);

        if (! $response->successful() || $response->json('result') !== 'success') {
            $this->error('Could not fetch rates for '.$base.' (HTTP '.$response->status().').');

            Log::warning('Exchange rate refresh failed', [
                'base' => $base,
                'status' => $response->status(),
            ]);

            /**
             * A failed fetch leaves the existing rates in place, which is
             * the safe outcome - stale rates price orders, missing ones
             * would break them.
             */
            return self::FAILURE;
        }

        $rates = $response->json('rates') ?? [];

        $this->info('Base '.$base.', source updated '.($response->json('time_last_update_utc') ?? 'unknown'));

        $dryRun = (bool) $this->option('dry-run');
        $changed = 0;

        foreach (DB::table('currencies')->get(['id', 'code']) as $currency) {
            if ($currency->code === $base) {
                continue;
            }

            if (in_array($currency->code, $this->pinned, true)) {
                $this->line('  '.str_pad($currency->code, 6).'pinned, left unchanged');

                continue;
            }

            $rate = $rates[$currency->code] ?? null;

            if (! $rate) {
                $this->line('  '.str_pad($currency->code, 6).'not quoted by the source, left unchanged');

                continue;
            }

            $current = DB::table('currency_exchange_rates')
                ->where('target_currency', $currency->id)
                ->value('rate');

            $this->line('  '.str_pad($currency->code, 6)
                .'1 '.$currency->code.' = '.number_format(1 / $rate, 2).' '.$base
                .'   (was '.($current ? number_format(1 / $current, 2) : 'unset').')');

            if (! $dryRun) {
                DB::table('currency_exchange_rates')->updateOrInsert(
                    ['target_currency' => $currency->id],
                    ['rate' => $rate, 'updated_at' => now()]
                );
            }

            $changed++;
        }

        $this->info($dryRun
            ? $changed.' rate(s) would change. Nothing written.'
            : $changed.' rate(s) updated.');

        return self::SUCCESS;
    }
}
