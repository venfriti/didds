<?php

namespace Webkul\Shipping\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Webkul\Shipping\Carriers\DhlShipmentService;

/**
 * Validates that a city is one the shipping carrier actually serves.
 *
 * The checkout and address forms restrict the field to a typeahead of
 * carrier-supplied cities, but that is a client-side convenience: a
 * request can be posted directly, and browser autofill can populate the
 * field without the typeahead ever running. Since the store offers no
 * fallback shipping rate, an unserviceable city means the customer ends
 * up with no shipping option at all, so it has to be rejected at the
 * point the address is saved rather than discovered at checkout.
 */
class ServiceableCity implements ValidationRule
{
    public function __construct(protected ?string $countryCode = null) {}

    /**
     * Set the country the city should be validated against.
     */
    public function setCountry(?string $countryCode): self
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $city = trim((string) $value);

        $country = strtoupper((string) $this->countryCode);

        /**
         * Without a country there is nothing to validate against, and the
         * country field has its own required rule - so defer rather than
         * raising a confusing second error.
         */
        if ($city === '' || ! preg_match('/^[A-Za-z]{2}$/', $country)) {
            return;
        }

        $service = app(DhlShipmentService::class);

        /**
         * If the carrier isn't configured we can't verify anything, and
         * blocking every address save would be worse than allowing one
         * through - the rate lookup will still surface the problem.
         */
        if (! $service->hasCredentials()) {
            return;
        }

        $matches = $service->searchCities($city, $country);

        if (empty($matches)) {
            $fail('shop::app.checkout.onepage.address.city-not-served')->translate();

            return;
        }

        foreach ($matches as $match) {
            if (strcasecmp($match['city'], $city) === 0) {
                return;
            }
        }

        $fail('shop::app.checkout.onepage.address.city-not-served')->translate();
    }
}
