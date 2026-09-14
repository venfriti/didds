<?php

namespace Webkul\Admin\DataGrids\Sales;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Webkul\DataGrid\DataGrid;

/**
 * Lists every cart still in progress (not yet converted to an order) so
 * admins can see checkout attempts as they happen - including customers
 * who opened the Paystack popup and abandoned it - rather than waiting
 * for the 2-day-delayed Abandoned Carts report to pick them up.
 */
class InProgressCartDataGrid extends DataGrid
{
    /**
     * Prepare query builder.
     *
     * @return Builder
     */
    public function prepareQueryBuilder()
    {
        $queryBuilder = DB::table('cart')
            ->leftJoin('cart_payment', 'cart.id', '=', 'cart_payment.cart_id')
            ->leftJoin('addresses', function ($join) {
                $join->on('cart.id', '=', 'addresses.cart_id')
                    ->where('addresses.address_type', '=', 'cart_shipping');
            })
            ->select(
                'cart.id as id',
                'cart.customer_email as customer_email',
                'cart.customer_first_name as customer_first_name',
                'cart.customer_last_name as customer_last_name',
                'cart.items_count as items_count',
                'cart.base_grand_total as base_grand_total',
                'cart.checkout_method as checkout_method',
                'cart.created_at as created_at',
                'cart.updated_at as updated_at',
                'cart_payment.method as payment_method',
                'addresses.first_name as shipping_first_name',
                'addresses.last_name as shipping_last_name'
            )
            ->where('cart.is_active', 1)
            ->whereNotNull('cart.items_count')
            ->where('cart.items_count', '>', 0);

        $this->addFilter('id', 'cart.id');
        $this->addFilter('customer_email', 'cart.customer_email');
        $this->addFilter('payment_method', 'cart_payment.method');
        $this->addFilter('created_at', 'cart.created_at');
        $this->addFilter('updated_at', 'cart.updated_at');

        return $queryBuilder;
    }

    /**
     * Add Columns.
     *
     * @return void
     */
    public function prepareColumns()
    {
        $this->addColumn([
            'index' => 'id',
            'label' => trans('admin::app.sales.in-progress-carts.index.datagrid.id'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'customer_email',
            'label' => trans('admin::app.sales.in-progress-carts.index.datagrid.email'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'closure' => function ($row) {
                $name = trim(($row->customer_first_name ?: $row->shipping_first_name).' '.($row->customer_last_name ?: $row->shipping_last_name));

                $email = $row->customer_email ?: trans('admin::app.sales.in-progress-carts.index.datagrid.guest');

                return $name ? "{$name} ({$email})" : $email;
            },
        ]);

        $this->addColumn([
            'index' => 'items_count',
            'label' => trans('admin::app.sales.in-progress-carts.index.datagrid.total-items'),
            'type' => 'integer',
            'sortable' => true,
        ]);

        $this->addColumn([
            'index' => 'base_grand_total',
            'label' => trans('admin::app.sales.in-progress-carts.index.datagrid.total'),
            'type' => 'string',
            'sortable' => true,
            'closure' => fn ($row) => core()->formatBasePrice($row->base_grand_total),
        ]);

        $this->addColumn([
            'index' => 'payment_method',
            'label' => trans('admin::app.sales.in-progress-carts.index.datagrid.stage'),
            'type' => 'string',
            'searchable' => true,
            'filterable' => true,
            'sortable' => true,
            'closure' => function ($row) {
                if ($row->payment_method) {
                    return trans('admin::app.sales.in-progress-carts.index.datagrid.payment-initiated', ['method' => ucfirst($row->payment_method)]);
                }

                return $row->shipping_first_name
                    ? trans('admin::app.sales.in-progress-carts.index.datagrid.address-entered')
                    : trans('admin::app.sales.in-progress-carts.index.datagrid.browsing');
            },
        ]);

        $this->addColumn([
            'index' => 'updated_at',
            'label' => trans('admin::app.sales.in-progress-carts.index.datagrid.updated-at'),
            'type' => 'datetime',
            'searchable' => false,
            'filterable' => true,
            /**
             * A datetime column rejects date_range - the grid throws
             * InvalidColumnException and the whole page 500s.
             */
            'filterable_type' => 'datetime_range',
            'sortable' => true,
        ]);
    }
}
