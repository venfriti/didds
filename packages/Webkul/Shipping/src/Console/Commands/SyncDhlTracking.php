<?php

namespace Webkul\Shipping\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Sales\Models\Order;
use Webkul\Sales\Models\Shipment;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Shipping\Carriers\DhlShipmentService;

class SyncDhlTracking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dhl:sync-tracking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks DHL Express tracking status for shipped orders and marks orders as completed once DHL reports delivery';

    /**
     * Execute the console command.
     */
    public function handle(DhlShipmentService $dhlShipmentService, OrderRepository $orderRepository): int
    {
        if (! $dhlShipmentService->isConfigured()) {
            $this->info('DHL is not configured — skipping tracking sync.');

            return self::SUCCESS;
        }

        $shipments = Shipment::query()
            ->where('carrier_title', 'DHL Express')
            ->whereNotNull('track_number')
            ->whereHas('order', function ($query) {
                $query->whereNotIn('status', [Order::STATUS_COMPLETED, Order::STATUS_CANCELED, Order::STATUS_CLOSED]);
            })
            ->with('order')
            ->get();

        if ($shipments->isEmpty()) {
            $this->info('No DHL shipments awaiting delivery.');

            return self::SUCCESS;
        }

        foreach ($shipments as $shipment) {
            $trackingNumber = trim(explode(',', (string) $shipment->track_number)[0]);

            $status = $dhlShipmentService->getTrackingStatus($trackingNumber);

            if (! $status) {
                continue;
            }

            $this->line("Order #{$shipment->order->increment_id}: {$status['status_code']} — {$status['description']}");

            if ($this->isDelivered($status)) {
                $orderRepository->updateOrderStatus($shipment->order, Order::STATUS_COMPLETED);

                $this->info("Order #{$shipment->order->increment_id} marked as completed (DHL delivered).");
            }
        }

        return self::SUCCESS;
    }

    /**
     * Whether a tracking checkpoint means the parcel has been delivered.
     *
     * DHL Express signals delivery with the checkpoint type code "OK"
     * rather than a word like "delivered", so the code is matched first.
     * The description is checked too, since DHL's own docs and the various
     * API versions aren't wholly consistent about which they populate.
     */
    protected function isDelivered(array $status): bool
    {
        $code = strtoupper((string) ($status['status_code'] ?? ''));

        if (in_array($code, ['OK', 'DELIVERED'], true)) {
            return true;
        }

        return str_contains(strtolower((string) ($status['description'] ?? '')), 'delivered');
    }
}
