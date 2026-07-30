{{--
    DIIDS designed homepage bands (between hero and the admin product carousels).
    All imagery is the real DIIDS shoot. Type is Grift throughout (Magste is
    hero-only). Layouts are intentionally editorial: asymmetric splits, generous
    negative space, hairline rules, mono labels.
--}}

{{-- ============================================================= STATEMENT --}}
<section class="diids-band diids-statement">
    <div class="diids-statement__label">
        <span>01</span><span>The Brand</span>
    </div>

    <div class="diids-statement__grid">
        <p class="diids-statement__lead">
            Underwear built for confidence, comfort and style every day.
        </p>
        <div class="diids-statement__body">
            <p>
                DIIDS combines high quality fabrics, modern design and exceptional
                craftsmanship to deliver everyday essentials that elevate comfort
                without compromising performance. Designed for both men and women,
                every piece is made for a perfect fit and lasting durability.
            </p>
            <a href="{{ route('shop.cms.page', 'about-us') }}" class="diids-link">Read our story</a>
        </div>
    </div>
</section>

{{-- ========================================================= CATEGORY GRID --}}
<section class="diids-band diids-cats">
    <div class="diids-band__head">
        <h2 class="diids-h2">Shop the Range</h2>
        <span class="diids-band__meta">Essentials for everyone</span>
    </div>

    <div class="diids-cats__grid">
        <a href="/men" class="diids-cat diids-cat--tall">
            <img src="{{ bagisto_asset('images/shoot/cat-men.webp') }}" alt="Shop men" loading="lazy">
            <span class="diids-cat__tag">For Him</span>
            <span class="diids-cat__cta">Shop Men <em>→</em></span>
        </a>

        <a href="/women" class="diids-cat diids-cat--tall">
            <img src="{{ bagisto_asset('images/shoot/cat-women.webp') }}" alt="Shop women" loading="lazy">
            <span class="diids-cat__tag">For Her</span>
            <span class="diids-cat__cta">Shop Women <em>→</em></span>
        </a>

        <a href="{{ route('shop.home.index') }}" class="diids-cat diids-cat--wide">
            <img src="{{ bagisto_asset('images/shoot/cat-collection.webp') }}" alt="The collection" loading="lazy">
            <span class="diids-cat__tag">Editorial</span>
            <span class="diids-cat__cta">The Collection <em>→</em></span>
        </a>
    </div>
</section>

{{-- ============================================================= MARQUEE --}}
<div class="diids-marquee" aria-hidden="true">
    <div class="diids-marquee__track">
        @for ($i = 0; $i < 2; $i++)
            <span class="diids-marquee__group">
                <span>Everyday Confidence</span>
                <span class="diids-marquee__dot">&bull;</span>
                <span>Premium Fabrics</span>
                <span class="diids-marquee__dot">&bull;</span>
                <span>For Him &amp; Her</span>
                <span class="diids-marquee__dot">&bull;</span>
                <span>Worldwide Shipping</span>
                <span class="diids-marquee__dot">&bull;</span>
            </span>
        @endfor
    </div>
</div>

{{-- ========================================================= CAMPAIGN BAND --}}
<section class="diids-band diids-campaign">
    <img class="diids-campaign__img" src="{{ bagisto_asset('images/shoot/couple-a.webp') }}" alt="DIIDS campaign" loading="lazy">
    <div class="diids-campaign__overlay">
        <p class="diids-campaign__kicker">DIIDS / SS 001</p>
        <p class="diids-campaign__headline">Feel confident in what you wear underneath.</p>
        <a href="/men" class="diids-btn diids-btn--solid diids-btn--ondark"><span>Discover</span></a>
    </div>
</section>
