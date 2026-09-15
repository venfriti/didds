<?php

namespace Webkul\Shop\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;
use Webkul\Core\Rules\PhoneNumber;
use Webkul\Core\Rules\PostCode;
use Webkul\Core\Rules\ValidState;
use Webkul\Customer\Rules\VatIdRule;
use Webkul\Shipping\Rules\ServiceableCity;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'company_name' => ['nullable'],
            'first_name' => ['required'],
            'last_name' => ['required'],
            'address' => ['required', 'array', 'min:1'],
            'country' => core()->isCountryRequired() ? ['required'] : ['nullable'],
            'state' => array_merge(
                $this->stateRequirement($this->input('country')),
                [(new ValidState)->setCountry($this->input('country'))]
            ),
            'city' => ['required', 'string', (new ServiceableCity)->setCountry($this->input('country'))],
            'postcode' => core()->isPostCodeRequired() ? ['required', new PostCode] : [new PostCode],
            'phone' => ['required', new PhoneNumber],
            'vat_id' => [(new VatIdRule)->setCountry($this->input('country'))],
            'email' => ['required'],
        ];
    }

    /**
     * Attributes.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'address.*' => 'address',
        ];
    }

    /**
     * Whether a state must be supplied for the given country.
     *
     * The address form only shows the state field for countries that have
     * states on record, so demanding one for the rest would reject an
     * address the customer had no way to complete.
     */
    private function stateRequirement(?string $countryCode): array
    {
        if (! core()->isStateRequired()) {
            return ['nullable'];
        }

        $states = core()->groupedStatesByCountries()[strtoupper((string) $countryCode)] ?? [];

        return empty($states) ? ['nullable'] : ['required'];
    }
}
