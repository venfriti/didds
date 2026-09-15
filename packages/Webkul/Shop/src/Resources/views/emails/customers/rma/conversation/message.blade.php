@component('shop::emails.layout')
    <div style="margin-bottom: 34px;">
        <span style="font-size: 22px; font-weight: 600; color: #0A0A0A;">
            @lang('shop::app.rma.mail.seller-conversation.title')
        </span><br>

        <!-- Heading -->
        <p style="font-size: 16px; color: #4B5563; line-height: 24px;">
            @lang('shop::app.rma.mail.customer-conversation.heading', ['name' => $rmaMessage->rma->order->customer->name])👋
        </p>

        <!-- Conversation -->
        <p style="font-size: 16px; color: #6B7280; line-height: 24px;">
            @lang('shop::app.rma.mail.customer-conversation.quotes')
        </p>

        <div style="margin-bottom: 20px; margin-top: 20px; display: flex; flex-direction: row; justify-content: space-between;">
            <div style="line-height: 25px;">
                <!-- Message -->
                <div style="font-size: 16px; font-weight: bold; color: #0A0A0A;">
                    @lang('shop::app.rma.mail.customer-conversation.message')
                </div>

                <div style="font-size: 16px; color: #6B7280;">
                    {{ $rmaMessage->message }}
                </div>
            </div>
        </div>
    </div>
@endcomponent