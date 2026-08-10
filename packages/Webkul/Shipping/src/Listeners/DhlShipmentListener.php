<?php

namespace Webkul\Shipping\Listeners;

use Webkul\Sales\Contracts\Shipment as ShipmentContract;
use Webkul\Shipping\Carriers\DhlShipmentService;

class DhlShipmentListener
{
    public function __construct(protected DhlShipmentService $dhlShipmentService) {}

    /**
     * When a shipment is created for an order that used DHL as its
     * shipping method, automatically create the matching DHL waybill and
     * fill in the tracking number - so admins don't have to manually book
     * it through DHL's own portal. Silently does nothing for non-DHL
     * orders, or if DHL isn't configured, or if the live call fails (the
     * admin can still fill in tracking manually as a fallback).
     */
    public function handle(ShipmentContract $shipment): void
    {
        $order = $shipment->order;

        if (! $order || $order->shipping_method !== 'dhl_dhl') {
            return;
        }

        if ($shipment->track_number) {
            return;
        }

        $result = $this->dhlShipmentService->createShipment($shipment);

        if (! $result) {
            return;
        }

        $shipment->carrier_title = 'DHL Express';
        $shipment->track_number = $result['tracking_number'];
        $shipment->save();
    }
}
