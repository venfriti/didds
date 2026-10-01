<?php

namespace Webkul\Shipping\Listeners;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Webkul\Sales\Contracts\Shipment as ShipmentContract;
use Webkul\Sales\Models\OrderComment;
use Webkul\Shipping\Carriers\DhlShipmentService;

class DhlShipmentListener
{
    /**
     * core_config row holding the DHL collections already booked.
     */
    protected const PICKUP_LOG = 'sales.carriers.dhl.pickup_bookings';

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

        if (core()->getConfigData('sales.carriers.dhl.request_pickup') && ! empty($result['planned_date'])) {
            $this->arrangeCollection($order, $result);
        }
    }

    /**
     * Make sure a DHL courier is coming for this parcel.
     *
     * One collection serves every parcel waiting at the store that day, so
     * a collection is booked once per date and later parcels that day ride
     * on it. Booked dates are kept in core_config, which survives cache
     * clears, separately for DHL's test and live systems so a test booking
     * can never stand in for a real one. Either way the outcome is written
     * on the order, where the person packing it will see it.
     */
    protected function arrangeCollection($order, array $result): void
    {
        $mode = core()->getConfigData('sales.carriers.dhl.sandbox_mode') ? 'test' : 'live';

        $key = $mode.':'.$result['planned_date'];

        $lock = Cache::lock('dhl-pickup-'.$key, 60);

        if (! $lock->block(20)) {
            Log::warning('Could not obtain DHL pickup lock for '.$key.'; order '.$order->increment_id);

            return;
        }

        try {
            $booked = $this->bookedCollections();

            if (isset($booked[$key])) {
                $this->note($order, 'Parcel joins the DHL collection already booked for '
                    .$this->describe($booked[$key]).'.');

                return;
            }

            $pickup = $this->dhlShipmentService->requestPickup($result['planned_date'], [
                'productCode' => $result['product_code'],
                'isCustomsDeclarable' => $result['is_customs_declarable'],
                'unitOfMeasurement' => 'metric',
                'packages' => $result['packages'],
            ], 'DIIDS order #'.$order->increment_id);

            if (! $pickup) {
                $this->note($order, 'DHL collection could not be booked automatically for this parcel. '
                    .'Book a collection in MyDHL+ or call DHL, or drop the parcel at a DHL service point.');

                return;
            }

            /**
             * Filed under the date asked for as well as the one DHL took,
             * so the next parcel planned for a holiday finds this booking
             * rather than making a second one for the same day.
             */
            $booked[$key] = $pickup;
            $booked[$mode.':'.$pickup['date']] = $pickup;

            $this->saveBookedCollections($booked);

            $this->note($order, 'DHL collection booked for '.$this->describe($pickup).'.');
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, array{confirmation: string, date: string, from: string, until: string}>
     */
    protected function bookedCollections(): array
    {
        $value = DB::table('core_config')->where('code', self::PICKUP_LOG)->value('value');

        return json_decode((string) $value, true) ?: [];
    }

    /**
     * Stored pruned to recent dates - only today and later matter.
     */
    protected function saveBookedCollections(array $booked): void
    {
        $cutoff = now()->subDays(14)->toDateString();

        $booked = array_filter($booked, fn ($pickup) => ($pickup['date'] ?? '') >= $cutoff);

        DB::table('core_config')->updateOrInsert(
            ['code' => self::PICKUP_LOG, 'channel_code' => null, 'locale_code' => null],
            ['value' => json_encode($booked), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    protected function describe(array $pickup): string
    {
        return \Carbon\Carbon::parse($pickup['date'])->format('l j F')
            .', '.$pickup['from'].' to '.$pickup['until']
            .' (DHL confirmation '.$pickup['confirmation'].')';
    }

    /**
     * An internal note on the order - not mailed to the customer.
     */
    protected function note($order, string $message): void
    {
        try {
            OrderComment::create([
                'order_id' => $order->id,
                'comment' => $message,
                'customer_notified' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not add DHL collection note to order '.$order->increment_id.': '.$e->getMessage());
        }
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
