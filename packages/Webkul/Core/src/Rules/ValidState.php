<?php

namespace Webkul\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a state belongs to the country it was submitted with.
 *
 * The address forms present a dropdown of the country's states, but that
 * is a client-side convenience - a request can be posted directly, and a
 * stale page can submit a state left over from a previously selected
 * country. Since the state is passed to the shipping carrier and printed
 * on the waybill, an unrecognised value produces an undeliverable label.
 *
 * Countries with no states on record (Bagisto ships none for much of the
 * world) accept any value, since there is nothing to check against and
 * rejecting everything would block those customers entirely.
 */
class ValidState implements ValidationRule
{
    public function __construct(protected ?string $countryCode = null) {}

    /**
     * Set the country the state should be validated against.
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
        $state = trim((string) $value);

        $country = strtoupper((string) $this->countryCode);

        /**
         * An empty state or missing country is handled by their own rules -
         * raising a second error here would just be confusing.
         */
        if ($state === '' || ! preg_match('/^[A-Za-z]{2}$/', $country)) {
            return;
        }

        $states = core()->groupedStatesByCountries()[$country] ?? [];

        if (empty($states)) {
            return;
        }

        foreach ($states as $countryState) {
            /**
             * groupedStatesByCountries() hands back objects, but the same
             * data is array-shaped elsewhere in the codebase, so accept
             * either rather than depending on which caller we came from.
             */
            $countryState = (array) $countryState;

            $code = $countryState['code'] ?? null;
            $name = $countryState['default_name'] ?? null;

            if (
                strcasecmp((string) $code, $state) === 0
                || strcasecmp((string) $name, $state) === 0
            ) {
                return;
            }
        }

        $fail('shop::app.checkout.onepage.address.invalid-state')->translate();
    }
}
