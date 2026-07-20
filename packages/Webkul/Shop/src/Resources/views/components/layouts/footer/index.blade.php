{!! view_render_event('bagisto.shop.layout.footer.before') !!}

<!--
    The category repository is injected directly here because there is no way
    to retrieve it from the view composer, as this is an anonymous component.
-->
@inject('themeCustomizationRepository', 'Webkul\Theme\Repositories\ThemeCustomizationRepository')

<!--
    This code needs to be refactored to reduce the amount of PHP in the Blade
    template as much as possible.
-->
@php
    $channel = core()->getCurrentChannel();

    $customization = $themeCustomizationRepository->findOneWhere([
        'type'       => 'footer_links',
        'status'     => 1,
        'theme_code' => $channel->theme,
        'channel_id' => $channel->id,
    ]);

    $footerColumns = [
        'column_1' => 'Company',
        'column_2' => 'Support',
        'column_3' => 'Policies',
    ];
@endphp

<footer class="mt-9 bg-navyBlue text-diidsSurface max-sm:mt-10">
    <div class="grid grid-cols-[1.4fr_repeat(3,1fr)_1.2fr] gap-x-10 gap-y-10 px-[60px] py-16 max-1180:grid-cols-2 max-1180:px-8 max-md:grid-cols-1 max-md:gap-8 max-md:px-8 max-md:py-10 max-sm:px-4 max-sm:py-8">
        <!-- Wordmark + social -->
        <div class="grid gap-6 max-1180:col-span-2 max-md:col-span-1">
            <p class="font-dmserif text-2xl tracking-wide text-diidsSurface">
                DIIDS
            </p>

            <div class="flex items-center gap-3">
                <a href="#" aria-label="Facebook" class="flex h-9 w-9 items-center justify-center rounded-full border border-diidsSurface/25 transition-colors hover:border-diidsSurface">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" class="text-diidsSurface"><path d="M22 12.06C22 6.51 17.52 2 12 2S2 6.51 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.85c0-2.5 1.49-3.89 3.78-3.89 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.88h2.78l-.44 2.91h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94Z"/></svg>
                </a>
                <a href="#" aria-label="Instagram" class="flex h-9 w-9 items-center justify-center rounded-full border border-diidsSurface/25 transition-colors hover:border-diidsSurface">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="text-diidsSurface"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg>
                </a>
                <a href="#" aria-label="X" class="flex h-9 w-9 items-center justify-center rounded-full border border-diidsSurface/25 transition-colors hover:border-diidsSurface">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" class="text-diidsSurface"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231ZM17.083 19.77h1.833L7.084 4.126H5.117Z"/></svg>
                </a>
            </div>
        </div>

        <!-- Link columns -->
        <div
            class="hidden flex-wrap items-start gap-16 max-1060:hidden md:contents"
            v-pre
        >
            @if ($customization?->options)
                @foreach ($footerColumns as $columnKey => $columnLabel)
                    @if (! empty($customization->options[$columnKey]))
                        <div class="grid gap-4">
                            <p class="font-mono text-xs uppercase tracking-[0.1em] text-diidsSurface">
                                {{ $columnLabel }}
                            </p>

                            <ul class="grid gap-3 text-sm text-diidsSurface/70">
                                @php
                                    $links = $customization->options[$columnKey];
                                    usort($links, function ($a, $b) {
                                        return $a['sort_order'] - $b['sort_order'];
                                    });
                                @endphp

                                @foreach ($links as $link)
                                    <li>
                                        <a href="{{ $link['url'] }}" class="transition-colors hover:text-diidsSurface">
                                            {{ $link['title'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        <!-- For Mobile view -->
        <x-shop::accordion
            :is-active="false"
            class="!w-full !rounded-none !border-0 !border-t !border-diidsSurface/15 md:hidden"
        >
            <x-slot:header class="!bg-transparent !px-0 font-mono text-xs uppercase tracking-[0.1em] text-diidsSurface">
                @lang('shop::app.components.layouts.footer.footer-content')
            </x-slot>

            <x-slot:content class="flex flex-wrap justify-between gap-6 !bg-transparent !px-0">
                @if ($customization?->options)
                    @foreach ($footerColumns as $columnKey => $columnLabel)
                        @if (! empty($customization->options[$columnKey]))
                            <ul
                                class="grid gap-3 text-sm text-diidsSurface/70"
                                v-pre
                            >
                                @php
                                    $links = $customization->options[$columnKey];
                                    usort($links, function ($a, $b) {
                                        return $a['sort_order'] - $b['sort_order'];
                                    });
                                @endphp

                                @foreach ($links as $link)
                                    <li>
                                        <a
                                            href="{{ $link['url'] }}"
                                            class="transition-colors hover:text-diidsSurface"
                                        >
                                            {{ $link['title'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach
                @endif
            </x-slot>
        </x-shop::accordion>

        {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.before') !!}

        <!-- News Letter subscription -->
        @if (core()->getConfigData('customer.settings.newsletter.subscription'))
            <div class="grid gap-3">
                <p class="font-mono text-xs uppercase tracking-[0.1em] text-diidsSurface">
                    Newsletter
                </p>

                <p class="text-sm text-diidsSurface/70">
                    Sign up for our newsletter and receive updates on new arrivals.
                </p>

                <div>
                    <x-shop::form
                        :action="route('shop.subscription.store')"
                        class="pt-2 rounded"
                        toolname="subscribe_to_newsletter"
                        tooldescription="{{ trans('shop::app.components.layouts.webmcp.subscribe-newsletter') }}"
                        toolautosubmit
                    >
                        <div class="relative w-full">
                            <x-shop::form.control-group.control
                                type="email"
                                class="block w-full rounded-full border border-diidsSurface/30 bg-transparent px-5 py-3 pr-12 text-sm text-diidsSurface placeholder:text-diidsSurface/40 focus:border-diidsSurface"
                                name="email"
                                rules="required|email"
                                label="Email"
                                :aria-label="trans('shop::app.components.layouts.footer.email')"
                                placeholder="Email"
                                toolparamdescription="{{ trans('shop::app.components.layouts.webmcp.subscribe-newsletter-email') }}"
                            />

                            <x-shop::form.control-group.error control-name="email" />

                            <button
                                type="submit"
                                aria-label="@lang('shop::app.components.layouts.footer.subscribe')"
                                class="absolute top-1/2 flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-diidsSurface text-navyBlue transition-opacity hover:opacity-80 ltr:right-1.5 rtl:left-1.5"
                            >
                                <span class="icon-arrow-right rtl:icon-arrow-left text-base"></span>
                            </button>
                        </div>
                    </x-shop::form>

                    <label class="flex cursor-pointer items-start gap-2.5 pt-4 text-xs text-diidsSurface/70">
                        <input type="checkbox" class="mt-0.5 h-3.5 w-3.5 rounded-sm border-diidsSurface/40 bg-transparent">
                        I agree to receiving marketing emails and special deals
                    </label>
                </div>
            </div>
        @endif

        {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.after') !!}
    </div>

    <div class="flex flex-wrap items-center justify-between gap-4 border-t border-diidsSurface/15 px-[60px] py-6 max-1180:px-8 max-md:justify-center max-md:px-8 max-md:text-center max-sm:px-5">
        {!! view_render_event('bagisto.shop.layout.footer.footer_text.before') !!}

        <p class="font-mono text-[11px] uppercase tracking-[0.06em] text-diidsSurface/60">
            @if (core()->getConfigData('general.content.footer.copyright_content'))
                {!! core()->getConfigData('general.content.footer.copyright_content') !!}
            @else
                @lang('shop::app.components.layouts.footer.footer-text', ['current_year'=> date('Y') ])
            @endif
        </p>

        <div class="flex items-center gap-3 text-diidsSurface/60">
            <span class="rounded border border-diidsSurface/25 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide">Visa</span>
            <span class="rounded border border-diidsSurface/25 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide">Mastercard</span>
            <span class="rounded border border-diidsSurface/25 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide">Verve</span>
            <span class="rounded border border-diidsSurface/25 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide">Paystack</span>
        </div>

        {!! view_render_event('bagisto.shop.layout.footer.footer_text.after') !!}
    </div>
</footer>

{!! view_render_event('bagisto.shop.layout.footer.after') !!}
