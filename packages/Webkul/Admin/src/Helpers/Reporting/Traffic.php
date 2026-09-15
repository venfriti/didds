<?php

namespace Webkul\Admin\Helpers\Reporting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Analytics\Repositories\PageViewRepository;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Sales\Repositories\OrderRepository;

class Traffic extends AbstractReporting
{
    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(
        protected PageViewRepository $pageViewRepository,
        protected CartRepository $cartRepository,
        protected OrderRepository $orderRepository
    ) {
        parent::__construct();
    }

    /**
     * Retrieves total page views and their progress.
     */
    public function getTotalPageViewsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalPageViews($this->lastStartDate, $this->lastEndDate),
            'current' => $current = $this->getTotalPageViews($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total page views for a given period.
     */
    public function getTotalPageViews($startDate, $endDate): int
    {
        return $this->pageViewRepository
            ->resetModel()
            ->when($this->channelIds, fn ($query) => $query->whereIn('channel_id', $this->channelIds))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Retrieves the top pages by view count.
     */
    public function getTopPages($limit = null): Collection
    {
        return $this->pageViewRepository
            ->resetModel()
            ->select('path')
            ->addSelect(DB::raw('COUNT(*) as count'))
            ->when($this->channelIds, fn ($query) => $query->whereIn('channel_id', $this->channelIds))
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->groupBy('path')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieves the top traffic sources by page-view count.
     */
    public function getTopSources($limit = null): Collection
    {
        return $this->pageViewRepository
            ->resetModel()
            ->select(DB::raw("COALESCE(referrer_source, 'Direct') as source"))
            ->addSelect(DB::raw('COUNT(*) as count'))
            ->when($this->channelIds, fn ($query) => $query->whereIn('channel_id', $this->channelIds))
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->groupBy('source')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();
    }

    /**
     * Retrieves conversion counts (visitors vs. orders) grouped by landing source.
     *
     * "Visitors" here means distinct visitor_ids seen in page_views for the
     * source, within the selected date range. "Orders" means orders whose
     * landing_source (stamped once at first-touch, on cart creation) matches.
     * Both counts are independently scoped to the same date range so the
     * comparison stays meaningful even though the two tables are unrelated
     * beyond the shared source label.
     */
    public function getConversionBySource($limit = null): Collection
    {
        $visitorsBySource = $this->pageViewRepository
            ->resetModel()
            ->select(DB::raw("COALESCE(referrer_source, 'Direct') as source"))
            ->addSelect(DB::raw('COUNT(DISTINCT visitor_id) as visitors'))
            ->when($this->channelIds, fn ($query) => $query->whereIn('channel_id', $this->channelIds))
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereNotNull('visitor_id')
            ->groupBy('source')
            ->get()
            ->keyBy('source');

        $ordersBySource = $this->orderRepository
            ->resetModel()
            ->select('landing_source')
            ->addSelect(DB::raw('COUNT(*) as orders'))
            ->when($this->channelIds, fn ($query) => $query->whereIn('channel_id', $this->channelIds))
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->whereNotNull('landing_source')
            ->groupBy('landing_source')
            ->get()
            ->keyBy('landing_source');

        $sources = $visitorsBySource->keys()->merge($ordersBySource->keys())->unique();

        $records = $sources->map(function ($source) use ($visitorsBySource, $ordersBySource) {
            $visitors = (int) ($visitorsBySource[$source]->visitors ?? 0);

            $orders = (int) ($ordersBySource[$source]->orders ?? 0);

            return (object) [
                'source' => $source,
                'visitors' => $visitors,
                'orders' => $orders,
                'conversion_rate' => $visitors ? round(($orders * 100) / $visitors, 1) : 0,
            ];
        })->sortByDesc('visitors')->values();

        return $limit ? $records->take($limit) : $records;
    }
}
