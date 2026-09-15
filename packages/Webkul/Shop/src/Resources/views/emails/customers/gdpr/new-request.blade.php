@component('shop::emails.layout')
    <div style="margin-bottom: 34px;">
        <p style="font-weight: bold;font-size: 20px;color: #0A0A0A;line-height: 24px;margin-bottom: 24px">
            @lang('shop::app.emails.dear', ['customer_name' => $gdprRequest->customer->name]), 👋
        </p>
    </div>

    <div style="font-size: 20px;color: #0A0A0A;line-height: 30px;margin-bottom: 34px;">
        <div style="font-weight: bold;font-size: 20px;color: #0A0A0A;line-height: 30px;margin-bottom: 20px !important;">
            {{ $gdprRequest->type == 'update' ? trans('shop::app.emails.customers.gdpr.new-request.update-summary') : trans('shop::app.emails.customers.gdpr.new-request.delete-summary') }}
        </div>
    </div>

    <div style="flex-direction: row;margin-top: 20px;justify-content: space-between;margin-bottom: 20px;">
        <div style="line-height: 25px;font-size: 16px;color: #0A0A0A">
            <span style="font-weight: bold;">
                @lang('shop::app.emails.customers.gdpr.new-request.request-status')</span> {{ $gdprRequest->status }}
        </div>

        <div style="line-height: 25px; font-size: 16px;color: #0A0A0A;">
            <div>
                <span style="font-weight: bold;">
                    @lang('shop::app.emails.customers.gdpr.new-request.request-type')</span> {{ $gdprRequest->type }}
            </div>

            <div>
                <span style="font-weight: bold">
                    @lang('shop::app.emails.customers.gdpr.new-request.message')</span> {{ $gdprRequest->message }}
            </div>
        </div>
    </div>
@endcomponent