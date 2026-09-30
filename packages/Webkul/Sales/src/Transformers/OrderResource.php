<?php

namespace Webkul\Sales\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Webkul\Checkout\Facades\Cart;

class OrderResource extends JsonResource
{
    /**
     * Indicates if the resource's collection keys should be preserved.
     *
     * @var bool
     */
    public $preserveKeys = true;

    /**
     * Transform the resource into an array.
     *
     * @param  Request
     * @return array
     */
    public function toArray($request)
    {
        $this->assertCurrencyIsConsistent();

        $shippingInformation = [];

        if ($this->haveStockableItems()) {
            $shippingInformation = [
                'shipping_method' => $this->selected_shipping_rate->method,
                'shipping_title' => $this->selected_shipping_rate->carrier_title.' - '.$this->selected_shipping_rate->method_title,
                'shipping_description' => $this->selected_shipping_rate->method_description,
                'shipping_amount' => $this->selected_shipping_rate->price,
                'base_shipping_amount' => $this->selected_shipping_rate->base_price,
                'shipping_amount_incl_tax' => $this->selected_shipping_rate->price_incl_tax,
                'base_shipping_amount_incl_tax' => $this->selected_shipping_rate->base_price_incl_tax,
                'shipping_discount_amount' => $this->selected_shipping_rate->discount_amount,
                'base_shipping_discount_amount' => $this->selected_shipping_rate->base_discount_amount,
                'shipping_address' => (new OrderAddressResource($this->shipping_address))->jsonSerialize(),
            ];
        }

        return [
            'cart_id' => $this->id,
            'is_guest' => $this->is_guest,
            'customer_id' => $this->customer_id,
            'customer_type' => $this->customer ? get_class($this->customer) : null,
            'customer_email' => $this->customer_email,
            'customer_first_name' => $this->customer_first_name,
            'customer_last_name' => $this->customer_last_name,
            'channel_id' => $this->channel_id,
            'channel_name' => $this->channel->name,
            'channel_type' => get_class($this->channel),
            'total_item_count' => $this->items_count,
            'total_qty_ordered' => $this->items_qty,
            'base_currency_code' => $this->base_currency_code,
            'channel_currency_code' => $this->channel_currency_code,
            'order_currency_code' => $this->cart_currency_code,
            'grand_total' => $this->grand_total,
            'base_grand_total' => $this->base_grand_total,
            'sub_total' => $this->sub_total,
            'sub_total_incl_tax' => $this->sub_total_incl_tax,
            'base_sub_total' => $this->base_sub_total,
            'base_sub_total_incl_tax' => $this->base_sub_total_incl_tax,
            'tax_amount' => $this->tax_total,
            'base_tax_amount' => $this->base_tax_total,
            'shipping_tax_amount' => $this->selected_shipping_rate?->tax_amount ?? 0,
            'base_shipping_tax_amount' => $this->selected_shipping_rate?->base_tax_amount ?? 0,
            'coupon_code' => $this->coupon_code,
            'visitor_id' => $this->visitor_id,
            'landing_source' => $this->landing_source,
            'applied_cart_rule_ids' => $this->applied_cart_rule_ids,
            'discount_amount' => $this->discount_amount,
            'base_discount_amount' => $this->base_discount_amount,
            'billing_address' => (new OrderAddressResource($this->billing_address))->jsonSerialize(),
            $this->mergeWhen($this->haveStockableItems(), $shippingInformation),
            'payment' => (new OrderPaymentResource($this->payment))->jsonSerialize(),
            'items' => OrderItemResource::collection($this->items)->jsonSerialize(),
        ];
    }

