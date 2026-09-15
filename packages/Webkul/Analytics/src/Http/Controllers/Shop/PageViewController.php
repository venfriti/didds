<?php

namespace Webkul\Analytics\Http\Controllers\Shop;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Webkul\Analytics\Repositories\PageViewRepository;
use Webkul\Analytics\Support\ReferrerClassifier;

class PageViewController extends Controller
{
    const VISITOR_COOKIE = 'analytics_visitor_id';

    public function __construct(
        protected PageViewRepository $pageViewRepository
    ) {}

    /**
     * Ingest a single page-view beacon from the storefront.
     *
     * Deliberately fails open: any error here must never surface to the
     * visitor or affect the page they're on, since this is a fire-and-forget
     * beacon call that happens after the page has already rendered.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $path = (string) $request->input('path');

            if ($path === '') {
                return response()->json([], 204);
            }

            $visitorId = $request->cookie(self::VISITOR_COOKIE) ?: (string) Str::uuid();

            $referrer = $request->input('referrer');

            $utmSource = $request->input('utm_source');

            $referrerSource = ReferrerClassifier::classify($referrer, $request->getHost(), $utmSource);

            $this->pageViewRepository->create([
                'visitor_id' => $visitorId,
                'channel_id' => core()->getCurrentChannel()->id ?? null,
                'url' => Str::limit((string) $request->input('url'), 2048, ''),
                'path' => Str::limit($path, 255, ''),
                'referrer_source' => Str::limit((string) $referrerSource, 255, '') ?: null,
                'referrer_host' => Str::limit((string) ReferrerClassifier::parseHost($referrer), 255, '') ?: null,
                'utm_source' => Str::limit((string) $request->input('utm_source'), 255, '') ?: null,
                'utm_medium' => Str::limit((string) $request->input('utm_medium'), 255, '') ?: null,
                'utm_campaign' => Str::limit((string) $request->input('utm_campaign'), 255, '') ?: null,
                'created_at' => now(),
            ]);

            if ($referrerSource && $referrerSource !== 'Direct' && ! $request->session()->has('landing_source')) {
                $request->session()->put('landing_source', $referrerSource);
            }

            return response()->json([], 204)
                ->cookie(self::VISITOR_COOKIE, $visitorId, 60 * 24 * 365 * 2, null, null, false, false, false, 'Lax');
        } catch (\Throwable $e) {
            report($e);

            return response()->json([], 204);
        }
    }
}
