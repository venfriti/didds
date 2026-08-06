{!! view_render_event('bagisto.shop.checkout.onepage.payment_methods.before') !!}

<div class="diids-hygiene-notice mb-5 rounded-lg border border-dashed p-4 text-sm" style="border-color: var(--diids-border); background: var(--diids-surface); color: var(--diids-ink);">
    <strong>Please note:</strong> for health and hygiene reasons, opened underwear items cannot be returned or refunded. Damaged, defective, or incorrect items are always eligible for exchange or refund &mdash;
    <a href="{{ route('shop.cms.page', 'return-policy') }}" class="underline" target="_blank" rel="noopener">see our full return policy</a>.
</div>

<v-payment-methods
    :methods="paymentMethods"
    @payment-method-selected="setSelectedPaymentMethod"
    @processing="stepForward"
    @processed="stepProcessed"
>
    <x-shop::shimmer.checkout.onepage.payment-method />
</v-payment-methods>

{!! view_render_event('bagisto.shop.checkout.onepage.payment_methods.after') !!}

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-payment-methods-template"
    >
        <div class="mb-7 max-md:last:!mb-0">
            <template v-if="! methods">
                <!-- Payment Method shimmer Effect -->
                <x-shop::shimmer.checkout.onepage.payment-method />
            </template>
    
            <template v-else>
                {!! view_render_event('bagisto.shop.checkout.onepage.payment_method.accordion.before') !!}

                <!-- Accordion Blade Component -->
                <x-shop::accordion class="overflow-hidden !border-b-0 max-md:rounded-lg max-md:!border-none max-md:!bg-diidsSurface">
                    <!-- Accordion Blade Component Header -->
                    <x-slot:header class="px-0 py-4 max-md:p-3 max-md:text-sm max-md:font-medium max-sm:p-2">
                        
                        <div class="flex items-center justify-between">
                            <h2 class="font-dmserif text-2xl text-diidsInk max-md:text-base">
                                @lang('shop::app.checkout.onepage.payment.payment-method')
                            </h2>
                        </div>
                    </x-slot>
    
                    <!-- Accordion Blade Component Content -->
                    <x-slot:content class="mt-8 !p-0 max-md:mt-0 max-md:rounded-t-none max-md:border max-md:border-t-0 max-md:!p-4">
                        <div class="flex flex-wrap gap-7 max-md:gap-4 max-sm:gap-2.5">
                            <div 
                                class="relative cursor-pointer max-md:max-w-full max-md:flex-auto"
                                v-for="(payment, index) in methods"
                            >
                                {!! view_render_event('bagisto.shop.checkout.payment-method.before') !!}

                                <input 
                                    type="radio" 
                                    name="payment[method]" 
                                    :value="payment.payment"
                                    :id="payment.method"
                                    class="peer hidden"
                                    @change="store(payment)"
                                >
    
                                <label 
                                    :for="payment.method" 
                                    class="icon-radio-unselect peer-checked:icon-radio-select absolute top-5 cursor-pointer text-2xl text-navyBlue ltr:right-5 rtl:left-5"
                                >
                                </label>

                                <label 
                                    :for="payment.method" 
                                    class="block w-[190px] cursor-pointer rounded-xl border border-diidsBorder p-5 max-md:flex max-md:w-full max-md:gap-5 max-md:rounded-lg max-sm:gap-4 max-sm:px-4 max-sm:py-2.5"
                                >
                                    {!! view_render_event('bagisto.shop.checkout.onepage.payment-method.image.before') !!}

                                    <img
                                        class="max-h-11 max-w-14"
                                        :src="payment.image"
                                        width="55"
                                        height="55"
                                        :alt="payment.method_title"
                                        :title="payment.method_title"
                                    />

                                    {!! view_render_event('bagisto.shop.checkout.onepage.payment-method.image.after') !!}

                                    <div>
                                        {!! view_render_event('bagisto.shop.checkout.onepage.payment-method.title.before') !!}

                                        <p class="mt-1.5 text-sm font-semibold max-md:mt-1 max-sm:mt-0">
                                            @{{ payment.method_title }}
                                        </p>
                                        
                                        {!! view_render_event('bagisto.shop.checkout.onepage.payment-method.title.after') !!}

                                        {!! view_render_event('bagisto.shop.checkout.onepage.payment-method.description.before') !!}

                                        <p class="mt-2.5 text-xs font-medium text-diidsInk/60 max-md:mt-1 max-sm:mt-0">
                                            @{{ payment.description }}
                                        </p> 

                                        {!! view_render_event('bagisto.shop.checkout.onepage.payment-method.description.after') !!}
    
                                    </div>
                                </label>

                                {!! view_render_event('bagisto.shop.checkout.payment-method.after') !!}

                                <!-- Todo implement the additionalDetails -->
                                {{-- \Webkul\Payment\Payment::getAdditionalDetails($payment['method'] --}}
                            </div>
                        </div>

                        @auth('customer')
                            @php
                                $savedCards = auth()->guard('customer')->user()->payment_methods;
                            @endphp

                            <div
                                class="mt-5 grid gap-2.5"
                                v-if="selectedPaymentMethod == 'paystack'"
                            >
                                @if ($savedCards && ! $savedCards->isEmpty())
                                    <p class="text-xs font-semibold uppercase tracking-wide text-diidsInk/60">
                                        @lang('shop::app.checkout.onepage.payment.saved-cards')
                                    </p>

                                    @foreach ($savedCards as $savedCard)
                                        <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-diidsBorder p-3 has-[:checked]:border-navyBlue">
                                            <input
                                                type="radio"
                                                name="paystack_saved_card"
                                                value="{{ $savedCard->id }}"
                                                @change="selectSavedCard({{ $savedCard->id }})"
                                            >

                                            <span class="text-sm">
                                                {{ $savedCard->card_type ? ucfirst($savedCard->card_type) : 'Card' }}
                                                &bull;&bull;&bull;&bull; {{ $savedCard->last4 }}
                                                ({{ str_pad($savedCard->exp_month, 2, '0', STR_PAD_LEFT) }}/{{ $savedCard->exp_year }})
                                            </span>
                                        </label>
                                    @endforeach

                                    <label class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-diidsBorder p-3 has-[:checked]:border-navyBlue">
                                        <input
                                            type="radio"
                                            name="paystack_saved_card"
                                            value=""
                                            checked
                                            @change="selectSavedCard(null)"
                                        >

                                        <span class="text-sm">
                                            @lang('shop::app.checkout.onepage.payment.use-new-card')
                                        </span>
                                    </label>
                                @endif

                                {{-- Shown to every logged-in customer, not just
                                     ones who already have a saved card — this is
                                     how the first card ever gets saved. --}}
                                <label class="mt-1.5 flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        v-model="saveCard"
                                    >

                                    <span class="text-xs text-diidsInk/70">
                                        @lang('shop::app.checkout.onepage.payment.save-card')
                                    </span>
                                </label>
                            </div>
                        @endauth
                    </x-slot>
                </x-shop::accordion>

                {!! view_render_event('bagisto.shop.checkout.onepage.payment_method.accordion.after') !!}
            </template>
        </div>
    </script>

    <script type="module">
        app.component('v-payment-methods', {
            template: '#v-payment-methods-template',

            props: {
                methods: {
                    type: Object,
                    required: true,
                    default: () => null,
                },
            },

            emits: ['payment-method-selected', 'processing', 'processed'],

            data() {
                return {
                    selectedPaymentMethod: null,

                    saveCard: false,
                };
            },

            watch: {
                saveCard(value) {
                    this.$axios.post("{{ route('paystack.select-saved-card') }}", {
                        save_card_intent: value,
                    }).catch(() => {});
                },
            },

            methods: {
                store(selectedMethod) {
                    this.selectedPaymentMethod = selectedMethod.method;

                    this.$emit('payment-method-selected', selectedMethod.method);

                    this.$emit('processing', 'review');

                    this.$axios.post("{{ route('shop.checkout.onepage.payment_methods.store') }}", {
                            payment: selectedMethod
                        })
                        .then(response => {
                            this.$emit('processed', response.data.cart);

                            // Used in mobile view.
                            if (window.innerWidth <= 768) {
                                window.scrollTo({
                                    top: document.body.scrollHeight,
                                    behavior: 'smooth'
                                });
                            }
                        })
                        .catch(error => {
                            this.$emit('processing', 'payment');

                            if (error.response.data.redirect_url) {
                                window.location.href = error.response.data.redirect_url;
                            }
                        });
                },

                selectSavedCard(id) {
                    this.$axios.post("{{ route('paystack.select-saved-card') }}", {
                        customer_payment_method_id: id,
                    }).catch(() => {});
                },
            },
        });
    </script>
@endPushOnce
