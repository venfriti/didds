<!-- SEO Meta Content -->
@push('meta')
    <meta name="title" content="{{ $page->meta_title }}" />
    <meta name="description" content="{{ $page->meta_description }}" />
    <meta name="keywords" content="{{ $page->meta_keywords }}" />
@endPush

<x-shop::layouts>
    <x-slot:title>
        {{ $page->meta_title ?: $page->page_title }}
    </x-slot>

    @php
        // Editorial treatment for the brand story; clean readable layout for
        // policy / legal pages.
        $isStory = $page->url_key === 'about-us';
    @endphp

    @if ($isStory)
        <section class="diids-story">
            <div class="diids-story__hero">
                <img src="{{ bagisto_asset('images/shoot/couple-a.webp') }}" alt="DIIDS">
                <div class="diids-story__hero-overlay">
                    <p class="diids-story__kicker">DIIDS / The Story</p>
                    <h1 class="diids-story__title">Everyday Confidence</h1>
                </div>
            </div>

            <div class="diids-story__body static-container">
                {!! $page->html_content !!}
            </div>

            <div class="diids-story__cta">
                <a href="/men" class="diids-btn diids-btn--solid"><span>Shop Men</span></a>
                <a href="/women" class="diids-btn diids-btn--ghost"><span>Shop Women</span></a>
            </div>
        </section>
    @else
        <section class="diids-doc">
            <header class="diids-doc__head">
                <p class="diids-doc__kicker">DIIDS</p>
                <h1 class="diids-doc__title">{{ $page->page_title }}</h1>
            </header>

            <div class="diids-doc__body static-container">
                {!! $page->html_content !!}
            </div>
        </section>
    @endif
</x-shop::layouts>
