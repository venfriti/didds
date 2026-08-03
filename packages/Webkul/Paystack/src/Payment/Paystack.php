<?php

namespace Webkul\Paystack\Payment;

use Illuminate\Support\Facades\Storage;
use Webkul\Payment\Payment\Payment;

class Paystack extends Payment
{
    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'paystack';

    /**
     * Get redirect URL for Paystack payment.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('paystack.standard.redirect');
    }

    /**
     * Check if payment method is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return parent::isAvailable() && $this->hasValidCredentials();
    }

    /**
     * Get payment method title.
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->getConfigData('title') ?? trans('paystack::app.title');
    }

    /**
     * Get payment method description.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->getConfigData('description') ?? trans('paystack::app.description');
    }

    /**
     * Get payment method image.
     *
     * @return string
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : bagisto_asset('images/paystack.png', 'shop');
    }

    /**
     * Get the Paystack public key.
     *
     * @return string|null
     */
    public function getPublicKey()
    {
        return $this->getConfigData('public_key');
    }

    /**
     * Get the Paystack secret key.
     *
     * @return string|null
     */
    public function getSecretKey()
    {
        return $this->getConfigData('secret_key');
    }

    /**
     * Check if required credentials are configured.
     *
     * @return bool
     */
    public function hasValidCredentials()
    {
        return (bool) ($this->getConfigData('public_key') && $this->getConfigData('secret_key'));
    }
}