    /**
     * Refuse to turn a cart into an order when its display amounts don't
     * match its own currency.
     *
     * An order records order_currency_code from the cart, so a cart whose
     * currency changed without its amounts being repriced would be written
     * with, say, dollar figures stamped as naira - permanently, on the
     * order, its items and every invoice built from them. That is how
     * invoices came to show "NGN 40.00" beside "NGN 30,800.00" for the same
     * bundle.
     *
     * Repricing here is safe: the base amounts are authoritative and
     * unchanged, so this only rebuilds the display side that was already
     * wrong.
     */
    protected function assertCurrencyIsConsistent(): void
    {
        $currency = $this->cart_currency_code;

        if (! $currency || (float) $this->base_grand_total == 0.0) {
            return;
        }

        $stale = $this->staleAmounts($currency);

        if (! $stale) {
            return;
        }

        Log::warning('Cart '.$this->id.' had stale display amounts at order time', [
            'currency' => $currency,
            'stale' => $stale,
        ]);

        /**
         * Re-total in the cart's own currency - not whatever this request
         * happens to be using, which outside the shop is the channel base.
         * Re-totalling in the wrong currency is exactly what produced a
         * "NGN 15,073" order from a $84.54 checkout.
         */
        core()->setCurrentCurrency($currency);

        Cart::setCart($this->resource);

        Cart::collectTotals();

        $this->resource->refresh();

        $this->resource->load('items', 'shipping_rates');

        if (! $stale = $this->staleAmounts($currency)) {
            return;
        }

        /**
         * Still inconsistent after a re-total: build the display side from
         * the base amounts directly. By this point the customer has paid,
         * so refusing the order is not an option - but a mixed-currency
         * order must never be written either.
         */
        Log::error('Cart '.$this->id.' still inconsistent after re-total; deriving display amounts from base', [
            'currency' => $currency,
            'stale' => $stale,
        ]);

        $this->deriveDisplayAmountsFromBase($currency);
    }

    /**
     * Every display amount copied onto the order - header, shipping and
     * each line - that has drifted from its base in the given currency.
     *
     * @return array<int, string>
     */
    protected function staleAmounts(string $currency): array
    {
        $stale = [];

        foreach (['grand_total', 'sub_total', 'shipping_amount', 'discount_amount', 'tax_total'] as $column) {
            if ($this->amountIsStale($this->{'base_'.$column}, $this->$column, $currency)) {
                $stale[] = $column;
            }
        }

        if ($rate = $this->selected_shipping_rate) {
            if ($this->amountIsStale($rate->base_price, $rate->price, $currency)) {
                $stale[] = 'shipping_rate.price';
            }
        }

        /**
         * Line items are checked on their own: the header can agree with
         * its base while a line still holds the old currency, and line
         * prices are copied onto the order and every invoice built from it.
         */
        foreach ($this->items as $item) {
            foreach (['price', 'total'] as $column) {
                if ($this->amountIsStale($item->{'base_'.$column}, $item->$column, $currency)) {
                    $stale[] = 'item '.$item->id.' '.$column;
                }
            }
        }

        return $stale;
    }

    /**
     * Last resort: set every display amount to its base converted into
     * the order currency, in memory, so the order is written consistently.
     */
    protected function deriveDisplayAmountsFromBase(string $currency): void
    {
        $convert = fn ($base) => round((float) core()->convertPrice((float) $base, $currency), 4);

        foreach ([
            'grand_total', 'sub_total', 'sub_total_incl_tax', 'tax_total',
            'discount_amount', 'shipping_amount', 'shipping_amount_incl_tax',
        ] as $column) {
            $this->resource->$column = $convert($this->resource->{'base_'.$column});
        }

        if ($rate = $this->selected_shipping_rate) {
            foreach (['price', 'price_incl_tax', 'tax_amount', 'discount_amount'] as $column) {
                $rate->$column = $convert($rate->{'base_'.$column});
            }
        }

        foreach ($this->items as $item) {
            foreach (['price', 'price_incl_tax', 'total', 'total_incl_tax', 'tax_amount', 'discount_amount'] as $column) {
                $item->$column = $convert($item->{'base_'.$column});
            }
        }
    }

    /**
     * Whether a display amount has drifted from the base it was converted
     * from. A zero base carries no information - a configurable child's
     * base_price is legitimately zero - so those are never treated as
     * stale.
     */
    protected function amountIsStale($base, $shown, string $currency): bool
    {
        if ((float) $base == 0.0) {
            return false;
        }

        $expected = (float) core()->convertPrice((float) $base, $currency);

        return abs($expected - (float) $shown) > 0.01;
    }
}
