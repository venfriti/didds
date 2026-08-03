<?php

namespace Webkul\Customer\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Webkul\Customer\Contracts\CustomerPaymentMethod as CustomerPaymentMethodContract;
use Webkul\Customer\Database\Factories\CustomerPaymentMethodFactory;

class CustomerPaymentMethod extends Model implements CustomerPaymentMethodContract
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'gateway',
        'authorization_code',
        'card_type',
        'last4',
        'exp_month',
        'exp_year',
        'bank',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Get the customer that owns this saved payment method.
     */
    public function customer()
    {
        return $this->belongsTo(CustomerProxy::modelClass());
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory
    {
        return CustomerPaymentMethodFactory::new();
    }
}
