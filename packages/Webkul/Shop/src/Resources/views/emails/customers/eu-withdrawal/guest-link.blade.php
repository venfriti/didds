@component('shop::emails.layout')
    <div style="margin-bottom: 34px;">
        <span style="font-size: 22px;font-weight: 600;color: #0A0A0A;">
            @lang('shop::app.eu_withdrawal.emails.guest_link.title')
        </span> <br>

        <p style="font-size: 16px;color: #4B5563;line-height: 24px;">
            @lang('shop::app.emails.dear', ['customer_name' => $toEmail]), 👋
        </p>

        <p style="font-size: 16px;color: #4B5563;line-height: 24px;">
            @lang('shop::app.eu_withdrawal.emails.guest_link.intro', ['order_id' => $orderIncrementId])
        </p>
    </div>

    <div style="margin-bottom: 40px;">
        <a
            href="{{ $signedUrl }}"
            style="padding: 16px 45px;justify-content: center;align-items: center;gap: 10px;border-radius: 2px;background: #0A0A0A;color: #FFFFFF;text-decoration: none;text-transform: uppercase;font-weight: 700;display: inline-block;"
        >
            @lang('shop::app.eu_withdrawal.emails.guest_link.button')
        </a>
    </div>

    <p style="font-size: 13px;color: #6B7280;line-height: 20px;margin-bottom: 8px;">
        @lang('shop::app.eu_withdrawal.emails.guest_link.expiry')
    </p>

    <p style="font-size: 12px;color: #6B7280;line-height: 18px;word-break: break-all;margin-bottom: 0;">
        {{ $signedUrl }}
    </p>
@endcomponent
