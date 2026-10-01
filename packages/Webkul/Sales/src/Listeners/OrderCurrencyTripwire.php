<?php

namespace Webkul\Sales\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Webkul\Sales\Contracts\Order as OrderContract;
use Webkul\Sales\Models\OrderComment;
use Webkul\Sales\Support\OrderCurrencyAudit;

/**
 * Checks every new order for mixed currencies, whatever created it.
 *
 * The cart and OrderResource already prevent a mixed order; this is the
 * alarm for a path nobody has thought of yet. A failure is logged, noted
 * on the order where staff will see it, and mailed to the admin at once,
 * so it is caught on the first order rather than by a customer.
 */
class OrderCurrencyTripwire
{
    public function __construct(protected OrderCurrencyAudit $audit) {}

    public function handle(OrderContract $order): void
    {
        try {
            $problems = $this->audit->problems($order, $order->items()->get());
        } catch (\Throwable $e) {
            Log::warning('Order currency check could not run for '.$order->increment_id.': '.$e->getMessage());

            return;
        }

        if (! $problems) {
            return;
        }

        Log::critical('Order '.$order->increment_id.' has mixed-currency amounts', [
            'currency' => $order->order_currency_code,
            'problems' => $problems,
        ]);

        $summary = 'Currency check failed: the amounts on this order are not all in '
            .$order->order_currency_code.'. '.implode('; ', $problems).'.';

        try {
            OrderComment::create([
                'order_id' => $order->id,
                'comment' => $summary,
                'customer_notified' => 0,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Could not note currency problem on order '.$order->increment_id.': '.$e->getMessage());
        }

        $admin = core()->getConfigData('emails.configure.email_settings.admin_email');

        if (! $admin) {
            return;
        }

        try {
            Mail::raw(
                'Order #'.$order->increment_id.' was saved with amounts in more than one currency.'
                ."\n\n".implode("\n", $problems)
                ."\n\nCompare it with the amount and currency on the order's payment record before it is invoiced, shipped or refunded."
                ."\n\nOrder: ".route('admin.sales.orders.view', $order->id),
                fn ($message) => $message->to($admin)->subject('Currency check failed on order #'.$order->increment_id)
            );
        } catch (\Throwable $e) {
            Log::warning('Could not email currency alert for order '.$order->increment_id.': '.$e->getMessage());
        }
    }
}
