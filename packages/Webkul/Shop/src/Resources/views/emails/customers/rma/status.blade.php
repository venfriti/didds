@component('shop::emails.layout')
    <div style="margin-bottom: 34px;">
        <!-- Title -->
        <span style="font-size: 22px;font-weight: 600;color: #0A0A0A">
            @lang('shop::app.rma.mail.status.title')
        </span>
        
        <br>

        <!-- Customer Name -->
        <p style="font-size: 16px;color: #4B5563;line-height: 24px;">
            @lang('shop::app.rma.mail.status.heading', ['name' => $rma->order->customer->name]),👋
        </p>

        <!-- RMA ID -->
        <p style="font-size: 16px;color: #4B5563;line-height: 24px;">
            @lang('shop::app.rma.mail.status.your-rma-id')
            @lang('shop::app.rma.mail.status.status-change', [
                'id' => '<a href="' . route('shop.customers.account.rma.view', $rma->id) . '" style="color: #0A0A0A; text-decoration: underline; font-weight: bold;">#' . $rma->id . '</a>',
            ])
        </p>

        <!-- Status -->
        <div style="margin-bottom: 20px; margin-top: 20px; display: flex; flex-direction: row; justify-content: space-between;">
            <div style="line-height: 25px;">
                <div style="font-size: 16px; font-weight: bold; color: #0A0A0A;">
                    @lang('shop::app.rma.mail.status.status') :
                    <span style="font-size: 16px; color: #4B5563; line-height: 24px;">
                       {{ $rma->status->title }}
                    </span>
                </div>
            </div>
        </div>
    </div>
@endcomponent