<?php

namespace Webkul\Core\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Sanity-checks the state on an address.
 *
 * The state is a records-and-display field: it appears on invoices, order
 * emails and the address book, but the shipping carrier is given the city,
 * not the state, so a state we don't recognise cannot misroute a parcel.
 *
 * Our state lists are therefore treated as suggestions rather than a
 * closed set. They are incomplete (Bagisto ships none for most of the
 * world), states get created and renamed, and customers know their own
 * address better than our seed data does - so rejecting an unlisted value
 * would strand real customers at checkout for no delivery benefit. Only
 * obvious junk is refused.
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

        /**
         * An empty state is handled by its own required/nullable rule -
         * raising a second error here would just be confusing.
         */
        if ($state === '') {
            return;
        }

        if (mb_strlen($state) > 60) {
            $fail('shop::app.checkout.onepage.address.invalid-state')->translate();

            return;
        }

        /**
         * Place names worldwide carry accents, apostrophes, hyphens, full
         * stops and spaces ("Murang'a", "Elgeyo-Marakwet", "Washington,
         * D.C."), so the check is only that it reads like a name at all -
         * it must contain a letter and no control or markup characters.
         */
        if (! preg_match('/\pL/u', $state) || preg_match('/[<>{}\\/|]/u', $state)) {
            $fail('shop::app.checkout.onepage.address.invalid-state')->translate();
        }
    }
}
