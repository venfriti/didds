<!-- SEO Meta Content -->
@push('meta')
    <meta name="description" content="@lang('shop::app.customers.login-form.page-title')"/>
    <meta name="keywords" content="@lang('shop::app.customers.login-form.page-title')"/>
@endPush

<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <x-slot:title>
        @lang('shop::app.customers.login-form.page-title')
    </x-slot>

    <div class="diids-auth">
        {{-- Left: editorial image --}}
        <div class="diids-auth__visual">
            <img src="{{ bagisto_asset('images/auth-visual.webp') }}" alt="DIIDS">
            <a href="{{ route('shop.home.index') }}" class="diids-auth__brand" aria-label="{{ config('app.name') }}">
                <img src="{{ bagisto_asset('images/diids-logo.svg') }}" alt="DIIDS" width="120" height="40">
            </a>
            <p class="diids-auth__tagline">Everyday Confidence</p>
        </div>

        {{-- Right: form --}}
        <div class="diids-auth__panel">
            <div class="diids-auth__inner">
                <a href="{{ route('shop.home.index') }}" class="diids-auth__brand-mobile" aria-label="{{ config('app.name') }}">
                    <img src="{{ bagisto_asset('images/diids-logo.svg') }}" alt="DIIDS" width="110" height="36">
                </a>

                <p class="diids-auth__eyebrow">Account</p>
                <h1 class="diids-auth__title">@lang('shop::app.customers.login-form.page-title')</h1>

                {!! view_render_event('bagisto.shop.customers.login.before') !!}

                {{-- Google sign-in --}}
                @if (core()->getConfigData('customer.settings.social_login.enable_google'))
                    <a href="{{ route('customer.social-login.index', 'google') }}" class="diids-google">
                        @include('social_login::icons.google')
                        <span>Continue with Google</span>
                    </a>

                    <div class="diids-auth__divider"><span>or</span></div>
                @endif

                <x-shop::form :action="route('shop.customer.session.create')">
                    {!! view_render_event('bagisto.shop.customers.login_form_controls.before') !!}

                    <x-shop::form.control-group>
                        <x-shop::form.control-group.label class="required">
                            @lang('shop::app.customers.login-form.email')
                        </x-shop::form.control-group.label>
                        <x-shop::form.control-group.control
                            type="email"
                            class="diids-auth__input"
                            name="email"
                            rules="required|email"
                            value=""
                            :label="trans('shop::app.customers.login-form.email')"
                            placeholder="email@example.com"
                            :aria-label="trans('shop::app.customers.login-form.email')"
                            aria-required="true"
                        />
                        <x-shop::form.control-group.error control-name="email" />
                    </x-shop::form.control-group>

                    <x-shop::form.control-group>
                        <x-shop::form.control-group.label class="required">
                            @lang('shop::app.customers.login-form.password')
                        </x-shop::form.control-group.label>
                        <x-shop::form.control-group.control
                            type="password"
                            class="diids-auth__input"
                            id="password"
                            name="password"
                            rules="required|min:6"
                            value=""
                            :label="trans('shop::app.customers.login-form.password')"
                            :placeholder="trans('shop::app.customers.login-form.password')"
                            :aria-label="trans('shop::app.customers.login-form.password')"
                            aria-required="true"
                        />
                        <x-shop::form.control-group.error control-name="password" />
                    </x-shop::form.control-group>

                    <div class="diids-auth__row">
                        <div class="flex select-none items-center gap-1.5">
                            <input type="checkbox" id="show-password" class="peer hidden" onchange="switchVisibility()"/>
                            <label class="icon-uncheck peer-checked:icon-check-box cursor-pointer text-xl text-navyBlue" for="show-password"></label>
                            <label class="cursor-pointer select-none text-sm text-diidsInk/60" for="show-password">
                                @lang('shop::app.customers.login-form.show-password')
                            </label>
                        </div>
                        <a href="{{ route('shop.customers.forgot_password.create') }}" class="diids-auth__link">
                            @lang('shop::app.customers.login-form.forgot-pass')
                        </a>
                    </div>

                    @if (core()->getConfigData('customer.captcha.credentials.status'))
                        <x-shop::form.control-group class="mt-5">
                            {!! \Webkul\Customer\Facades\Captcha::render() !!}
                            <x-shop::form.control-group.error control-name="recaptcha_token" />
                        </x-shop::form.control-group>
                    @endif

                    <button class="diids-btn diids-btn--solid diids-auth__submit" type="submit">
                        <span>@lang('shop::app.customers.login-form.button-title')</span>
                    </button>

                    {!! view_render_event('bagisto.shop.customers.login_form_controls.after') !!}
                </x-shop::form>

                {!! view_render_event('bagisto.shop.customers.login.after') !!}

                @if (request()->cookie('enable-resend') && request()->cookie('email-for-resend'))
                    <p class="diids-auth__meta">
                        <a href="{{ route('shop.customers.resend.verification_email', urlencode(request()->cookie('email-for-resend'))) }}">
                            @lang('shop::app.customers.login-form.resend-verification')
                        </a>
                    </p>
                @endif

                <p class="diids-auth__meta">
                    @lang('shop::app.customers.login-form.new-customer')
                    <a href="{{ route('shop.customers.register.index') }}">
                        @lang('shop::app.customers.login-form.create-your-account')
                    </a>
                </p>
            </div>
        </div>
    </div>

    @push('scripts')
        {!! \Webkul\Customer\Facades\Captcha::renderJS() !!}
        <script>
            function switchVisibility() {
                let f = document.getElementById("password");
                f.type = f.type === "password" ? "text" : "password";
            }
        </script>
    @endpush
</x-shop::layouts>
