<?php

namespace Webkul\Paystack\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Webkul\Checkout\Contracts\Cart as CartContract;
use Webkul\Checkout\Facades\Cart;
use Webkul\Checkout\Repositories\CartRepository;
use Webkul\Customer\Repositories\CustomerPaymentMethodRepository;
use Webkul\Sales\Repositories\InvoiceRepository;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\OrderTransactionRepository;
use Webkul\Sales\Transformers\OrderResource;
use Webkul\Paystack\Payment\Paystack;

class PaystackController extends Controller
{
    /**
     * Paystack API base URL.
     *
     * @var string
     */
    protected string $apiUrl = 'https://api.paystack.co';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected CartRepository $cartRepository,
        protected OrderRepository $orderRepository,
        protected OrderTransactionRepository $orderTransactionRepository,
        protected InvoiceRepository $invoiceRepository,
        protected CustomerPaymentMethodRepository $customerPaymentMethodRepository,
        protected Paystack $paystack,
    ) {}

    /**
     * Redirects to Paystack's hosted checkout, or charges a saved card
     * server-side (no redirect) when the customer selected one at checkout.
     *
     * @return RedirectResponse
     */
    public function redirect()
    {
        if (! $this->paystack->hasValidCredentials()) {
            session()->flash('error', trans('paystack::app.response.provide-credentials'));

            return redirect()->route('shop.checkout.cart.index');
        }

        $cart = Cart::getCart();

        if (! $cart) {
            session()->flash('error', trans('paystack::app.response.cart-not-found'));

            return redirect()->route('shop.checkout.cart.index');
        }

        if (
            auth()->guard('customer')->check()
            && session('paystack_saved_card_id')
        ) {
            return $this->chargeSavedCard($cart, session('paystack_saved_card_id'));
        }

        try {
            $reference = (string) Str::uuid();

            $currency = $this->chargeCurrency($cart);

            $amountInSubunit = $this->chargeAmount($cart, $currency);

            $email = $cart->customer_email ?: $cart->billing_address?->email;

            $response = Http::withToken($this->paystack->getSecretKey())
                ->post("{$this->apiUrl}/transaction/initialize", [
                    'email' => $email,
                    'amount' => $amountInSubunit,
                    'currency' => $currency,
                    'reference' => $reference,
                    'callback_url' => route('paystack.payment.callback'),
                    'metadata' => [
                        'cart_id' => $cart->id,
                    ],
                ]);

            $result = $response->json();

            if (! ($result['status'] ?? false) || empty($result['data']['authorization_url'] ?? null)) {
                session()->flash('error', trans('paystack::app.response.payment-failed').': '.($result['message'] ?? 'Unknown error'));

                return redirect()->route('shop.checkout.cart.index');
            }

            return redirect($result['data']['authorization_url']);
        } catch (\Exception $e) {
            report($e);

            session()->flash('error', trans('paystack::app.response.payment-failed').': '.$e->getMessage());

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    /**
     * Initializes a Paystack transaction and returns the reference +
     * access_code as JSON for the Paystack Inline (popup) widget, instead
     * of redirecting to Paystack's hosted page. Used when the customer has
     * no saved card selected — mirrors redirect() but responds with JSON.
     */
    public function initInline()
    {
        if (! $this->paystack->hasValidCredentials()) {
            return response()->json(['message' => trans('paystack::app.response.provide-credentials')], 422);
        }

        $cart = Cart::getCart();

        if (! $cart) {
            return response()->json(['message' => trans('paystack::app.response.cart-not-found')], 422);
        }

        try {
            $reference = (string) Str::uuid();

            $currency = $this->chargeCurrency($cart);

            $amountInSubunit = $this->chargeAmount($cart, $currency);

            $email = $cart->customer_email ?: $cart->billing_address?->email;

            $response = Http::withToken($this->paystack->getSecretKey())
                ->post("{$this->apiUrl}/transaction/initialize", [
                    'email' => $email,
                    'amount' => $amountInSubunit,
                    'currency' => $currency,
                    'reference' => $reference,
                    'channels' => ['card', 'bank', 'ussd', 'bank_transfer', 'mobile_money'],
                    'metadata' => [
                        'cart_id' => $cart->id,
                    ],
                ]);

            $result = $response->json();

            if (! ($result['status'] ?? false) || empty($result['data']['access_code'] ?? null)) {
                return response()->json(['message' => $result['message'] ?? trans('paystack::app.response.payment-failed')], 422);
            }

            return response()->json([
                'reference' => $reference,
                'access_code' => $result['data']['access_code'],
                'public_key' => $this->paystack->getPublicKey(),
                'email' => $email,
                'amount' => $amountInSubunit,
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['message' => trans('paystack::app.response.payment-failed')], 500);
        }
    }

    /**
     * Verifies a transaction reference initiated via Paystack Inline and
     * creates the order, returning JSON instead of a redirect so the
     * popup's JS success callback can call this via fetch() and then
     * navigate the browser itself.
     */
    public function verifyInline(Request $request)
    {
        $reference = $request->input('reference');

        if (! $reference) {
            return response()->json(['message' => trans('paystack::app.response.invalid-reference')], 422);
        }

        try {
            $data = $this->verifyTransaction($reference);

            if (! $data || ($data['status'] ?? null) !== 'success') {
                return response()->json(['message' => trans('paystack::app.response.payment-failed')], 422);
            }

            $cartId = $data['metadata']['cart_id'] ?? null;

            if (! $cartId) {
                return response()->json(['message' => trans('paystack::app.response.cart-not-found')], 422);
            }

            $cart = $this->cartRepository->find($cartId);

            if (! $cart || ! $cart->is_active) {
                if ($existing = $this->findExistingOrderRedirect($reference)) {
                    return response()->json(['success' => true, 'redirect_url' => $existing->getTargetUrl()]);
                }

                return response()->json(['message' => trans('paystack::app.response.cart-processed')], 422);
            }

            $expectedAmount = $this->chargeAmount($cart, $this->chargeCurrency($cart));

            if (abs(($data['amount'] ?? 0) - $expectedAmount) > 1) {
                return response()->json(['message' => trans('paystack::app.response.amount-mismatch')], 422);
            }

            $redirect = $this->handleSuccessfulPayment($cart, $data);

            return response()->json(['success' => true, 'redirect_url' => $redirect->getTargetUrl()]);
        } catch (\Exception $e) {
            report($e);

            return response()->json(['message' => trans('paystack::app.response.verification-failed')], 500);
        }
    }

    /**
     * Handle the browser redirect back from Paystack's hosted checkout.
     *
     * @return RedirectResponse
     */
    public function callback()
    {
        $reference = request()->query('reference');

        if (! $reference) {
            session()->flash('error', trans('paystack::app.response.invalid-reference'));

            return redirect()->route('shop.checkout.cart.index');
        }

        try {
            $data = $this->verifyTransaction($reference);

            if (! $data) {
                session()->flash('error', trans('paystack::app.response.verification-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            if (($data['status'] ?? null) !== 'success') {
                session()->flash('error', trans('paystack::app.response.payment-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $cartId = $data['metadata']['cart_id'] ?? null;

            if (! $cartId) {
                session()->flash('error', trans('paystack::app.response.cart-not-found'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $cart = $this->cartRepository->find($cartId);

            if (! $cart || ! $cart->is_active) {
                if ($existing = $this->findExistingOrderRedirect($reference)) {
                    return $existing;
                }

                session()->flash('error', trans('paystack::app.response.cart-processed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            $expectedAmount = $this->chargeAmount($cart, $this->chargeCurrency($cart));

            if (abs(($data['amount'] ?? 0) - $expectedAmount) > 1) {
                session()->flash('error', trans('paystack::app.response.amount-mismatch'));

                return redirect()->route('shop.checkout.cart.index');
            }

            return $this->handleSuccessfulPayment($cart, $data);
        } catch (\Exception $e) {
            report($e);

            session()->flash('error', trans('paystack::app.response.verification-failed').': '.$e->getMessage());

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    /**
     * Handle Paystack's asynchronous webhook (safety net for lost callback
     * redirects — the customer may close their browser before the redirect
     * back from Paystack's hosted page completes).
     *
     * @return Response
     */
    public function webhook(Request $request)
    {
        $secretKey = $this->paystack->getSecretKey();

        $signature = $request->header('x-paystack-signature');

        if (! $secretKey || ! $signature || ! hash_equals(hash_hmac('sha512', $request->getContent(), $secretKey), $signature)) {
            return response('Invalid signature', 400);
        }

        $payload = $request->json()->all();

        if (($payload['event'] ?? null) !== 'charge.success') {
            return response('', 200);
        }

        $data = $payload['data'] ?? [];

        $reference = $data['reference'] ?? null;

        $cartId = $data['metadata']['cart_id'] ?? null;

        if (! $reference || ! $cartId) {
            return response('', 200);
        }

        if ($this->orderTransactionRepository->findOneWhere(['transaction_id' => $reference])) {
            return response('', 200);
        }

        $cart = $this->cartRepository->find($cartId);

        if (! $cart || ! $cart->is_active) {
            return response('', 200);
        }

        try {
            $this->handleSuccessfulPayment($cart, $data);
        } catch (\Exception $e) {
            report($e);
        }

        return response('', 200);
    }

    /**
     * Handle payment cancellation.
     *
     * @return RedirectResponse
     */
    public function cancel()
    {
        session()->flash('error', trans('paystack::app.response.payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Stash the customer's selected saved card in session, so that when
     * checkout later hits redirect(), it charges the card server-side
     * instead of sending the browser to Paystack's hosted page. Also
     * accepts an empty/null id to clear the selection (e.g. the customer
     * switched to entering a new card instead), and independently records
     * the "save this card for later" checkbox intent.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function selectSavedCard(Request $request)
    {
        if ($request->has('save_card_intent')) {
            if ($request->boolean('save_card_intent')) {
                session(['paystack_save_card_intent' => true]);
            } else {
                session()->forget('paystack_save_card_intent');
            }
        }

        if (! $request->has('customer_payment_method_id')) {
            return response()->json(['success' => true]);
        }

        $id = $request->input('customer_payment_method_id');

        if (! $id) {
            session()->forget('paystack_saved_card_id');

            return response()->json(['success' => true]);
        }

        $savedCard = $this->customerPaymentMethodRepository->findOneWhere([
            'id' => $id,
            'customer_id' => auth()->guard('customer')->id(),
        ]);

        if (! $savedCard) {
            return response()->json(['message' => trans('paystack::app.response.saved-card-not-found')], 404);
        }

        session(['paystack_saved_card_id' => $id]);

        return response()->json(['success' => true]);
    }

    /**
     * Charge a customer's previously saved card via Paystack's
     * charge_authorization endpoint — no redirect to Paystack's hosted
     * page, the browser goes straight from checkout to the success page.
     *
     * @param  int|string  $savedCardId
     * @return RedirectResponse
     */
    protected function chargeSavedCard(CartContract $cart, $savedCardId)
    {
        $savedCard = $this->customerPaymentMethodRepository->findOneWhere([
            'id' => $savedCardId,
            'customer_id' => auth()->guard('customer')->id(),
        ]);

        if (! $savedCard) {
            session()->forget('paystack_saved_card_id');

            session()->flash('error', trans('paystack::app.response.saved-card-not-found'));

            return redirect()->route('shop.checkout.cart.index');
        }

        try {
            $reference = (string) Str::uuid();

            $currency = $this->chargeCurrency($cart);

            $email = $cart->customer_email ?: $cart->billing_address?->email;

            $response = Http::withToken($this->paystack->getSecretKey())
                ->post("{$this->apiUrl}/transaction/charge_authorization", [
                    'authorization_code' => $savedCard->authorization_code,
                    'email' => $email,
                    'amount' => $this->chargeAmount($cart, $currency),
                    'currency' => $currency,
                    'reference' => $reference,
                ]);

            $result = $response->json();

            $data = $result['data'] ?? [];

            if (($data['status'] ?? null) !== 'success') {
                session()->flash('error', trans('paystack::app.response.saved-card-charge-failed'));

                return redirect()->route('shop.checkout.cart.index');
            }

            session()->forget('paystack_saved_card_id');

            return $this->handleSuccessfulPayment($cart, $data);
        } catch (\Exception $e) {
            report($e);

            session()->flash('error', trans('paystack::app.response.saved-card-charge-failed'));

            return redirect()->route('shop.checkout.cart.index');
        }
    }

    /**
     * Verify a transaction reference against Paystack's API.
     *
     * @param  string  $reference
     * @return array|null
     */
    protected function verifyTransaction($reference)
    {
        $response = Http::withToken($this->paystack->getSecretKey())
            ->get("{$this->apiUrl}/transaction/verify/".rawurlencode($reference));

        $result = $response->json();

        if (! ($result['status'] ?? false)) {
            return null;
        }

        return $result['data'] ?? null;
    }

    /**
     * Create the Bagisto order from a successfully verified/charged
     * Paystack transaction. Shared by callback(), webhook(), and the
     * saved-card reuse path so all three record identical data.
     *
     * @return RedirectResponse
     */
    protected function handleSuccessfulPayment(CartContract $cart, array $data)
    {
        if ($existing = $this->findExistingOrderRedirect($data['reference'] ?? null)) {
            return $existing;
        }

        Cart::setCart($cart);

        Cart::collectTotals();

        $orderData = (new OrderResource($cart))->jsonSerialize();

        $authorization = $data['authorization'] ?? [];

        $additional = [
            'paystack_reference' => $data['reference'] ?? null,
            'paystack_status' => $data['status'] ?? null,
            'paystack_amount' => isset($data['amount']) ? $data['amount'] / 100 : null,
            'paystack_currency' => $data['currency'] ?? null,
            'paystack_channel' => $data['channel'] ?? null,
            'paystack_paid_at' => $data['paid_at'] ?? null,
            'paystack_transaction_date' => $data['transaction_date'] ?? null,
            'paystack_authorization_code' => $authorization['authorization_code'] ?? null,
            'paystack_authorization_reusable' => $authorization['reusable'] ?? false,
            'paystack_card_type' => $authorization['card_type'] ?? null,
            'paystack_last4' => $authorization['last4'] ?? null,
            'paystack_exp_month' => $authorization['exp_month'] ?? null,
            'paystack_exp_year' => $authorization['exp_year'] ?? null,
            'paystack_bank' => $authorization['bank'] ?? null,
        ];

        $orderData['payment']['additional'] = $additional;

        $order = $this->orderRepository->create($orderData);

        $this->orderRepository->update(['status' => 'processing'], $order->id);

        if ($order->canInvoice()) {
            $invoiceData = [
                'order_id' => $order->id,
            ];

            foreach ($order->items as $item) {
                $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
            }

            $invoice = $this->invoiceRepository->create($invoiceData);

            $this->orderTransactionRepository->create([
                'transaction_id' => $data['reference'] ?? null,
                'status' => $data['status'] ?? null,
                'type' => $order->payment->method,
                'payment_method' => $order->payment->method,
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
                'amount' => $order->base_grand_total,
                'data' => json_encode($additional),
            ]);
        }

        $this->maybeSaveCard($order, $data, $authorization);

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

        session()->flash('success', trans('paystack::app.response.payment-success'));

        return redirect()->route('shop.checkout.onepage.success');
    }

    /**
     * Persist a new saved card for the customer when all of: they're
     * authenticated, opted in via the "save this card" checkbox, and the
     * transaction returned a reusable card-channel authorization.
     */
    protected function maybeSaveCard($order, array $data, array $authorization): void
    {
        if (! auth()->guard('customer')->check()) {
            return;
        }

        if (! session('paystack_save_card_intent')) {
            return;
        }

        session()->forget('paystack_save_card_intent');

        if (
            empty($authorization['authorization_code'])
            || ($authorization['reusable'] ?? false) !== true
            || ($data['channel'] ?? null) !== 'card'
        ) {
            return;
        }

        $customerId = auth()->guard('customer')->id();

        $duplicate = $this->customerPaymentMethodRepository->findOneWhere([
            'customer_id' => $customerId,
            'last4' => $authorization['last4'] ?? null,
            'exp_month' => $authorization['exp_month'] ?? null,
            'exp_year' => $authorization['exp_year'] ?? null,
            'bank' => $authorization['bank'] ?? null,
        ]);

        if ($duplicate) {
            return;
        }

        $isFirstCard = ! $this->customerPaymentMethodRepository->findOneWhere(['customer_id' => $customerId]);

        $this->customerPaymentMethodRepository->create([
            'customer_id' => $customerId,
            'gateway' => 'paystack',
            'authorization_code' => $authorization['authorization_code'],
            'card_type' => $authorization['card_type'] ?? null,
            'last4' => $authorization['last4'] ?? null,
            'exp_month' => $authorization['exp_month'] ?? null,
            'exp_year' => $authorization['exp_year'] ?? null,
            'bank' => $authorization['bank'] ?? null,
            'is_default' => $isFirstCard,
        ]);
    }

    /**
     * Idempotency guard: if an order was already created for this
     * reference (e.g. the webhook beat the callback redirect, or vice
     * versa), redirect straight to the success page instead of
     * re-creating the order.
     *
     * @param  string|null  $reference
     * @return RedirectResponse|null
     */
    protected function findExistingOrderRedirect($reference)
    {
        if (! $reference) {
            return null;
        }

        $transaction = $this->orderTransactionRepository->findOneWhere(['transaction_id' => $reference]);

        if (! $transaction) {
            return null;
        }

        session()->flash('order_id', $transaction->order_id);

        session()->flash('success', trans('paystack::app.response.payment-success'));

        return redirect()->route('shop.checkout.onepage.success');
    }

    /**
     * The currency to charge a given cart in.
     *
     * Paystack prices a Nigerian card in naira at 1.5% capped at NGN 2,000,
     * but treats a dollar charge as international at 3.9% uncapped - so on
     * a NGN 28,000 order the same sale costs about NGN 420 in fees rather
     * than NGN 1,090. Nigerian customers are therefore charged naira and
     * everyone else dollars.
     *
     * The decision follows the shipping country, not the browsing
     * currency: where the goods are going is a fact about the order, while
     * the currency someone is viewing in is a display preference they may
     * have changed on a whim.
     */
    protected function chargeCurrency($cart): string
    {
        $country = strtoupper((string) (
            $cart->shipping_address?->country
            ?: $cart->billing_address?->country
        ));

        return $country === 'NG' ? 'NGN' : 'USD';
    }

    /**
     * The order total in the charge currency, in minor units.
     *
     * Naira has no subunit in practice - Paystack still expects kobo, so
     * the same x100 applies to both.
     */
    protected function chargeAmount($cart, string $currency): int
    {
        return (int) round(core()->convertPrice($cart->base_grand_total, $currency) * 100);
    }
}
