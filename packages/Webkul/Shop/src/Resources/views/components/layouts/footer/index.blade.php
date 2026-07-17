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
@endphp

<footer class="mt-9 bg-navyBlue text-diidsSurface max-sm:mt-10">
    <div class="flex justify-between gap-x-12 gap-y-10 px-[60px] py-16 max-1180:px-8 max-1060:flex-col-reverse max-md:gap-8 max-md:px-8 max-md:py-10 max-sm:px-4 max-sm:py-8">
        <!-- For Desktop View -->
        <div
            class="flex flex-wrap items-start gap-16 max-1180:gap-10 max-1060:hidden"
            v-pre
        >
            @if ($customization?->options)
                @foreach ($customization->options as $footerLinkSection)
                    <ul class="grid gap-4 font-mono text-xs uppercase tracking-[0.08em] text-diidsSurface/70">
                        @php
                            usort($footerLinkSection, function ($a, $b) {
                                return $a['sort_order'] - $b['sort_order'];
                            });
                        @endphp

                        @foreach ($footerLinkSection as $link)
                            <li>
                                <a href="{{ $link['url'] }}" class="transition-colors hover:text-diidsSurface">
                                    {{ $link['title'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            @endif
        </div>

        <!-- For Mobile view -->
        <x-shop::accordion
            :is-active="false"
            class="hidden !w-full !rounded-none !border-0 !border-t !border-diidsSurface/15 max-1060:block"
        >
            <x-slot:header class="!bg-transparent !px-0 font-mono text-xs uppercase tracking-[0.08em] text-diidsSurface">
                @lang('shop::app.components.layouts.footer.footer-content')
            </x-slot>

            <x-slot:content class="flex flex-wrap justify-between gap-6 !bg-transparent !px-0">
                @if ($customization?->options)
                    @foreach ($customization->options as $footerLinkSection)
                        <ul
                            class="grid gap-4 font-mono text-xs uppercase tracking-[0.08em] text-diidsSurface/70"
                            v-pre
                        >
                            @php
                                usort($footerLinkSection, function ($a, $b) {
                                    return $a['sort_order'] - $b['sort_order'];
                                });
                            @endphp

                            @foreach ($footerLinkSection as $link)
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
                    @endforeach
                @endif
            </x-slot>
        </x-shop::accordion>

        {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.before') !!}

        <!-- News Letter subscription -->
        @if (core()->getConfigData('customer.settings.newsletter.subscription'))
            <div class="grid gap-3 max-w-[360px]">
                <p
                    class="font-dmserif text-3xl leading-[1.2] text-diidsSurface max-md:text-2xl max-sm:text-xl"
                    role="heading"
                    aria-level="2"
                >
                    @lang('shop::app.components.layouts.footer.newsletter-text')
                </p>

                <p class="text-sm text-diidsSurface/70">
                    @lang('shop::app.components.layouts.footer.subscribe-stay-touch')
                </p>

                <div>
                    <x-shop::form
                        :action="route('shop.subscription.store')"
                        class="mt-3 rounded max-sm:mt-2"
                        toolname="subscribe_to_newsletter"
                        tooldescription="{{ trans('shop::app.components.layouts.webmcp.subscribe-newsletter') }}"
                        toolautosubmit
                    >
                        <div class="relative w-full">
                            <x-shop::form.control-group.control
                                type="email"
                                class="block w-full rounded-none border-0 border-b border-diidsSurface/40 bg-transparent px-0 py-3 text-base text-diidsSurface placeholder:text-diidsSurface/40 focus:border-diidsSurface max-md:p-3.5 max-sm:mb-0 max-sm:text-sm"
                                name="email"
                                rules="required|email"
                                label="Email"
                                :aria-label="trans('shop::app.components.layouts.footer.email')"
                                placeholder="email@example.com"
                                toolparamdescription="{{ trans('shop::app.components.layouts.webmcp.subscribe-newsletter-email') }}"
                            />

                            <x-shop::form.control-group.error control-name="email" />

                            <button
                                type="submit"
                                class="absolute top-2 flex w-max items-center font-mono text-xs uppercase tracking-[0.08em] text-diidsSurface underline underline-offset-4 hover:text-diidsBlush ltr:right-0 rtl:left-0 max-md:top-2.5"
                            >
                                @lang('shop::app.components.layouts.footer.subscribe')
                            </button>
                        </div>
                    </x-shop::form>
                </div>
            </div>
        @endif

        {!! view_render_event('bagisto.shop.layout.footer.newsletter_subscription.after') !!}
    </div>

    <div class="flex items-center justify-between border-t border-diidsSurface/15 px-[60px] py-6 max-1180:px-8 max-md:flex-col max-md:gap-2 max-md:px-8 max-md:text-center max-sm:px-5">
        {!! view_render_event('bagisto.shop.layout.footer.footer_text.before') !!}

        <p class="font-dmserif text-lg text-diidsSurface">
            DIIDS
        </p>

        <p class="font-mono text-[11px] uppercase tracking-[0.06em] text-diidsSurface/60">
            @if (core()->getConfigData('general.content.footer.copyright_content'))
                {!! core()->getConfigData('general.content.footer.copyright_content') !!}
            @else
                @lang('shop::app.components.layouts.footer.footer-text', ['current_year'=> date('Y') ])
            @endif
        </p>

        {!! view_render_event('bagisto.shop.layout.footer.footer_text.after') !!}
    </div>
</footer>

{!! view_render_event('bagisto.shop.layout.footer.after') !!}
