<?php

namespace Webkul\Shipping\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Repositories\ShipmentRepository;
use Webkul\Shipping\Carriers\DhlShipmentService;

class AutoCreateShipmentListener
{
    public function __construct(
        protected ShipmentRepository $shipmentRepository,
        protected DhlShipmentService $dhlShipmentService
    ) {}

    /**
     * Book the DHL shipment as soon as an order is paid, when the setting
     * asks for it.
     *
     * Creating the shipment is what triggers the DHL waybill, so this is
     * the difference between "the parcel is booked the moment money
     * arrives" and "someone presses Ship when the box is packed". Both are
     * legitimate: the first suits a warehouse that dispatches everything
     * same-day, the second a small operation that picks stock by hand. The
     * setting lets the store choose, and creating a shipment manually works
     * exactly as before either way.
     */
    public function handle(OrderContract $order): void
    {
        if (! core()->getConfigData('sales.carriers.dhl.auto_create_shipment')) {
            return;
        }

        if ($order->shipping_method !== 'dhl_dhl') {
            return;
        }

        /**
         * Only book once the money is in. An unpaid order that later fails
         * would otherwise leave a real waybill - and a real charge - behind
         * it.
         */
        if (! $order->canShip() || $order->status === 'pending_payment') {
            return;
        }

        if ($order->shipments()->count()) {
            return;
        }

        if (! $this->dhlShipmentService->isConfigured()) {
            Log::warning('Automatic shipment skipped for order '.$order->increment_id.': DHL is not configured.');

            return;
        }

        $source = DB::table('inventory_sources')->where('status', 1)->value('id');

        if (! $source) {
            Log::warning('Automatic shipment skipped for order '.$order->increment_id.': no active inventory source.');

            return;
        }

        $items = [];

        foreach ($order->items as $item) {
            if ($item->qty_to_ship > 0) {
                $items[$item->id] = [$source => $item->qty_to_ship];
            }
        }

        if (! $items) {
            return;
        }

        try {
            $this->shipmentRepository->create([
                'order_id' => $order->id,
                'shipment' => [
                    'source'        => $source,
                    'items'         => $items,
                    'carrier_title' => 'DHL Express',
                    'track_number'  => '',
                ],
            ]);
        } catch (\Throwable $e) {
            /**
             * A failure here must not take the order down with it - the
             * customer has paid and the order is valid. It is logged so the
             * shipment can be created by hand, which is the same path a
             * store with this setting off uses anyway.
             */
            Log::error('Automatic DHL shipment failed for order '.$order->increment_id.': '.$e->getMessage());
        }
    }
}
