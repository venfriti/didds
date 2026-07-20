@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-empty-info-template"
    >
        <div class="grid justify-center justify-items-center gap-3.5 px-2.5 py-10">
            <img
                src="{{ bagisto_asset('images/icon-add-product.svg') }}"
                class="h-20 w-20 dark:mix-blend-exclusion dark:invert"
            >

            <div class="flex flex-col items-center gap-2">
                <p
                    class="text-base font-semibold text-diidsInk/40"
                    v-if="type == 'event'"
                >
                    @lang('admin::app.catalog.products.edit.types.booking.empty-info.tickets.add')
                </p>

                <p
                    class="text-base font-semibold text-diidsInk/40"
                    v-else
                >
                    @lang('admin::app.catalog.products.edit.types.booking.empty-info.slots.add')
                </p>

                <p class="text-diidsInk/40">
                    @lang('admin::app.catalog.products.edit.types.booking.empty-info.slots.description')
                </p>
            </div>
        </div>
    </script>

    <script type="module">
        app.component('v-empty-info', {
            template: '#v-empty-info-template',

            props: ['type'],
        });
    </script>
@endpushOnce