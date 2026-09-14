<?php

namespace Webkul\Shipping\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        $this->storeLabel($shipment, $result['label_base64'] ?? null);
    }

    /**
     * Save the waybill PDF DHL returns with the shipment. Without this the
     * label is discarded and the warehouse has nothing to print - the
     * tracking number alone doesn't get a parcel collected.
     *
     * A failure here must not undo the shipment: the waybill already
     * exists at DHL, so it's logged and the label can be re-fetched from
     * the DHL portal using the tracking number.
     */
    protected function storeLabel(ShipmentContract $shipment, ?string $labelBase64): void
    {
        if (! $labelBase64) {
            return;
        }

        try {
            $pdf = base64_decode($labelBase64, true);

            if ($pdf === false || ! str_starts_with($pdf, '%PDF-')) {
                Log::warning('DHL label for '.$shipment->track_number.' was not a readable PDF.');

                return;
            }

            Storage::disk('public')->put(
                'shipping-labels/'.$shipment->track_number.'.pdf',
                $pdf
            );
        } catch (\Throwable $e) {
            Log::warning('Could not store DHL label for '.$shipment->track_number.': '.$e->getMessage());
        }
    }
}
