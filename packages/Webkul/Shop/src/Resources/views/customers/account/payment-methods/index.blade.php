<x-shop::layouts.account>
    <!-- Page Title -->
    <x-slot:title>
        @lang('shop::app.customers.account.payment-methods.index.title')
    </x-slot>

    <div class="max-md:hidden">
        <x-shop::layouts.account.navigation />
    </div>

    <div class="flex-auto mx-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <!-- Back Button -->
                <a
                    class="grid md:hidden"
                    href="{{ route('shop.customers.account.index') }}"
                >
                    <span class="text-2xl icon-arrow-left rtl:icon-arrow-right"></span>
                </a>

                <h2 class="font-dmserif text-2xl text-diidsInk max-md:text-xl max-sm:text-base ltr:ml-2.5 md:ltr:ml-0 rtl:mr-2.5 md:rtl:mr-0">
                    @lang('shop::app.customers.account.payment-methods.index.title')
                </h2>
            </div>
        </div>

        @if (! $paymentMethods->isEmpty())
            {!! view_render_event('bagisto.shop.customers.account.payment_methods.list.before', ['paymentMethods' => $paymentMethods]) !!}

            <div class="mt-[60px] grid grid-cols-2 gap-5 max-1060:grid-cols-[1fr] max-md:mt-5">
                @foreach ($paymentMethods as $paymentMethod)
                    <div class="p-5 border rounded-xl border-diidsBorder max-md:flex-wrap">
                        <div class="flex justify-between">
                            <p class="text-base font-medium" v-pre>
                                {{ $paymentMethod->card_type ? ucfirst($paymentMethod->card_type) : 'Card' }}
                                &bull;&bull;&bull;&bull; {{ $paymentMethod->last4 }}
                            </p>

                            <div class="flex gap-4 max-sm:gap-2.5">
                                @if ($paymentMethod->is_default)
                                    <div class="label-pending block h-fit w-max px-2.5 py-1 max-md:px-1.5">
                                        @lang('shop::app.customers.account.payment-methods.index.default')
                                    </div>
                                @endif

                                <!-- Dropdown Actions -->
                                <x-shop::dropdown position="bottom-{{ core()->getCurrentLocale()->direction === 'ltr' ? 'right' : 'left' }}">
                                    <x-slot:toggle>
                                        <button
                                            class="icon-more cursor-pointer rounded-md px-1.5 py-1 text-2xl text-diidsInk/60 transition-all hover:bg-diidsSurface hover:text-black focus:bg-diidsSurface focus:text-black max-md:p-0"
                                            aria-label="More Options"
                                        >
                                        </button>
                                    </x-slot>

                                    <x-slot:menu class="!py-1 max-sm:!py-0">
                                        <x-shop::dropdown.menu.item>
                                            <form
                                                method="POST"
                                                ref="paymentMethodDelete{{ $paymentMethod->id }}"
                                                action="{{ route('shop.customers.account.payment_methods.delete', $paymentMethod->id) }}"
                                            >
                                                @method('DELETE')
                                                @csrf
                                            </form>

                                            <a
                                                href="javascript:void(0);"
                                                @click="$emitter.emit('open-confirm-modal', {
                                                    agree: () => {
                                                        $refs['paymentMethodDelete{{ $paymentMethod->id }}'].submit()
                                                    }
                                                })"
                                            >
                                                <p class="w-full">
                                                    @lang('shop::app.customers.account.payment-methods.index.delete')
                                                </p>
                                            </a>
                                        </x-shop::dropdown.menu.item>

                                        @if (! $paymentMethod->is_default)
                                            <x-shop::dropdown.menu.item>
                                                <form
                                                    method="POST"
                                                    ref="paymentMethodSetAsDefault{{ $paymentMethod->id }}"
                                                    action="{{ route('shop.customers.account.payment_methods.update.default', $paymentMethod->id) }}"
                                                >
                                                    @method('PATCH')
                                                    @csrf
                                                </form>

                                                <a
                                                    href="javascript:void(0);"
                                                    @click="$emitter.emit('open-confirm-modal', {
                                                        agree: () => {
                                                            $refs['paymentMethodSetAsDefault{{ $paymentMethod->id }}'].submit()
                                                        }
                                                    })"
                                                >
                                                    <button>
                                                        @lang('shop::app.customers.account.payment-methods.index.set-as-default')
                                                    </button>
                                                </a>
                                            </x-shop::dropdown.menu.item>
                                        @endif
                                    </x-slot>
                                </x-shop::dropdown>
                            </div>
                        </div>

                        <p class="mt-6 text-diidsInk/60 max-md:mt-5 max-md:text-sm" v-pre>
                            @lang('shop::app.customers.account.payment-methods.index.expires', ['month' => str_pad($paymentMethod->exp_month, 2, '0', STR_PAD_LEFT), 'year' => $paymentMethod->exp_year])
                        </p>
                    </div>
                @endforeach
            </div>

            {!! view_render_event('bagisto.shop.customers.account.payment_methods.list.after', ['paymentMethods' => $paymentMethods]) !!}
        @else
            <!-- Payment Methods Empty Page -->
            <div class="grid items-center w-full py-32 m-auto text-center place-content-center justify-items-center">
                <img
                    class="max-md:h-[100px] max-md:w-[100px]"
                    src="{{ bagisto_asset('images/no-address.png') }}"
                    alt="Empty Payment Methods"
                    title=""
                >

                <p
                    class="text-xl max-md:text-sm"
                    role="heading"
                >
                    @lang('shop::app.customers.account.payment-methods.index.empty-payment-methods')
                </p>
            </div>
        @endif
    </div>
</x-shop::layouts.account>
