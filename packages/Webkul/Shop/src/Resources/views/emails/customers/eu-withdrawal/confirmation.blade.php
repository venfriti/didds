@php
    $statusIntroKey = 'shop::app.eu_withdrawal.emails.confirmation.intro_'.$withdrawal->status;
    $statusIntroKey = trans()->has($statusIntroKey) ? $statusIntroKey : 'shop::app.eu_withdrawal.emails.confirmation.intro';

    $titleKey = 'shop::app.eu_withdrawal.emails.confirmation.title_'.$withdrawal->status;
    $titleKey = trans()->has($titleKey) ? $titleKey : 'shop::app.eu_withdrawal.emails.confirmation.title';
@endphp

@component('shop::emails.layout')
    <div style="margin-bottom: 34px;">
        <span style="font-size: 22px;font-weight: 600;color: #0A0A0A;">
            @lang($titleKey)
        </span> <br>

        <p style="font-size: 16px;color: #4B5563;line-height: 24px;">
            @lang('shop::app.emails.dear', ['customer_name' => $withdrawal->customer_email]), 👋
        </p>

        <p style="font-size: 16px;color: #4B5563;line-height: 24px;">
            @lang($statusIntroKey, [
                'order_id' => $withdrawal->order->increment_id ?? $withdrawal->order_id,
            ])
        </p>
    </div>

    <div style="font-size: 20px;font-weight: 600;color: #0A0A0A;">
        @lang('shop::app.eu_withdrawal.emails.confirmation.summary')
    </div>

    <table
        cellpadding="0"
        cellspacing="0"
        border="0"
        style="width: 100%;margin-top: 20px;margin-bottom: 40px;border-collapse: collapse;"
    >
        <tr>
            <td style="padding: 8px 12px 8px 0;font-size: 14px;font-weight: 600;color: #0A0A0A;width: 180px;vertical-align: top;white-space: nowrap;">
                @lang('shop::app.eu_withdrawal.emails.confirmation.reference')
            </td>

            <td style="padding: 8px 0;font-size: 14px;color: #4B5563;font-family: 'Courier New', monospace;">
                {{ $withdrawal->uuid }}
            </td>
        </tr>

        <tr>
            <td style="padding: 8px 12px 8px 0;font-size: 14px;font-weight: 600;color: #0A0A0A;width: 180px;vertical-align: top;white-space: nowrap;">
                @lang('shop::app.eu_withdrawal.emails.confirmation.received_at')
            </td>

            <td style="padding: 8px 0;font-size: 14px;color: #4B5563;">
                {{ $withdrawal->received_at->copy()->setTimezone('UTC')->format('d M Y, H:i') }} UTC
            </td>
        </tr>

        <tr>
            <td style="padding: 8px 12px 8px 0;font-size: 14px;font-weight: 600;color: #0A0A0A;width: 180px;vertical-align: top;white-space: nowrap;">
                @lang('shop::app.eu_withdrawal.emails.confirmation.order')
            </td>

            <td style="padding: 8px 0;font-size: 14px;color: #4B5563;">
                #{{ $withdrawal->order->increment_id ?? $withdrawal->order_id }}
            </td>
        </tr>

        <tr>
            <td style="padding: 8px 12px 8px 0;font-size: 14px;font-weight: 600;color: #0A0A0A;width: 180px;vertical-align: top;white-space: nowrap;">
                @lang('shop::app.eu_withdrawal.emails.confirmation.email')
            </td>

            <td style="padding: 8px 0;font-size: 14px;color: #4B5563;">
                {{ $withdrawal->customer_email }}
            </td>
        </tr>

        @if ($withdrawal->reason_text)
            <tr>
                <td style="padding: 8px 12px 8px 0;font-size: 14px;font-weight: 600;color: #0A0A0A;width: 180px;vertical-align: top;white-space: nowrap;">
                    @lang('shop::app.eu_withdrawal.emails.confirmation.reason')
                </td>

                <td style="padding: 8px 0;font-size: 14px;color: #4B5563;">
                    {{ $withdrawal->reason_text }}
                </td>
            </tr>
        @endif

        @if ($withdrawal->status === 'declined' && $withdrawal->declined_reason)
            <tr>
                <td style="padding: 8px 12px 8px 0;font-size: 14px;font-weight: 600;color: #B91C1C;width: 180px;vertical-align: top;white-space: nowrap;">
                    @lang('shop::app.eu_withdrawal.emails.confirmation.declined_reason')
                </td>
                
                <td style="padding: 8px 0;font-size: 14px;color: #B91C1C;">
                    {{ $withdrawal->declined_reason }}
                </td>
            </tr>
        @endif
    </table>

    <p style="font-size: 16px;color: #4B5563;line-height: 24px;margin-bottom: 24px;">
        @lang('shop::app.eu_withdrawal.emails.confirmation.refund_notice')
    </p>

    <p style="font-size: 13px;color: #6B7280;line-height: 20px;margin-bottom: 0;">
        @lang('shop::app.eu_withdrawal.emails.confirmation.footer')
    </p>
@endcomponent
