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
     * Queries DHL's Tracking API for the current status of a shipment.
     * Returns the raw status/description pair, or null if unavailable.
     * Never throws - the sync job must keep going even if one shipment's
     * lookup fails (DHL down, tracking number not yet scanned, etc).
     */
    public function getTrackingStatus(string $trackingNumber): ?array
    {
        if (
            ! core()->getConfigData('sales.carriers.dhl.api_key')
            || ! core()->getConfigData('sales.carriers.dhl.api_secret')
        ) {
            return null;
        }

        try {
            $response = Http::withBasicAuth(
                core()->getConfigData('sales.carriers.dhl.api_key'),
                core()->getConfigData('sales.carriers.dhl.api_secret')
            )
                ->withHeaders(['x-version' => $this->apiVersion])
                ->timeout(15)
                ->get($this->getBaseUrl().'/shipments/'.$trackingNumber.'/tracking', [
                    'trackingView' => 'last-checkpoint',
                ]);

            if (! $response->successful()) {
                Log::warning('DHL tracking lookup failed', [
                    'tracking_number' => $trackingNumber,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $shipments = $response->json('shipments', []);

            if (empty($shipments)) {
                return null;
            }

            $status = $shipments[0]['status'] ?? null;

            if (! $status) {
                return null;
            }

            return [
                'status_code' => $status['statusCode'] ?? null,
                'status' => $status['status'] ?? null,
                'description' => $status['description'] ?? null,
                'timestamp' => $status['timestamp'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('DHL tracking lookup exception: '.$e->getMessage(), [
                'tracking_number' => $trackingNumber,
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
     * MyDHL API enforces a 45 character limit on each address line and
     * rejects the whole request if it's exceeded, so long street addresses
     * have to be trimmed rather than passed through verbatim.
     */
    protected function truncateAddressLine(?string $line): string
    {
        return mb_substr(trim((string) $line), 0, 45);
    }

    /**
     * DHL sells domestic and international movements as different products,
     * and booking the wrong one is rejected outright ("Requested product(s)
     * not available at payer"). Express Worldwide ('P') only covers
     * cross-border shipments, so anything staying inside the origin country
     * has to go out as Express Domestic ('N') instead.
     */
    protected function resolveProductCode($shippingAddress): string
    {
        $originCountry = strtoupper((string) core()->getConfigData('sales.carriers.dhl.origin_country_code'));

        $destinationCountry = strtoupper((string) $shippingAddress->country);

        return $originCountry === $destinationCountry ? 'N' : 'P';
    }

    /**
     * Builds the MyDHL API shipment creation payload.
     */
    protected function buildPayload(Shipment $shipment, $order, $shippingAddress): array
    {
        $weight = (float) ($shipment->total_weight ?: 0.5);

        /**
         * Anything crossing a border needs a customs declaration; purely
         * domestic movements must NOT have one (DHL rejects declarations
         * on domestic products).
         */
        $isCustomsDeclarable = strtoupper((string) core()->getConfigData('sales.carriers.dhl.origin_country_code'))
            !== strtoupper((string) $shippingAddress->country);

        $exportDeclaration = $isCustomsDeclarable
            ? $this->buildExportDeclaration($shipment, $order, $shippingAddress)
            : null;

        return [
            'plannedShippingDateAndTime' => now()->addDay()->format('Y-m-d\TH:i:s \G\M\TP'),
            /**
             * DHL's integration guide is explicit that isRequested must
             * always be false here - pickups are booked through the separate
             * Pickup API, not as a side effect of creating a shipment.
             */
            'pickup' => [
                'isRequested' => false,
            ],
            'productCode' => $this->resolveProductCode($shippingAddress),
            'outputImageProperties' => $this->buildOutputImageProperties($isCustomsDeclarable),
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
                        'addressLine1' => $this->truncateAddressLine(
                            core()->getConfigData('sales.carriers.dhl.origin_address') ?: core()->getConfigData('sales.carriers.dhl.origin_city')
                        ),
                    ],
                    'contactInformation' => [
                        'companyName' => config('app.name'),
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
                        'addressLine1' => $this->truncateAddressLine(
                            $shippingAddress->address1 ? implode(' ', (array) $shippingAddress->address1) : $shippingAddress->city
                        ),
                    ],
                    'contactInformation' => [
                        /**
                         * MyDHL API requires companyName on both parties. Most
                         * customers are individuals, so fall back to their own
                         * name rather than sending an empty string (which the
                         * API rejects).
                         */
                        'companyName' => $shippingAddress->company_name
                            ?: trim($shippingAddress->first_name.' '.$shippingAddress->last_name),
                        'fullName' => trim($shippingAddress->first_name.' '.$shippingAddress->last_name),
                        'phone' => $shippingAddress->phone ?: core()->getConfigData('sales.carriers.dhl.origin_phone'),
                        'email' => $order->customer_email,
                    ],
                    'typeCode' => 'private',
                ],
            ],
            'content' => array_filter([
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
                'isCustomsDeclarable' => $isCustomsDeclarable,
                'description' => mb_substr('Order #'.$order->increment_id, 0, 70),
                'unitOfMeasurement' => 'metric',
                'incoterm' => 'DAP',
                /**
                 * DHL requires declaredValue to equal the sum of the export
                 * declaration's line item prices, so it's derived from the
                 * same declaration rather than from the order total (which
                 * also carries shipping and tax and would not reconcile).
                 * Domestic shipments must omit all three fields entirely.
                 */
                'declaredValue' => $exportDeclaration
                    ? $this->sumDeclaredValue($exportDeclaration)
                    : null,
                'declaredValueCurrency' => $exportDeclaration
                    ? $this->accountCurrencyCode()
                    : null,
                'exportDeclaration' => $exportDeclaration,
            ], fn ($value) => ! is_null($value)),
        ];
    }

    /**
     * DHL returns the label/waybill paperwork as PDFs, and the templates
     * requested here determine what comes back. Per DHL's integration guide
     * domestic shipments take two templates (label + waybill doc), while
     * international ones additionally need the Commercial Invoice, without
     * which the package has no customs paperwork attached.
     */
    protected function buildOutputImageProperties(bool $isCustomsDeclarable): array
    {
        $imageOptions = [
            [
                'templateName' => 'ECOM26_84_A4_001',
                'typeCode' => 'label',
            ],
            [
                'templateName' => 'ARCH_8X4_A4_002',
                'isRequested' => true,
                'typeCode' => 'waybillDoc',
                'hideAccountNumber' => true,
            ],
        ];

        if ($isCustomsDeclarable) {
            $imageOptions[] = [
                'templateName' => 'COMMERCIAL_INVOICE_P_10',
                'invoiceType' => 'commercial',
                'languageCode' => 'eng',
                'isRequested' => true,
                'typeCode' => 'invoice',
            ];
        }

        return [
            'allDocumentsInOneImage' => true,
            'encodingFormat' => 'pdf',
            'imageOptions' => $imageOptions,
        ];
    }

    /**
     * declaredValue must reconcile exactly with the line items DHL is given,
     * otherwise the shipment is held at customs for a value mismatch.
     */
    protected function sumDeclaredValue(array $exportDeclaration): float
    {
        $total = 0.0;

        foreach ($exportDeclaration['lineItems'] ?? [] as $lineItem) {
            $total += (float) $lineItem['price'] * (int) $lineItem['quantity']['value'];
        }

        return max(0.01, round($total, 2));
    }

    /**
     * Converts a store base-currency amount into the DHL account's currency,
     * so line item prices and declaredValue are stated consistently. Falls
     * back to the original amount when no exchange rate is configured, which
     * is correct when the two currencies already match.
     */
    protected function toAccountCurrency(float $amount): float
    {
        $target = $this->accountCurrencyCode();

        if ($target === core()->getBaseCurrencyCode()) {
            return max(0.01, round($amount, 2));
        }

        return max(0.01, round((float) core()->convertPrice($amount, $target), 2));
    }

    /**
     * DHL expects the declared value in the currency of the DHL account's
     * own country, not the storefront's display or base currency.
     */
    protected function accountCurrencyCode(): string
    {
        $currencyByCountry = [
            'NG' => 'NGN',
            'GH' => 'GHS',
            'GB' => 'GBP',
            'US' => 'USD',
            'ZA' => 'ZAR',
            'KE' => 'KES',
        ];

        $country = strtoupper((string) core()->getConfigData('sales.carriers.dhl.origin_country_code'));

        return $currencyByCountry[$country] ?? 'USD';
    }

    /**
     * Cross-border shipments must be accompanied by a customs declaration
     * listing what's actually in the box. DHL rejects the shipment outright
     * if this is missing on an international movement.
     */
    protected function buildExportDeclaration(Shipment $shipment, $order, $shippingAddress): array
    {
        $originCountry = core()->getConfigData('sales.carriers.dhl.origin_country_code') ?: 'NG';

        $lineItems = [];

        $number = 0;

        foreach ($shipment->items as $item) {
            $quantity = (int) ($item->qty ?: 1);

            /**
             * DHL wants the unit price, not the line total, and rejects a
             * declared value of zero - so free/promo items still need a
             * nominal customs value. Prices are converted into the DHL
             * account's own currency to match declaredValueCurrency, since
             * the two have to reconcile.
             */
            $unitPrice = $this->toAccountCurrency((float) ($item->price ?: 0.01));

            $unitWeight = (float) $item->weight ?: 0.1;

            $lineItems[] = [
                'number' => ++$number,
                'description' => $this->buildCustomsDescription($item, $originCountry),
                'price' => round($unitPrice, 2),
                'quantity' => [
                    'value' => $quantity,
                    'unitOfMeasurement' => 'PCS',
                ],
                'commodityCodes' => $this->buildCommodityCodes($item),
                'exportReasonType' => 'permanent',
                'manufacturerCountry' => $originCountry,
                'weight' => [
                    'netValue' => max(0.01, round($unitWeight * $quantity, 2)),
                    'grossValue' => max(0.01, round($unitWeight * $quantity, 2)),
                ],
            ];
        }

        if (empty($lineItems)) {
            $lineItems[] = [
                'number' => 1,
                'description' => 'Cotton knitted apparel for adults made in '.$originCountry,
                'price' => $this->toAccountCurrency((float) $order->base_grand_total),
                'quantity' => [
                    'value' => 1,
                    'unitOfMeasurement' => 'PCS',
                ],
                'commodityCodes' => $this->buildCommodityCodes(null),
                'exportReasonType' => 'permanent',
                'manufacturerCountry' => $originCountry,
                'weight' => [
                    'netValue' => 0.5,
                    'grossValue' => 0.5,
                ],
            ];
        }

        return [
            'lineItems' => $lineItems,
            'invoice' => [
                'number' => (string) $order->increment_id,
                'date' => now()->format('Y-m-d'),
            ],
            'exportReason' => 'Permanent',
            'exportReasonType' => 'permanent',
            'shipmentType' => 'commercial',
            /**
             * placeOfIncoterm is the destination city under DAP terms, not
             * the origin - DHL's own samples use the receiving city here.
             */
            'placeOfIncoterm' => mb_substr((string) $shippingAddress->city, 0, 45),
        ];
    }

    /**
     * Customs rejects vague descriptions like "Socks". DHL's guidance is to
     * state material, item, who it's for and where it was made, so the
     * product name is expanded into something a customs officer can clear.
     */
    protected function buildCustomsDescription($item, string $originCountry): string
    {
        $name = trim((string) $item->name) ?: 'Apparel';

        return mb_substr($name.' - cotton knitted apparel for adults, made in '.$originCountry, 0, 200);
    }

    /**
     * Every line item must carry an HS code or the shipment risks being held
     * or damaged at the destination customs office. DHL does not issue these
     * codes - they come from customs tariff schedules - so the catalogue's
     * two categories are mapped by hand:
     *
     *   6109.10 - knitted cotton t-shirts, singlets and vests (tanks, bras)
     *   6115.95 - knitted cotton hosiery (socks)
     *
     * Both inbound and outbound codes are sent, as DHL's samples do.
     */
    protected function buildCommodityCodes($item): array
    {
        $code = '610910';

        $haystack = strtolower(($item->name ?? '').' '.($item->sku ?? ''));

        if (str_contains($haystack, 'sock')) {
            $code = '611595';
        }

        return [
            ['typeCode' => 'outbound', 'value' => $code],
            ['typeCode' => 'inbound', 'value' => $code],
        ];
    }
}
