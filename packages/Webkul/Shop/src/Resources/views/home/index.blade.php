@php
    $channel = core()->getCurrentChannel();
@endphp

<!-- SEO Meta Content -->
@push ('meta')
    <meta
        name="title"
        content="{{ $channel->home_seo['meta_title'] ?? '' }}"
    />

    <meta
        name="description"
        content="{{ $channel->home_seo['meta_description'] ?? '' }}"
    />

    <meta
        name="keywords"
        content="{{ $channel->home_seo['meta_keywords'] ?? '' }}"
    />
@endPush

@push('scripts')
    @if(! empty($categories))
        <script>
            localStorage.setItem('categories', JSON.stringify(@json($categories)));
        </script>
    @endif
@endpush

<x-shop::layouts>
    <!-- Page Title -->
    <x-slot:title>
        {{  $channel->home_seo['meta_title'] ?? '' }}
    </x-slot>

    <!-- Loop over the theme customization -->
    @foreach ($customizations as $customization)
        @php ($data = $customization->options) @endphp

        <!-- Static Content -->
        @switch ($customization->type)
            @case ($customization::IMAGE_CAROUSEL)
                <!-- Image Carousel -->
                <div class="relative">
                    <x-shop::carousel
                        :options="$data"
                        aria-label="{{ trans('shop::app.home.index.image-carousel') }}"
                    />

                    <!-- Hero overlay -->
                    <div class="pointer-events-none absolute inset-0 z-[2] flex flex-col items-center justify-center gap-5 px-6 text-center">
                        <p class="font-mono text-xs uppercase tracking-[0.3em] text-white/80 max-sm:text-[10px]">
                            DIIDS &middot; No. 001
                        </p>

                        <h1 class="font-dmserif text-6xl uppercase tracking-tight text-white max-md:text-4xl max-sm:text-3xl">
                            Everyday&nbsp;Confidence
                        </h1>

                        <p class="max-w-md text-sm text-white/90 max-sm:text-xs">
                            Premium fabrics, honest fit &mdash; underwear built for comfort, every day of the week.
                        </p>

                        <div class="pointer-events-auto mt-2 flex flex-wrap items-center justify-center gap-4">
                            <a href="/men" class="primary-button !rounded-full !bg-white !text-navyBlue !border-white">
                                Shop Men
                            </a>

                            <a href="/women" class="secondary-button !rounded-full !border-white !text-white hover:!bg-white/10">
                                Shop Women
                            </a>
                        </div>
                    </div>
                </div>

                @break
            @case ($customization::STATIC_CONTENT)
                <!-- Push Style -->
                @if (! empty($data['css']))
                    @push ('styles')
                        <style>
                            {!! $data['css'] !!}
                        </style>
                    @endpush
                @endif

                <!-- Render HTML -->
                @if (! empty($data['html']))
                    {!! $data['html'] !!}
                @endif

                @break
            @case ($customization::CATEGORY_CAROUSEL)
                <!-- Categories carousel -->
                <x-shop::categories.carousel
                    :title="$data['title'] ?? ''"
                    :src="route('shop.api.categories.index', $data['filters'] ?? [])"
                    :navigation-link="route('shop.home.index')"
                    aria-label="{{ trans('shop::app.home.index.categories-carousel') }}"
                />

                @break
            @case ($customization::PRODUCT_CAROUSEL)
                <!-- Product Carousel -->
                <x-shop::products.carousel
                    :title="$data['title'] ?? ''"
                    :src="route('shop.api.products.index', $data['filters'] ?? [])"
                    :navigation-link="route('shop.search.index', $data['filters'] ?? [])"
                    aria-label="{{ trans('shop::app.home.index.product-carousel') }}"
                />

                @break
        @endswitch
    @endforeach
</x-shop::layouts>
