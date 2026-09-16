<?php

namespace Webkul\Shipping\Listeners;

use Illuminate\Support\Facades\Log;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Models\OrderComment;

class CancelledOrderShipmentListener
{
    /**
     * Flag any DHL waybill left behind by a cancelled order.
     *
     * MyDHL has no cancellation endpoint - DELETE /shipments/{awb} answers
     * 405 - so a waybill cannot be voided programmatically once minted.
     * With shipments booked automatically on payment, a cancellation can
     * therefore leave a live, billable consignment nobody knows about.
     *
     * This cannot cancel it, so it does the next best thing: records the
     * waybill numbers on the order itself, where whoever cancelled it will
     * see them, and logs a warning. Voiding the consignment with DHL
     * remains a manual step - but an obvious one rather than a silent
     * liability.
     */
    public function handle(OrderContract $order): void
    {
        $shipments = $order->shipments()->whereNotNull('track_number')->get();

        if ($shipments->isEmpty()) {
            return;
        }

        $waybills = $shipments->pluck('track_number')->filter()->implode(', ');

        $message = 'Order cancelled with DHL waybill(s) already booked: '.$waybills.'. '
            .'DHL has no cancellation API, so these must be voided directly with DHL '
            .'to avoid being charged for consignments that will not ship.';

        Log::warning('Cancelled order still has DHL waybills', [
            'order' => $order->increment_id,
            'waybills' => $waybills,
        ]);

        try {
            /**
             * Recorded as a customer-invisible comment so it surfaces on
             * the order screen for whoever handles the cancellation,
             * without being mailed to the customer.
             */
            OrderComment::create([
                'order_id'           => $order->id,
                'comment'            => $message,
                'customer_notified'  => 0,
            ]);
        } catch (\Throwable $e) {
            /**
             * The cancellation itself matters more than the note, so a
             * failure here is logged rather than thrown - the warning
             * above still records the waybills.
             */
            Log::warning('Could not add cancellation note to order '.$order->increment_id.': '.$e->getMessage());
        }
    }
}
