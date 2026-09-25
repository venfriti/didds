<?php

namespace Webkul\Admin\Mail\Order;

use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Webkul\Admin\Mail\Mailable;
use Webkul\Sales\Contracts\Shipment;

class InventorySourceNotification extends Mailable
{
    /**
     * Create a new message instance.
     */
    public function __construct(public Shipment $shipment) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $inventory = $this->shipment->inventory_source;

        /**
         * The store's contact address is copied in as well, so whoever
         * handles customer queries sees dispatches too.
         */
        $contactEmail = core()->getConfigData('emails.configure.email_settings.contact_email');

        $cc = $contactEmail && strcasecmp($contactEmail, (string) $inventory->contact_email) !== 0
            ? [new Address($contactEmail, (string) core()->getConfigData('emails.configure.email_settings.contact_name'))]
            : [];

        return new Envelope(
            to: [
                new Address(
                    $inventory->contact_email,
                    $inventory->contact_name
                ),
            ],
            cc: $cc,
            subject: trans('admin::app.emails.orders.inventory-source.subject'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'admin::emails.orders.inventory-source',
        );
    }
}
