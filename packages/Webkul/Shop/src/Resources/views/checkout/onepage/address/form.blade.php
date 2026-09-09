@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-checkout-address-form-template"
    >
        <div class="mt-2 max-md:mt-3">
            <x-shop::form.control-group class="hidden">
                <x-shop::form.control-group.control
                    type="text"
                    ::name="controlName + '.id'"
                    ::value="address.id"
                />
            </x-shop::form.control-group>

            <x-shop::form.control-group class="hidden">
                <x-shop::form.control-group.control
                    type="text"
                    ::name="controlName + '.company_name'"
                    ::value="address.company_name"
                />
            </x-shop::form.control-group>

            <!-- First Name -->
            <div class="grid grid-cols-2 gap-x-5 max-md:grid-cols-1">
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="required !mt-0">
                        @lang('shop::app.checkout.onepage.address.first-name')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.first_name'"
                        ::value="address.first_name"
                        rules="required"
                        :label="trans('shop::app.checkout.onepage.address.first-name')"
                        :placeholder="trans('shop::app.checkout.onepage.address.first-name')"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.first_name'" />
                </x-shop::form.control-group>

                {!! view_render_event('bagisto.shop.checkout.onepage.address.form.first_name.after') !!}

                <!-- Last Name -->
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="required !mt-0">
                        @lang('shop::app.checkout.onepage.address.last-name')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.last_name'"
                        ::value="address.last_name"
                        rules="required"
                        :label="trans('shop::app.checkout.onepage.address.last-name')"
                        :placeholder="trans('shop::app.checkout.onepage.address.last-name')"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.last_name'" />
                </x-shop::form.control-group>

                {!! view_render_event('bagisto.shop.checkout.onepage.address.form.last_name.after') !!}
            </div>

            <!-- Email -->
            <x-shop::form.control-group>
                <x-shop::form.control-group.label class="required !mt-0">
                    @lang('shop::app.checkout.onepage.address.email')
                </x-shop::form.control-group.label>

                <x-shop::form.control-group.control
                    type="email"
                    ::name="controlName + '.email'"
                    ::value="address.email"
                    rules="required|email"
                    :label="trans('shop::app.checkout.onepage.address.email')"
                    placeholder="email@example.com"
                />

                <x-shop::form.control-group.error ::name="controlName + '.email'" />
            </x-shop::form.control-group>

            {!! view_render_event('bagisto.shop.checkout.onepage.address.form.email.after') !!}

            <!-- Vat ID -->
            <template v-if="controlName=='billing'">
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label>
                        @lang('shop::app.checkout.onepage.address.vat-id')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.vat_id'"
                        ::value="address.vat_id"
                        :label="trans('shop::app.checkout.onepage.address.vat-id')"
                        :placeholder="trans('shop::app.checkout.onepage.address.vat-id')"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.vat_id'" />
                </x-shop::form.control-group>

                {!! view_render_event('bagisto.shop.checkout.onepage.address.form.vat_id.after') !!}
            </template>

            <!-- Street Address -->
            <x-shop::form.control-group>
                <x-shop::form.control-group.label class="required !mt-0">
                    @lang('shop::app.checkout.onepage.address.street-address')
                </x-shop::form.control-group.label>

                <x-shop::form.control-group.control
                    type="text"
                    ::name="controlName + '.address.[0]'"
                    ::value="address.address[0]"
                    rules="required|address"
                    :label="trans('shop::app.checkout.onepage.address.street-address')"
                    :placeholder="trans('shop::app.checkout.onepage.address.street-address')"
                />

                <x-shop::form.control-group.error
                    class="mb-2"
                    ::name="controlName + '.address.[0]'"
                />

                @if (core()->getConfigData('customer.address.information.street_lines') > 1)
                    @for ($i = 1; $i < core()->getConfigData('customer.address.information.street_lines'); $i++)
                        <x-shop::form.control-group.control
                            type="text"
                            ::name="controlName + '.address.[{{ $i }}]'"
                            rules="address"
                            :label="trans('shop::app.checkout.onepage.address.street-address')"
                            :placeholder="trans('shop::app.checkout.onepage.address.street-address')"
                        />

                        <x-shop::form.control-group.error
                            class="mb-2"
                            ::name="controlName + '.address.[{{ $i }}]'"
                        />
                    @endfor
                @endif
            </x-shop::form.control-group>

            {!! view_render_event('bagisto.shop.checkout.onepage.address.form.address.after') !!}

            <div class="grid grid-cols-2 gap-x-5 max-md:grid-cols-1">
                <!-- Country -->
                <x-shop::form.control-group class="!mb-4">
                    <x-shop::form.control-group.label class="{{ core()->isCountryRequired() ? 'required' : '' }} !mt-0">
                        @lang('shop::app.checkout.onepage.address.country')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="select"
                        ::name="controlName + '.country'"
                        ::value="address.country"
                        v-model="selectedCountry"
                        rules="{{ core()->isCountryRequired() ? 'required' : '' }}"
                        :label="trans('shop::app.checkout.onepage.address.country')"
                        :placeholder="trans('shop::app.checkout.onepage.address.country')"
                    >
                        <option value="">
                            @lang('shop::app.checkout.onepage.address.select-country')
                        </option>

                        <option
                            v-for="country in countries"
                            :value="country.code"
                        >
                            @{{ country.name }}
                        </option>
                    </x-shop::form.control-group.control>

                    <x-shop::form.control-group.error ::name="controlName + '.country'" />
                </x-shop::form.control-group>

                {!! view_render_event('bagisto.shop.checkout.onepage.address.form.country.after') !!}

                <!-- State -->
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="{{ core()->isStateRequired() ? 'required' : '' }} !mt-0">
                        @lang('shop::app.checkout.onepage.address.state')
                    </x-shop::form.control-group.label>

                    <template v-if="states">
                        <template v-if="haveStates">
                            <x-shop::form.control-group.control
                                type="select"
                                ::name="controlName + '.state'"
                                rules="{{ core()->isStateRequired() ? 'required' : '' }}"
                                ::value="address.state"
                                :label="trans('shop::app.checkout.onepage.address.state')"
                                :placeholder="trans('shop::app.checkout.onepage.address.state')"
                            >
                                <option value="">
                                    @lang('shop::app.checkout.onepage.address.select-state')
                                </option>

                                <option
                                    v-for='(state, index) in states[selectedCountry]'
                                    :value="state.code"
                                >
                                    @{{ state.default_name }}
                                </option>
                            </x-shop::form.control-group.control>
                        </template>

                        <template v-else>
                            <x-shop::form.control-group.control
                                type="text"
                                ::name="controlName + '.state'"
                                ::value="address.state"
                                rules="{{ core()->isStateRequired() ? 'required' : '' }}"
                                :label="trans('shop::app.checkout.onepage.address.state')"
                                :placeholder="trans('shop::app.checkout.onepage.address.state')"
                            />
                        </template>
                    </template>

                    <x-shop::form.control-group.error ::name="controlName + '.state'" />
                </x-shop::form.control-group>

                {!! view_render_event('bagisto.shop.checkout.onepage.address.form.state.after') !!}
            </div>

            <div class="grid grid-cols-2 gap-x-5 max-md:grid-cols-1">
                <!-- City -->
                <x-shop::form.control-group class="relative">
                    <x-shop::form.control-group.label class="required !mt-0">
                        @lang('shop::app.checkout.onepage.address.city')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.city'"
                        v-model="cityQuery"
                        rules="required"
                        autocomplete="off"
                        :label="trans('shop::app.checkout.onepage.address.city')"
                        :placeholder="trans('shop::app.checkout.onepage.address.city')"
                        @input="onCityInput"
                        @focus="onCityFocus"
                        @blur="onCityBlur"
                    />

                    <!--
                        Cities are restricted to those the carrier actually
                        delivers to, so a customer can't save an address that
                        would later leave them with no shipping option.
                    -->
                    <ul
                        class="absolute z-10 mt-1 max-h-60 w-full overflow-y-auto rounded border border-zinc-200 bg-white shadow-lg dark:border-gray-800 dark:bg-gray-900"
                        v-if="showCitySuggestions && citySuggestions.length"
                    >
                        <li
                            class="cursor-pointer px-4 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-gray-800"
                            v-for="suggestion in citySuggestions"
                            :key="suggestion.city"
                            @mousedown.prevent="selectCity(suggestion)"
                        >
                            @{{ suggestion.city }}
                        </li>
                    </ul>

                    <p
                        class="mt-1 text-xs text-zinc-500"
                        v-if="isSearchingCities"
                    >
                        @lang('shop::app.checkout.onepage.address.searching-cities')
                    </p>

                    <p
                        class="mt-1 text-xs text-red-600"
                        v-else-if="cityNotFound"
                    >
                        @lang('shop::app.checkout.onepage.address.city-not-served')
                    </p>

                    <x-shop::form.control-group.error ::name="controlName + '.city'" />
                </x-shop::form.control-group>

                {!! view_render_event('bagisto.shop.checkout.onepage.address.form.city.after') !!}

                <!-- Postcode -->
                <x-shop::form.control-group>
                    <x-shop::form.control-group.label class="{{ core()->isPostCodeRequired() ? 'required' : '' }} !mt-0">
                        @lang('shop::app.checkout.onepage.address.postcode')
                    </x-shop::form.control-group.label>

                    <x-shop::form.control-group.control
                        type="text"
                        ::name="controlName + '.postcode'"
                        ::value="address.postcode"
                        rules="{{ core()->isPostCodeRequired() ? 'required' : '' }}|postcode"
                        :label="trans('shop::app.checkout.onepage.address.postcode')"
                        :placeholder="trans('shop::app.checkout.onepage.address.postcode')"
                    />

                    <x-shop::form.control-group.error ::name="controlName + '.postcode'" />
                </x-shop::form.control-group>

                {!! view_render_event('bagisto.shop.checkout.onepage.address.form.postcode.after') !!}
            </div>

            <!-- Phone Number -->
            <x-shop::form.control-group>
                <x-shop::form.control-group.label class="required !mt-0">
                    @lang('shop::app.checkout.onepage.address.telephone')
                </x-shop::form.control-group.label>

                <x-shop::form.control-group.control
                    type="text"
                    ::name="controlName + '.phone'"
                    ::value="address.phone"
                    rules="required|phone"
                    :label="trans('shop::app.checkout.onepage.address.telephone')"
                    :placeholder="trans('shop::app.checkout.onepage.address.telephone')"
                />

                <x-shop::form.control-group.error ::name="controlName + '.phone'" />
            </x-shop::form.control-group>

            {!! view_render_event('bagisto.shop.checkout.onepage.address.form.phone.after') !!}
        </div>
    </script>

    <script type="module">
        app.component('v-checkout-address-form', {
            template: '#v-checkout-address-form-template',

            props: {
                controlName: {
                    type: String,
                    required: true,
                },

                address: {
                    type: Object,

                    default: () => ({
                        id: 0,
                        company_name: '',
                        first_name: '',
                        last_name: '',
                        email: '',
                        address: [],
                        country: '',
                        state: '',
                        city: '',
                        postcode: '',
                        phone: '',
                    }),
                },
            },

            data() {
                return {
                    selectedCountry: this.address.country,

                    countries: [],

                    states: null,

                    cityQuery: this.address.city ?? '',

                    citySuggestions: [],

                    showCitySuggestions: false,

                    isSearchingCities: false,

                    cityNotFound: false,

                    citySearchTimeout: null,
                }
            },

            computed: {
                haveStates() {
                    return !! this.states[this.selectedCountry]?.length;
                },
            },

            watch: {
                /**
                 * A city is only valid for the country it was chosen from,
                 * so switching country has to clear it rather than leave a
                 * stale value the carrier would reject.
                 */
                selectedCountry() {
                    this.cityQuery = '';

                    this.citySuggestions = [];

                    this.cityNotFound = false;
                },
            },

            mounted() {
                this.getCountries();

                this.getStates();
            },

            methods: {
                getCountries() {
                    this.$axios.get("{{ route('shop.api.core.countries') }}")
                        .then(response => {
                            this.countries = response.data.data;
                        })
                        .catch(() => {});
                },

                getStates() {
                    this.$axios.get("{{ route('shop.api.core.states') }}")
                        .then(response => {
                            this.states = response.data.data;
                        })
                        .catch(() => {});
                },

                onCityInput() {
                    this.cityNotFound = false;

                    this.showCitySuggestions = true;

                    clearTimeout(this.citySearchTimeout);

                    if (! this.selectedCountry || this.cityQuery.trim().length < 2) {
                        this.citySuggestions = [];

                        return;
                    }

                    // Debounced so we aren't calling the carrier on every keystroke.
                    this.citySearchTimeout = setTimeout(() => this.searchCities(), 300);
                },

                searchCities() {
                    this.isSearchingCities = true;

                    this.$axios.get("{{ route('shop.api.core.cities') }}", {
                            params: {
                                query: this.cityQuery.trim(),
                                country: this.selectedCountry,
                            },
                        })
                        .then(response => {
                            this.citySuggestions = response.data.data;

                            this.cityNotFound = ! this.citySuggestions.length;
                        })
                        .catch(() => {
                            this.citySuggestions = [];

                            this.cityNotFound = true;
                        })
                        .finally(() => {
                            this.isSearchingCities = false;
                        });
                },

                selectCity(suggestion) {
                    this.cityQuery = suggestion.city;

                    this.citySuggestions = [];

                    this.showCitySuggestions = false;

                    this.cityNotFound = false;
                },

                onCityFocus() {
                    if (this.citySuggestions.length) {
                        this.showCitySuggestions = true;
                    }
                },

                onCityBlur() {
                    // Delay so a click on a suggestion registers first.
                    setTimeout(() => {
                        this.showCitySuggestions = false;
                    }, 150);
                },
            }
        });
    </script>
@endPushOnce
