<?php

namespace Webkul\Admin\Http\Controllers\Sales;

use Illuminate\View\View;
use Webkul\Admin\DataGrids\Sales\InProgressCartDataGrid;
use Webkul\Admin\Http\Controllers\Controller;

class InProgressCartController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index()
    {
        if (request()->ajax()) {
            return datagrid(InProgressCartDataGrid::class)->process();
        }

        return view('admin::sales.in-progress-carts.index');
    }
}
