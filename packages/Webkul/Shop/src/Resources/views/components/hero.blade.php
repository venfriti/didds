{{--
    DIIDS hero — a 4-slide cutout slider. Each slide is a transparent cutout of
    DIIDS talent standing on the page, with the oversized Magste wordmark behind
    them (models occlude the letters for depth). One WebGL plane renders the
    active slide's texture and cross-fades on change; Magste is hero-only.
--}}
<section
    class="diids-hero"
    aria-label="DIIDS"
    data-hero-parallax
    data-hero-slider
    data-hero-interval="5200"
>
    {{-- top hairline label --}}
    <div class="diids-hero__marker">
        <span>DIIDS</span>
        <span aria-hidden="true">/</span>
        <span class="diids-hero__count"><em data-hero-index>01</em> / 04</span>
    </div>

    {{-- giant display type behind the models --}}
    <div class="diids-hero__type" aria-hidden="true">
        <span class="diids-hero__word diids-hero__word--top">Everyday</span>
        <span class="diids-hero__word diids-hero__word--bottom">Confidence</span>
    </div>

    <h1 class="sr-only">DIIDS — Everyday Confidence</h1>

    {{-- WebGL plane (renders active slide, cross-fades between them) --}}
    <div class="diids-hero__figure" data-parallax-layer>
        <div
            id="diids-hero-gl"
            class="diids-hero__gl"
            data-hero-srcs="{{ collect(['hero-1','hero-3','hero-2','hero-4'])->map(fn($s) => bagisto_asset('images/hero/'.$s.'.webp'))->implode(',') }}"
        ></div>

        {{-- <img> fallback stack (first slide shown; hidden once GL is ready) --}}
        @foreach (['hero-1','hero-3','hero-2','hero-4'] as $i => $slide)
            <img
                class="diids-hero__img {{ $i === 0 ? 'is-active' : '' }}"
                src="{{ bagisto_asset('images/hero/'.$slide.'.webp') }}"
                alt="DIIDS essentials"
                width="763"
                height="1500"
                data-slide="{{ $i }}"
                @if ($i === 0) fetchpriority="high" @else loading="lazy" @endif
            >
        @endforeach
    </div>

    {{-- lower-left intro copy --}}
    <div class="diids-hero__lede">
        <p>
            Premium underwear for people who value confidence, comfort and style
            every day. Fabrics that feel as good as they look, fit that lasts.
        </p>
    </div>

    {{-- lower-right actions --}}
    <div class="diids-hero__actions">
        <a href="/men" class="diids-btn diids-btn--solid"><span>Shop Men</span></a>
        <a href="/women" class="diids-btn diids-btn--ghost"><span>Shop Women</span></a>
    </div>

    {{-- slide dots --}}
    <div class="diids-hero__dots" data-hero-dots aria-label="Hero slides">
        @foreach (['hero-1','hero-3','hero-2','hero-4'] as $i => $slide)
            <button
                class="diids-hero__dot {{ $i === 0 ? 'is-active' : '' }}"
                data-slide-to="{{ $i }}"
                aria-label="Go to slide {{ $i + 1 }}"
            ></button>
        @endforeach
    </div>

</section>
