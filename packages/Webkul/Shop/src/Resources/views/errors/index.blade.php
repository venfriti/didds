<x-shop::layouts
    :has-header="false"
    :has-feature="false"
    :has-footer="false"
>
    <x-slot:title>
        @lang("shop::app.errors.{$errorCode}.title")
    </x-slot>

    <section class="diids-error">
        <a href="{{ route('shop.home.index') }}" class="diids-error__brand" aria-label="DIIDS">
            <img src="{{ bagisto_asset('images/diids-logo.svg') }}" alt="DIIDS" width="110" height="36">
        </a>

        <div class="diids-error__inner">
            <p class="diids-error__code">{{ $errorCode }}</p>

            <h1 class="diids-error__title">
                @lang("shop::app.errors.{$errorCode}.title")
            </h1>

            <p class="diids-error__desc">
                {{
                    $errorCode === 503 && core()->getCurrentChannel()->maintenance_mode_text != ""
                    ? core()->getCurrentChannel()->maintenance_mode_text
                    : trans("shop::app.errors.{$errorCode}.description")
                }}
            </p>

            <div class="diids-error__actions">
                <a href="{{ route('shop.home.index') }}" class="diids-btn diids-btn--solid"><span>@lang('shop::app.errors.go-to-home')</span></a>
                <a href="{{ route('shop.search.index') }}" class="diids-btn diids-btn--ghost"><span>Shop All</span></a>
            </div>
        </div>

        <p class="diids-error__tagline">Everyday Confidence</p>
    </section>
</x-shop::layouts>
