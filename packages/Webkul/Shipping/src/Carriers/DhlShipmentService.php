<?php

namespace Webkul\Shipping\Carriers;

use Illuminate\Support\Facades\Cache;
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

            $shipment = $shipments[0];

            /**
             * The shipment's own "status" field is the API call result
             * ("Success"), NOT the delivery state - reading it as the
             * delivery status means never detecting a delivery. Progress is
             * reported as checkpoints under "events", newest first when
             * requesting the last checkpoint.
             */
            $event = $shipment['events'][0] ?? null;

            if (! $event) {
                /**
                 * A freshly created waybill has no checkpoints until the
                 * parcel is actually scanned, which is normal rather than a
                 * failure.
                 */
                return null;
            }

            $timestamp = trim(($event['date'] ?? '').' '.($event['time'] ?? '')) ?: null;

            return [
                'status_code' => $event['typeCode'] ?? null,
                'status' => $event['statusCode'] ?? ($event['typeCode'] ?? null),
                'description' => $event['description'] ?? null,
                'timestamp' => $timestamp,
            ];
        } catch (\Throwable $e) {
            Log::warning('DHL tracking lookup exception: '.$e->getMessage(), [
                'tracking_number' => $trackingNumber,
            ]);

            return null;
        }
    }

    /**
     * Looks up cities DHL actually serves, matching on a name prefix. Used
     * to drive the checkout city typeahead so a customer can only ever save
     * a destination DHL can price and deliver to - previously they could
     * type anything ("Okoko, Ojo") and end up with no shipping option at
     * all.
     *
     * Results are cached because the underlying data is effectively static
     * and the same prefixes get typed constantly.
     *
     * @return array<int, array{city: string, service_area: string|null}>
     */
    public function searchCities(string $query, string $countryCode): array
    {
        $query = trim($query);

        if (
            mb_strlen($query) < 2
            || ! preg_match('/^[A-Za-z]{2}$/', $countryCode)
        ) {
            return [];
        }

        $cacheKey = 'dhl:cities:'.strtoupper($countryCode).':'.strtolower($query);

        return Cache::remember($cacheKey, now()->addDay(), function () use ($query, $countryCode) {
            if (! $this->hasCredentials()) {
                return [];
            }

            try {
                $response = Http::withBasicAuth(
                    core()->getConfigData('sales.carriers.dhl.api_key'),
                    core()->getConfigData('sales.carriers.dhl.api_secret')
                )
                    ->withHeaders(['x-version' => $this->apiVersion])
                    ->timeout(8)
                    ->get($this->getBaseUrl().'/address-validate', [
                        'type' => 'delivery',
                        'countryCode' => strtoupper($countryCode),
                        'cityName' => $query,
                        'strictValidation' => 'false',
                    ]);

                if (! $response->successful()) {
                    /**
                     * DHL answers an unmatched prefix with a 400, which is a
                     * normal "no results" here rather than a fault.
                     */
                    return [];
                }

                $cities = [];

                foreach ($response->json('address', []) as $address) {
                    $city = $address['cityName'] ?? null;

                    if (! $city) {
                        continue;
                    }

                    $cities[$city] = [
                        'city' => $city,
                        'service_area' => $address['serviceArea']['code'] ?? null,
                    ];
                }

                return array_values($cities);
            } catch (\Throwable $e) {
                Log::warning('DHL city lookup failed: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * Whether the DHL API credentials are present. Distinct from
     * isConfigured(), which also demands the full origin address needed to
     * actually book a shipment - a city lookup only needs to authenticate.
     */
    public function hasCredentials(): bool
    {
        return (bool) (
            core()->getConfigData('sales.carriers.dhl.api_key')
            && core()->getConfigData('sales.carriers.dhl.api_secret')
        );
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
     * The city name DHL will accept for delivery. Shipment creation is a
     * single non-repeatable call that mints a real waybill, so unlike the
     * rates lookup it can't retry through candidates - the destination has
     * to be settled before the call is made.
     *
     * The customer's own locality is always preferred: DHL's gazetteer
     * carries small towns like OKPELLA, and widening to the state sends the
     * parcel toward the wrong place entirely when the two don't line up.
     * The state is only a last resort for a locality DHL doesn't know.
     */
    protected function resolveDeliveryCity($shippingAddress): string
    {
        $country = strtoupper(trim((string) $shippingAddress->country));

        foreach ($this->deliveryCityCandidates($shippingAddress) as $candidate) {
            if ($this->isServiceableCity($candidate, $country)) {
                return mb_substr($candidate, 0, 45);
            }
        }

        /**
         * Nothing matched the gazetteer - most likely the lookup itself is
         * unavailable rather than every name being wrong. Send the
         * customer's own locality, which is the accurate one; a rejected
         * city surfaces as a shipment error we can act on, whereas a
         * silently substituted state produces a valid waybill routed to the
         * wrong region.
         */
        $city = trim((string) $shippingAddress->city);

        if ($city !== '') {
            return mb_substr(str_contains($city, ',') ? trim(explode(',', $city)[0]) : $city, 0, 45);
        }

        return mb_substr(trim((string) $shippingAddress->state), 0, 45);
    }

    /**
     * Destination names to try for a waybill, most precise first, so the
     * parcel is addressed to where the customer actually is.
     *
     * @return array<int, string>
     */
    protected function deliveryCityCandidates($shippingAddress): array
    {
        $candidates = [];

        $city = trim((string) $shippingAddress->city);

        if ($city !== '') {
            $candidates[] = $city;

            /**
             * "Okoko, Ojo" style entries: the leading segment is the
             * specific locality and is what DHL is most likely to know.
             */
            if (str_contains($city, ',')) {
                foreach (explode(',', $city) as $part) {
                    if (($part = trim($part)) !== '') {
                        $candidates[] = $part;
                    }
                }
            }
        }

        $state = trim((string) $shippingAddress->state);

        if ($state !== '') {
            $candidates[] = $state;
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * Whether DHL's gazetteer lists this exact city for the country. Reuses
     * the cached city search, so a repeat destination costs no API call.
     */
    protected function isServiceableCity(string $city, string $countryCode): bool
    {
        if (mb_strlen($city) < 2 || ! preg_match('/^[A-Za-z]{2}$/', $countryCode)) {
            return false;
        }

        foreach ($this->searchCities($city, $countryCode) as $match) {
            if (strcasecmp(trim($match['city']), $city) === 0) {
                return true;
            }
        }

        return false;
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
            'plannedShippingDateAndTime' => $this->nextPickupDate()->format('Y-m-d\TH:i:s \G\M\TP'),
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
                    'postalAddress' => array_filter([
                        'postalCode' => $shippingAddress->postcode,
                        /**
                         * DHL validates cityName against its own gazetteer
                         * and rejects unrecognised neighbourhood names like
                         * "Okoko, Ojo", so the broader state is sent when
                         * one is available. The customer's own wording is
                         * preserved on the address lines below, which is
                         * what the courier actually delivers against.
                         */
                        'cityName' => $this->resolveDeliveryCity($shippingAddress),
                        'countryCode' => $shippingAddress->country,
                        'addressLine1' => $this->truncateAddressLine(
                            $shippingAddress->address1 ? implode(' ', (array) $shippingAddress->address1) : $shippingAddress->city
                        ),
                        'addressLine2' => $this->truncateAddressLine($shippingAddress->city),
                    ], fn ($value) => ! in_array($value, [null, ''], true)),
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
                'description' => $this->buildContentDescription($order),
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

            $unitWeight = (float) $item->weight ?: 0.1;

            /**
             * A set is declared as its components, not as one line.
             *
             * "The Match: Tank + Pant" is a singlet and a pair of briefs,
             * which fall under different headings - declaring the pair
             * under the singlet's code describes half the parcel wrongly,
             * and that is the conversation to avoid if a parcel is opened.
             * The components carry their own SKUs and prices, so the split
             * reconciles to the same total.
             */
            $components = $this->declarableComponents($item);

            foreach ($components as $component) {
                /**
                 * DHL wants the unit price, not the line total, and rejects
                 * a declared value of zero - so free/promo items still need
                 * a nominal customs value. Prices are converted into the
                 * DHL account's own currency to match
                 * declaredValueCurrency, since the two have to reconcile.
                 */
                $unitPrice = $this->toAccountCurrency((float) ($component['price'] ?: 0.01));

                $lineQuantity = $quantity * $component['quantity'];
                $lineWeight = $component['weight'] ?: $unitWeight;

                $lineItems[] = [
                    'number' => ++$number,
                    'description' => $this->buildCustomsDescription($component['item'], $originCountry),
                    'price' => round($unitPrice, 2),
                    'quantity' => [
                        'value' => max(1, $lineQuantity),
                        'unitOfMeasurement' => 'PCS',
                    ],
                    'commodityCodes' => $this->buildCommodityCodes($component['item']),
                    'exportReasonType' => 'permanent',
                    'manufacturerCountry' => $originCountry,
                    'weight' => [
                        'netValue' => max(0.01, round($lineWeight * max(1, $lineQuantity), 2)),
                        'grossValue' => max(0.01, round($lineWeight * max(1, $lineQuantity), 2)),
                    ],
                ];
            }
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
            'placeOfIncoterm' => $this->resolveDeliveryCity($shippingAddress),
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
     * The goods a shipment line actually contains, for customs.
     *
     * A simple product is itself. A bundle or configurable is its child
     * rows, which carry their own SKUs, prices and weights - so a set is
     * declared as the garments inside it rather than under a single code
     * that describes only part of the parcel.
     *
     * Falls back to the line itself whenever children are missing or price
     * to nothing, so a declaration is never emptied by this.
     *
     * @return array<int, array{item: mixed, price: float, quantity: int, weight: float}>
     */
    protected function declarableComponents($shipmentItem): array
    {
        $orderItem = $shipmentItem->order_item ?? null;

        $line = [[
            'item' => $shipmentItem,
            /**
             * Base price, not price: price is in whatever currency the
             * customer browsed in, while toAccountCurrency() expects the
             * base - the same source the bundle children below use.
             */
            'price' => (float) ($shipmentItem->base_price ?: $shipmentItem->price ?: 0),
            'quantity' => 1,
            'weight' => (float) ($shipmentItem->weight ?: 0),
        ]];

        if (! $orderItem) {
            return $line;
        }

        $children = $orderItem->children ?? null;

        if (! $children || $children->isEmpty()) {
            return $line;
        }

        $components = [];

        foreach ($children as $child) {
            $price = (float) ($child->base_price ?: 0);

            if ($price <= 0) {
                continue;
            }

            $components[] = [
                'item' => $child,
                'price' => $price,
                'quantity' => max(1, (int) ($child->qty_ordered ?: 1)),
                'weight' => (float) ($child->weight ?: 0),
            ];
        }

        /**
         * Configurable products also have a child row, but it is the same
         * garment in a chosen size - one line, not two - so a single
         * component collapses back to the parent line rather than
         * duplicating it.
         */
        if (count($components) < 2) {
            return $line;
        }

        return $components;
    }

    /**
     * What the package contains, for the "Content" line on the waybill.
     *
     * DHL's review of our demo labels flagged that an order number tells a
     * handler or a customs officer nothing about what is in the box. This
     * lists the actual goods instead, most significant first, and falls
     * back to a generic description only when an order somehow has no
     * nameable items.
     */
    protected function buildContentDescription($order): string
    {
        $names = [];

        foreach ($order->items as $item) {
            /**
             * Child rows of a bundle or configurable repeat their parent's
             * goods, so only top-level lines are named.
             */
            if ($item->parent_id) {
                continue;
            }

            $name = trim((string) $item->name);

            if ($name === '') {
                continue;
            }

            $quantity = (int) ($item->qty_ordered ?? 1);

            $names[] = $quantity > 1 ? $quantity.' x '.$name : $name;
        }

        if (empty($names)) {
            return 'Cotton knitted apparel';
        }

        $description = implode(', ', $names);

        /**
         * The field is capped at 70 characters. A long order is summarised
         * rather than cut mid-word, so the line always reads as a sentence.
         */
        if (mb_strlen($description) <= 70) {
            return $description;
        }

        $first = $names[0];
        $others = count($names) - 1;
        $summary = mb_substr($first, 0, 50).' + '.$others.' more item'.($others > 1 ? 's' : '');

        return mb_substr($summary, 0, 70);
    }

    /**
     * The HS commodity code for a line item.
     *
     * DHL's review flagged our original 6-digit codes as family-level: a
     * 6-digit HS subheading is the internationally common part, and the
     * 8-digit code carries the national detail customs actually assesses
     * duty against. These are the 8-digit CN codes for cotton knitted
     * underwear, which is what the whole catalogue is.
     *
     * The split that matters at 8 digits is garment type and, for briefs
     * and slips, whether the garment is men's or women's - so the product
     * name is matched before falling back to the general women's code.
     *
     * Pending confirmation by a customs broker; see the note in the
     * integration handover.
     */
    protected function buildCommodityCodes($item): array
    {
        /**
         * Matched on the product name only. SKUs carry family prefixes -
         * the Tank is DIIDS-BRA-002 - so including the SKU classified a
         * singlet as a brassiere.
         */
        $name = strtolower(trim((string) ($item->name ?? '')));

        /**
         * Ordered most specific first: "boxer" before the general brief
         * rule, "tank" before "bra", and socks sit outside 6109 entirely.
         * A set takes the code of its most heavily dutied component, which
         * is how a mixed consignment is normally declared.
         */
        $code = match (true) {
            str_contains($name, 'sock') => '61159500',    // socks, cotton, knitted
            str_contains($name, 'tank') => '61091000',    // t-shirts & singlets, cotton
            str_contains($name, 'boxer') => '61071100',   // men's underpants & briefs, cotton
            str_contains($name, 'bra') => '62121090',     // brassieres
            str_contains($name, 'pant')
                || str_contains($name, 'panty')
                || str_contains($name, 'brief') => '61082100',   // women's briefs, cotton
            default => '61091000',                         // cotton knitted apparel
        };

        return [
            ['typeCode' => 'outbound', 'value' => $code],
            ['typeCode' => 'inbound', 'value' => $code],
        ];
    }


    /**
     * The next day DHL will actually collect.
     *
     * The carrier asked for "tomorrow", which on a Friday or Saturday is a
     * weekend - DHL does not collect domestically then and answers /rates
     * with 404 "product(s) not available for the requested pickup date".
     * The effect was that checkout offered no shipping at all, and since
     * DHL is the only method, nobody could buy over a weekend.
     */
    protected function nextPickupDate(): \Carbon\Carbon
    {
        $date = now()->addDay();

        while ($date->isWeekend()) {
            $date->addDay();
        }

        return $date;
    }
}
