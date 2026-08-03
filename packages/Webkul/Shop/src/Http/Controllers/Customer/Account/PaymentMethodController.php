<?php

namespace Webkul\Shop\Http\Controllers\Customer\Account;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Webkul\Customer\Repositories\CustomerPaymentMethodRepository;
use Webkul\Shop\Http\Controllers\Controller;

class PaymentMethodController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected CustomerPaymentMethodRepository $customerPaymentMethodRepository) {}

    /**
     * Payment methods route index page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('shop::customers.account.payment-methods.index')
            ->with('paymentMethods', auth()->guard('customer')->user()->payment_methods);
    }

    /**
     * To change the default saved payment method.
     *
     * @return RedirectResponse
     */
    public function makeDefault(int $id)
    {
        $customer = auth()->guard('customer')->user();

        $defaultPaymentMethod = $customer->payment_methods()->where('is_default', 1)->first();

        $paymentMethodToSetDefault = $customer->payment_methods()->find($id);

        if ($defaultPaymentMethod && $defaultPaymentMethod->id !== $id) {
            $defaultPaymentMethod->update(['is_default' => 0]);
        }

        if ($paymentMethodToSetDefault) {
            $paymentMethodToSetDefault->update(['is_default' => 1]);
        } else {
            session()->flash('success', trans('shop::app.customers.account.payment-methods.index.default-delete'));
        }

        return redirect()->back();
    }

    /**
     * Delete a saved payment method of the current customer.
     *
     * @return RedirectResponse
     */
    public function destroy(int $id)
    {
        $paymentMethod = $this->customerPaymentMethodRepository->findOneWhere([
            'id' => $id,
            'customer_id' => auth()->guard('customer')->id(),
        ]);

        if (! $paymentMethod) {
            abort(404);
        }

        Event::dispatch('customer.payment_methods.delete.before', $id);

        $this->customerPaymentMethodRepository->delete($id);

        Event::dispatch('customer.payment_methods.delete.after', $id);

        session()->flash('success', trans('shop::app.customers.account.payment-methods.index.delete-success'));

        return redirect()->route('shop.customers.account.payment_methods.index');
    }
}
