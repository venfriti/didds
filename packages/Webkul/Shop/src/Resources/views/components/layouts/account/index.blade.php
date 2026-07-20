<x-shop::layouts :has-feature="false">
    <!-- Page Title -->
    <x-slot:title>
        {{ $title ?? '' }}
    </x-slot>

    <!-- Page Content -->
    <div class="container px-[60px] pb-16 max-lg:px-8 max-md:px-0">
        <x-shop::layouts.account.breadcrumb />

        <div class="flex items-start gap-10 pt-8 max-lg:gap-5 max-md:grid max-md:pt-5 ltr:max-md:gap-0 rtl:max-md:gap-0">
            {{ $slot }}
        </div>
    </div>
</x-shop::layouts>
