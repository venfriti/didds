<?php

namespace Webkul\Shipping\Carriers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Webkul\Sales\Models\Shipment;

/**
 * Creates real DHL Express shipments (waybill + tracking number) via the
 * MyDHL API's Shipping product, as opposed to Dhl.php's Rating product
 * which only quotes a price at checkout.
 *
 * Requires the DHL developer app to have Shipping API access enabled and
 * a live DHL Express account number - the Rating-only sandbox credentials
 * used for checkout rate quotes are not sufficient for this endpoint.
 */
class DhlShipmentService
{
    /**
     * MyDHL API version. Required on every request via the x-version
     * header.
     *
     * @var string
     */
    protected $apiVersion = '3.3.1';

    /**
     * Creates a DHL shipment for the given order shipment and returns the
     * tracking number(s), or null if DHL isn't configured / the call fails.
     * Never throws - shipment creation in the admin must still succeed
     * even if DHL is down or misconfigured, since the admin can always
     * retry or fall back to manual booking.
     */
    public function createShipment(Shipment $shipment): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $order = $shipment->order;
        $shippingAddress = $order->shipping_address;

        if (! $shippingAddress) {
            Log::warning("DHL shipment creation skipped: order {$order->id} has no shipping address.");

            return null;
        }

        $payload = $this->buildPayload($shipment, $order, $shippingAddress);

        try {
            $response = Http::withBasicAuth(
                core()->getConfigData('sales.carriers.dhl.api_key'),
                core()->getConfigData('sales.carriers.dhl.api_secret')
            )
                ->withHeaders(['x-version' => $this->apiVersion])
                ->timeout(20)
                ->post($this->getBaseUrl().'/shipments', $payload);

            if (! $response->successful()) {
                Log::error('DHL shipment creation failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $data = $response->json();

            $trackingNumber = $data['shipmentTrackingNumber'] ?? null;

            $labelUrl = $data['documents'][0]['content'] ?? null;

            if (! $trackingNumber) {
                Log::warning('DHL shipment created but no tracking number returned', [
                    'order_id' => $order->id,
                    'response' => $data,
                ]);

                return null;
            }

            return [
                'tracking_number' => $trackingNumber,
                'label_base64' => $labelUrl,
            ];
        } catch (\Throwable $e) {
            Log::error('DHL shipment creation exception: '.$e->getMessage(), [
                'order_id' => $order->id,
            ]);

            return null;
        }
    }

    /**
     * Whether DHL has enough configuration to attempt shipment creation.
     */
    public function isConfigured(): bool
    {
        return (bool) (
            core()->getConfigData('sales.carriers.dhl.active')
            && core()->getConfigData('sales.carriers.dhl.api_key')
            && core()->getConfigData('sales.carriers.dhl.api_secret')
            && core()->getConfigData('sales.carriers.dhl.account_number')
            && core()->getConfigData('sales.carriers.dhl.origin_country_code')
            && core()->getConfigData('sales.carriers.dhl.origin_postal_code')
            && core()->getConfigData('sales.carriers.dhl.origin_city')
            && core()->getConfigData('sales.carriers.dhl.origin_contact_name')
            && core()->getConfigData('sales.carriers.dhl.origin_phone')
            && core()->getConfigData('sales.carriers.dhl.origin_email')
        );
    }

    /**
     * MyDHL API base URL - sandbox and production use different paths
     * under the same host.
     */
    protected function getBaseUrl(): string
    {
        return core()->getConfigData('sales.carriers.dhl.sandbox_mode')
            ? 'https://express.api.dhl.com/mydhlapi/test'
            : 'https://express.api.dhl.com/mydhlapi';
    }

    /**
     * Builds the MyDHL API shipment creation payload.
     */
    protected function buildPayload(Shipment $shipment, $order, $shippingAddress): array
    {
        $weight = (float) ($shipment->total_weight ?: 0.5);

        return [
            'plannedShippingDateAndTime' => now()->addDay()->format('Y-m-d\TH:i:s \G\M\TP'),
            'pickup' => [
                'isRequested' => (bool) core()->getConfigData('sales.carriers.dhl.request_pickup'),
            ],
            'productCode' => 'P',
            'accounts' => [
                [
                    'typeCode' => 'shipper',
                    'number' => core()->getConfigData('sales.carriers.dhl.account_number'),
                ],
            ],
            'customerDetails' => [
                'shipperDetails' => [
                    'postalAddress' => [
                        'postalCode' => core()->getConfigData('sales.carriers.dhl.origin_postal_code'),
                        'cityName' => core()->getConfigData('sales.carriers.dhl.origin_city'),
                        'countryCode' => core()->getConfigData('sales.carriers.dhl.origin_country_code'),
                        'addressLine1' => core()->getConfigData('sales.carriers.dhl.origin_address') ?: core()->getConfigData('sales.carriers.dhl.origin_city'),
                    ],
                    'contactInformation' => [
                        'fullName' => core()->getConfigData('sales.carriers.dhl.origin_contact_name'),
                        'phone' => core()->getConfigData('sales.carriers.dhl.origin_phone'),
                        'email' => core()->getConfigData('sales.carriers.dhl.origin_email'),
                    ],
                    'typeCode' => 'business',
                ],
                'receiverDetails' => [
                    'postalAddress' => [
                        'postalCode' => $shippingAddress->postcode,
                        'cityName' => $shippingAddress->city,
                        'countryCode' => $shippingAddress->country,
                        'addressLine1' => $shippingAddress->address1 ? implode(' ', (array) $shippingAddress->address1) : $shippingAddress->city,
                    ],
                    'contactInformation' => [
                        'fullName' => trim($shippingAddress->first_name.' '.$shippingAddress->last_name),
                        'phone' => $shippingAddress->phone ?: core()->getConfigData('sales.carriers.dhl.origin_phone'),
                        'email' => $order->customer_email,
                    ],
                    'typeCode' => 'private',
                ],
            ],
            'content' => [
                'packages' => [
                    [
                        'weight' => $weight,
                        'dimensions' => [
                            'length' => (float) core()->getConfigData('sales.carriers.dhl.package_length') ?: 20,
                            'width' => (float) core()->getConfigData('sales.carriers.dhl.package_width') ?: 15,
                            'height' => (float) core()->getConfigData('sales.carriers.dhl.package_height') ?: 10,
                        ],
                    ],
                ],
                'isCustomsDeclarable' => false,
                'description' => 'Order #'.$order->increment_id,
                'unitOfMeasurement' => 'metric',
                'incoterm' => 'DAP',
            ],
        ];
    }
}
