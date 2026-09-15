<?php

namespace Webkul\Admin\Http\Controllers\Reporting;

use Illuminate\View\View;

class TrafficController extends Controller
{
    /**
     * Request param functions.
     *
     * @var array
     */
    protected $typeFunctions = [
        'top-pages' => 'getTopPagesStats',
        'top-sources' => 'getTopSourcesStats',
        'conversion-by-source' => 'getConversionBySourceStats',
    ];

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        return view('admin::reporting.traffic.index')->with([
            'startDate' => $this->reportingHelper->getStartDate(),
            'endDate' => $this->reportingHelper->getEndDate(),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function view()
    {
        if ($this->validateRequestedType()) {
            abort(404);
        }

        return view('admin::reporting.view')->with([
            'entity' => 'traffic',
            'startDate' => $this->reportingHelper->getStartDate(),
            'endDate' => $this->reportingHelper->getEndDate(),
        ]);
    }
}
