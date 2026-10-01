<?php

namespace Webkul\Sales\Support;

/**
 * Checks that an order's display amounts are all in its own currency.
 *
 * Every display amount is its base amount times one exchange rate, so the
 * ratio display/base must be the same across the header, the shipping and
 * every line - and that ratio must be the rate of the currency the order
 * claims. A mixed order fails both: order #37 held NGN 15,000 of goods at
 * a ratio of 1 beside "73.26" of shipping at a ratio of 0.00075, under an
 * NGN label.
 *
 * The check needs no record of the rate at the time: it compares the
 * amounts with each other, and the label with today's rate only loosely,
 * so a daily rate refresh between checkout and payment is not flagged.
 */
class OrderCurrencyAudit
{
    /**
     * How far the order's own rate may sit from today's rate for its
     * currency before the label is called wrong. Daily moves are far
     * smaller; a wrong label is out by orders of magnitude.
     */
    protected const RATE_TOLERANCE = 0.05;

    /**
     * @param  array|object  $order  An Order model or OrderResource data
     * @param  iterable  $items  Line items (models or arrays)
     * @return array<int, string> Problems found; empty when consistent
     */
    public function problems($order, iterable $items): array
    {
        $get = fn ($source, string $key) => (float) (is_array($source) ? ($source[$key] ?? 0) : ($source->$key ?? 0));

        $currency = is_array($order) ? ($order['order_currency_code'] ?? null) : $order->order_currency_code;

        $baseGrand = $get($order, 'base_grand_total');

        if (! $currency || $baseGrand <= 0) {
            return [];
        }

        $ratio = $get($order, 'grand_total') / $baseGrand;

        $problems = [];

        $expected = (float) core()->convertPrice(1, $currency);

        if ($expected > 0 && abs($ratio / $expected - 1) > self::RATE_TOLERANCE) {
            $problems[] = sprintf(
                'grand total %.2f %s is %.6f x its base %.2f, but 1 base = %.6f %s',
                $get($order, 'grand_total'), $currency, $ratio, $baseGrand, $expected, $currency
            );
        }

        $pairs = [
            'sub_total' => 'base_sub_total',
            'shipping_amount' => 'base_shipping_amount',
            'discount_amount' => 'base_discount_amount',
        ];

        foreach ($pairs as $display => $base) {
            if ($problem = $this->offRatio($display, $get($order, $display), $get($order, $base), $ratio)) {
                $problems[] = $problem;
            }
        }

        foreach ($items as $item) {
            $sku = is_array($item) ? ($item['sku'] ?? '?') : $item->sku;

            foreach (['price' => 'base_price', 'total' => 'base_total'] as $display => $base) {
                if ($problem = $this->offRatio('item '.$sku.' '.$display, $get($item, $display), $get($item, $base), $ratio)) {
                    $problems[] = $problem;
                }
            }
        }

        return $problems;
    }

    /**
     * A display amount that is not its base at the order's ratio, allowing
     * for rounding to cents.
     */
    protected function offRatio(string $label, float $display, float $base, float $ratio): ?string
    {
        if ($base == 0.0 && $display == 0.0) {
            return null;
        }

        $expected = $base * $ratio;

        if (abs($display - $expected) <= max(0.02, abs($expected) * 0.002)) {
            return null;
        }

        return sprintf('%s is %.2f, expected %.2f (base %.2f at the order\'s rate)', $label, $display, $expected, $base);
    }
}
