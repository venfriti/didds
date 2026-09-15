<!-- Top Sources Vue Component -->
<v-reporting-traffic-top-sources>
    <!-- Shimmer -->
    <x-admin::shimmer.reporting.products.last-search-terms />
</v-reporting-traffic-top-sources>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-reporting-traffic-top-sources-template"
    >
        <!-- Shimmer -->
        <template v-if="isLoading">
            <x-admin::shimmer.reporting.products.last-search-terms />
        </template>

        <!-- Top Sources Section -->
        <template v-else>
            <div class="box-shadow relative flex-1 rounded bg-white p-4 dark:bg-gray-900">
                <!-- Header -->
                <div class="mb-4 flex items-center justify-between">
                    <p class="text-base font-semibold text-diidsInk/70 dark:text-white">
                        @lang('admin::app.reporting.traffic.index.top-sources')
                    </p>

                    <a
                        href="{{ route('admin.reporting.traffic.view', ['type' => 'top-sources']) }}"
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
                                <p class="truncate dark:text-white">
                                    @{{ source.source }}
                                </p>

                                <div class="flex items-center gap-5">
                                    <div class="relative h-2 w-full bg-slate-100">
                                        <div
                                            class="absolute left-0 h-2 bg-emerald-500"
                                            :style="{ 'width': source.progress + '%' }"
                                        ></div>
                                    </div>

                                    <p class="text-sm font-semibold text-diidsInk/70 dark:text-gray-300">
                                        @{{ source.count }}
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
        app.component('v-reporting-traffic-top-sources', {
            template: '#v-reporting-traffic-top-sources-template',

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

                    filters.type = 'top-sources';

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
