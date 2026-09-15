<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.reporting.traffic.index.title')
    </x-slot>

    <!-- Page Header -->
    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        <div class="grid gap-1.5">
            <p class="pt-1.5 text-xl font-bold leading-6 text-diidsInk dark:text-white">
                @lang('admin::app.reporting.traffic.index.title')
            </p>
        </div>

        <!-- Actions -->
        <v-reporting-filters>
            <!-- Shimmer -->
            <div class="flex gap-1.5">
                <div class="shimmer h-[39px] w-[132px] rounded-md"></div>
                <div class="shimmer h-[39px] w-[140px] rounded-md"></div>
                <div class="shimmer h-[39px] w-[140px] rounded-md"></div>
            </div>
        </v-reporting-filters>
    </div>

    <!-- Traffic Stats Vue Component -->
    <div class="flex flex-1 flex-col gap-4 max-xl:flex-auto">
        <!-- Top Pages and Top Sources Sections Container -->
        <div class="flex flex-col justify-between gap-4 flex-1 [&>*]:flex-1 md:flex-row">
            <!-- Top Pages Section -->
            @include('admin::reporting.traffic.top-pages')

            <!-- Top Sources Section -->
            @include('admin::reporting.traffic.top-sources')
        </div>

        <!-- Conversion by Source Section -->
        <div class="flex flex-col justify-between gap-4 flex-1 [&>*]:flex-1 md:flex-row">
            @include('admin::reporting.traffic.conversion-by-source')
        </div>
    </div>

    @pushOnce('scripts')
        <script
            type="text/x-template"
            id="v-reporting-filters-template"
        >
            <div class="flex gap-1.5">
                <template v-if="channels.length > 2">
                    <x-admin::dropdown position="bottom-right">
                        <x-slot:toggle>
                            <button
                                type="button"
                                class="inline-flex w-full cursor-pointer appearance-none items-center justify-between gap-x-2 rounded-md border bg-white px-2.5 py-1.5 text-center text-sm leading-6 text-diidsInk/70 transition-all marker:shadow hover:border-diidsBorder focus:border-diidsBorder dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-diidsBorder dark:focus:border-diidsBorder"
                            >
                                @{{ channels.find(channel => channel.code == filters.channel).name }}

                                <span class="icon-sort-down text-2xl"></span>
                            </button>
                        </x-slot>

                        <x-slot:menu class="!p-0 shadow-[0_5px_20px_rgba(0,0,0,0.15)] dark:border-gray-800">
                            <x-admin::dropdown.menu.item
                                v-for="channel in channels"
                                ::class="{'bg-diidsSurface dark:bg-gray-950': channel.code == filters.channel}"
                                @click="filters.channel = channel.code"
                            >
                                @{{ channel.name }}
                            </x-admin::dropdown.menu.item>
                        </x-slot>
                    </x-admin::dropdown>
                </template>

                <x-admin::flat-picker.date class="!w-[140px]" ::allow-input="false">
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-diidsInk/70 transition-all hover:border-diidsBorder dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-diidsBorder"
                        v-model="filters.start"
                        placeholder="@lang('admin::app.reporting.traffic.index.start-date')"
                    />
                </x-admin::flat-picker.date>

                <x-admin::flat-picker.date class="!w-[140px]" ::allow-input="false">
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-diidsInk/70 transition-all hover:border-diidsBorder dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-diidsBorder"
                        v-model="filters.end"
                        placeholder="@lang('admin::app.reporting.traffic.index.end-date')"
                    />
                </x-admin::flat-picker.date>
            </div>
        </script>

        <script type="module">
            app.component('v-reporting-filters', {
                template: '#v-reporting-filters-template',

                data() {
                    return {
                        channels: [
                            {
                                name: "@lang('admin::app.reporting.traffic.index.all-channels')",
                                code: ''
                            },
                            ...@json(core()->getAllChannels()),
                        ],

                        filters: {
                            channel: '',

                            start: "{{ $startDate->format('Y-m-d') }}",

                            end: "{{ $endDate->format('Y-m-d') }}",
                        }
                    }
                },

                watch: {
                    filters: {
                        handler() {
                            this.$emitter.emit('reporting-filter-updated', this.filters);
                        },

                        deep: true
                    }
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
