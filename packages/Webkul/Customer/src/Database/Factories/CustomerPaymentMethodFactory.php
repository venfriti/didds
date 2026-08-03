<?php

namespace Webkul\Customer\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Webkul\Customer\Models\CustomerPaymentMethod;

class CustomerPaymentMethodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = CustomerPaymentMethod::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'gateway' => 'paystack',
            'authorization_code' => 'AUTH_'.$this->faker->uuid,
            'card_type' => $this->faker->randomElement(['visa', 'mastercard', 'verve']),
            'last4' => (string) rand(1000, 9999),
            'exp_month' => rand(1, 12),
            'exp_year' => (int) date('Y') + rand(1, 5),
            'bank' => $this->faker->company,
            'is_default' => false,
        ];
    }
}
