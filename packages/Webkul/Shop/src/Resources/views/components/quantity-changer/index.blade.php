@props([
    'name'      => '',
    'value'     => 1,
    'minValue'  => 1,
    'maxValue'  => null,
    'removable' => false,
])

@php
    /**
     * Falls back to the store-wide per-item limit so the + button stops
     * where the cart would reject the quantity anyway - a customer should
     * meet the cap as a button that stops, not an error after the fact.
     */
    $resolvedMax = $maxValue ?? core()->getConfigData('catalog.products.settings.max_quantity_per_item');
    $resolvedMax = ($resolvedMax === null || $resolvedMax === '' || (int) $resolvedMax < 1)
        ? null
        : (int) $resolvedMax;
@endphp

<v-quantity-changer
    {{ $attributes->merge(['class' => 'flex items-center border border-navyBlue']) }}
    name="{{ $name }}"
    value="{{ $value }}"
    min-value="{{ $minValue }}"
    :max-value="{{ $resolvedMax !== null ? $resolvedMax : 'null' }}"
    is-removable="{{ $removable ? '1' : '0' }}"
>
</v-quantity-changer>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-quantity-changer-template"
    >
        <div>
            <span
                v-if="isAtMinValue"
                class="icon-bin cursor-pointer text-2xl"
                role="button"
                tabindex="0"
                aria-label="@lang('shop::app.components.quantity-changer.remove-item')"
                @click="remove"
            >
            </span>

            <span
                v-else
                class="icon-minus text-2xl"
                :class="atMinValue ? 'cursor-not-allowed opacity-40' : 'cursor-pointer'"
                role="button"
                tabindex="0"
                :aria-disabled="atMinValue"
                aria-label="@lang('shop::app.components.quantity-changer.decrease-quantity')"
                @click="decrease"
            >
            </span>

            <p class="w-2.5 select-none text-center max-sm:text-sm">
                @{{ quantity }}
            </p>

            <span
                class="icon-plus text-2xl"
                :class="atMax ? 'cursor-not-allowed opacity-40' : 'cursor-pointer'"
                role="button"
                tabindex="0"
                :aria-disabled="atMax"
                aria-label="@lang('shop::app.components.quantity-changer.increase-quantity')"
                @click="increase"
            >
            </span>

            <v-field
                type="hidden"
                :name="name"
                v-model="quantity"
            ></v-field>
        </div>
    </script>

    <script type="module">
        app.component("v-quantity-changer", {
            template: '#v-quantity-changer-template',

            props:['name', 'value', 'minValue', 'maxValue', 'isRemovable'],

            data() {
                return  {
                    quantity: this.value,
                }
            },

            computed: {
                /**
                 * Whether the quantity is at (or below) the minimum and cannot be
                 * decreased further. Used to dim/disable the minus icon.
                 */
                atMinValue() {
                    return Number(this.quantity) <= Number(this.minValue);
                },

                /**
                 * Whether the per-item limit has been reached, so the plus
                 * icon can be dimmed rather than letting the customer click
                 * into an error.
                 */
                atMax() {
                    return this.maxValue != null && Number(this.quantity) >= Number(this.maxValue);
                },

                /**
                 * Whether the trash icon should replace the minus icon. Only when
                 * removal is enabled and the quantity cannot be decreased further.
                 */
                isAtMinValue() {
                    return this.isRemovable == '1' && this.atMinValue;
                },
            },

            watch: {
                value() {
                    this.quantity = this.value;
                },
            },

            methods: {
                increase() {
                    if (this.atMax) {
                        return;
                    }

                    this.$emit('change', ++this.quantity);
                },

                decrease() {
                    if (this.quantity > this.minValue) {
                        this.quantity -= 1;

                        this.$emit('change', this.quantity);
                    }
                },

                remove() {
                    this.$emit('remove');
                },
            }
        });
    </script>
@endpushOnce
