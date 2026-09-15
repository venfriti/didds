@component('shop::emails.layout')
    <!-- Header Section -->
    <div style="margin-bottom: 40px;">
        <h1 style="font-size: 28px; font-weight: 700; color: #0A0A0A; margin: 0 0 20px 0;">
            @lang('shop::app.rma.mail.customer-rma-create.heading')
        </h1>
        
        <p style="font-size: 16px; color: #4B5563; line-height: 26px; margin: 0 0 16px 0;">
            @lang('shop::app.rma.mail.customer-rma-create.hello', ['name' => $rma->order->customer->name]), 👋
        </p>

        <p style="font-size: 16px; color: #4B5563; line-height: 26px; margin: 0;">
            @lang('shop::app.rma.mail.customer-rma-create.greeting', [
                'order_id' =>
                    '<a href="' .
                    route('shop.customers.account.orders.view', $rma->order_id) .
                    '" style="font-weight: 600; color: #0A0A0A; text-decoration: underline;">#' .
                    $rma->order_id .
                    '</a>',
            ])
        </p>
    </div>

    <!-- Summary Section -->
    <div style="margin-bottom: 32px; padding: 24px; background-color: #F5F5F5; border-radius: 8px; border-left: 4px solid #0A0A0A;">
        <h2 style="font-size: 18px; font-weight: 700; color: #0A0A0A; margin: 0 0 20px 0;">
            @lang('shop::app.rma.mail.customer-rma-create.summary')
        </h2>

        <!-- Info Grid -->
        <div style="display: flex; gap: 32px; flex-wrap: wrap;">
            <!-- RMA ID -->
            <div style="flex: 1; min-width: 200px;">
                <p style="font-size: 13px; font-weight: 600; color: #6B7280; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.5px;">
                    @lang('shop::app.rma.mail.customer-rma-create.rma-id')
                </p>
                <p style="font-size: 18px; font-weight: 700; color: #0A0A0A; margin: 0;">
                    {{ $rma->id }}
                </p>
            </div>

            <!-- Order ID -->
            <div style="flex: 1; min-width: 200px;">
                <p style="font-size: 13px; font-weight: 600; color: #6B7280; text-transform: uppercase; margin: 0 0 8px 0; letter-spacing: 0.5px;">
                    @lang('shop::app.rma.mail.customer-rma-create.order-id')
                </p>
                <p style="font-size: 18px; font-weight: 700; color: #0A0A0A; margin: 0;">
                    #{{ $rma->order_id }}
                </p>
            </div>
        </div>
    </div>

    <!-- Additional Information Section -->
    <div style="margin-bottom: 32px; padding: 20px; background-color: #fff5f5; border-radius: 8px;">
        <h3 style="font-size: 16px; font-weight: 700; color: #0A0A0A; margin: 0 0 12px 0;">
            @lang('shop::app.rma.mail.customer-rma-create.additional-information')
        </h3>
        <div style="font-size: 15px; color: #4B5563; line-height: 26px; margin: 0;">
            {{ $rma->information }}
        </div>
    </div>

    <!-- Products Section -->
    <div style="margin-bottom: 40px;">
        <h2 style="font-size: 18px; font-weight: 700; color: #0A0A0A; margin: 0 0 20px 0;">
            @lang('shop::app.rma.mail.customer-rma-create.requested-rma-product')
        </h2>

        <div style="width: 100%; overflow-x: auto;">
            <table style="margin-top: 8px; width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr>
                        @php($lang = Lang::get('shop::app.rma.mail.customer-data-table-heading'))
                        @foreach ($lang as $tableHeading)
                            <th style="background-color: #F5F5F5; padding: 12px 16px; font-weight: 600; color: #4B5563; border-bottom: 1px solid #E5E5E5;">
                                {{ $tableHeading }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @foreach ($rma->items as $key => $item)
                        <tr>
                            <td style="border-bottom: 1px solid #E5E5E5; padding: 12px 16px; color: #4B5563;">
                                {{ $item->orderItem->name }}
                            </td>

                            <td style="border-bottom: 1px solid #E5E5E5; padding: 12px 16px; color: #4B5563;">
                                {{ $item->quantity }}
                            </td>

                            <td style="border-bottom: 1px solid #E5E5E5; padding: 12px 16px; color: #4B5563;">
                                {{ $item->reason->title }}
                            </td>

                            <td style="border-bottom: 1px solid #E5E5E5; padding: 12px 16px; color: #4B5563;">
                                {{ $item->orderItem->sku }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endcomponent
