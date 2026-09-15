<!-- Conversion by Source Vue Component -->
<v-reporting-traffic-conversion-by-source>
    <!-- Shimmer -->
    <x-admin::shimmer.reporting.products.last-search-terms />
</v-reporting-traffic-conversion-by-source>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-reporting-traffic-conversion-by-source-template"
    >
        <!-- Shimmer -->
        <template v-if="isLoading">
            <x-admin::shimmer.reporting.products.last-search-terms />
        </template>

        <!-- Conversion by Source Section -->
        <template v-else>
            <div class="box-shadow relative flex-1 rounded bg-white p-4 dark:bg-gray-900">
                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-base font-semibold text-diidsInk/70 dark:text-white">
                        @lang('admin::app.reporting.traffic.index.conversion-by-source')
                    </p>

                    <a
                        href="{{ route('admin.reporting.traffic.view', ['type' => 'conversion-by-source']) }}"
                        class="cursor-pointer text-sm text-navyBlue transition-all hover:underline"
                    >
                        @lang('admin::app.reporting.traffic.index.view-details')
                    </a>
                </div>

                <!-- Content -->
                <div class="grid gap-4">
                    <template v-if="report.statistics.length">
                        <div class="grid gap-7">
                            <div
                                class="grid"
                                v-for="source in report.statistics"
                            >
                                <div class="flex items-center justify-between">
                                    <p class="truncate dark:text-white">
                                        @{{ source.source }}
                                    </p>

                                    <p class="text-xs text-diidsInk/50 dark:text-gray-400">
                                        @{{ source.visitors }} @lang('admin::app.reporting.traffic.index.visitors'), @{{ source.orders }} @lang('admin::app.reporting.traffic.index.orders')
                                    </p>
                                </div>

                                <div class="flex items-center gap-5">
                                    <div class="relative h-2 w-full bg-slate-100">
                                        <div
                                            class="absolute left-0 h-2 bg-emerald-500"
                                            :style="{ 'width': Math.min(source.progress, 100) + '%' }"
                                        ></div>
                                    </div>

                                    <p class="text-sm font-semibold text-diidsInk/70 dark:text-gray-300">
                                        @{{ source.conversion_rate }}%
                                    </p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Empty State -->
                    <template v-else>
                        @include('admin::reporting.empty')
                    </template>

                    <!-- Date Range -->
                    <div class="flex justify-end gap-5">
                        <div class="flex items-center gap-1">
                            <span class="h-3.5 w-3.5 rounded-md bg-emerald-400"></span>

                            <p class="text-xs dark:text-gray-300">
                                @{{ report.date_range.current }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-reporting-traffic-conversion-by-source', {
            template: '#v-reporting-traffic-conversion-by-source-template',

            data() {
                return {
                    report: [],

                    isLoading: true,
                }
            },

            mounted() {
                this.getStats({});

                this.$emitter.on('reporting-filter-updated', this.getStats);
            },

            methods: {
                getStats(filters) {
                    this.isLoading = true;

                    var filters = Object.assign({}, filters);

                    filters.type = 'conversion-by-source';

                    this.$axios.get("{{ route('admin.reporting.traffic.stats') }}", {
                            params: filters
                        })
                        .then(response => {
                            this.report = response.data;

                            this.isLoading = false;
                        })
                        .catch(error => {});
                }
            }
        });
    </script>
@endPushOnce
