<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\ScheduledRepayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduledRepaymentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ScheduledRepayment::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        return [
            // TODO: Complete factory
            'loan_id' => Loan::factory(),
            'amount' => $this->faker->numberBetween(1000, 10000),
            'outstanding_amount' => $this->faker->numberBetween(1000, 10000),
            'currency_code' => 'VND',
            'due_date' => $this->faker->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
            'status' => 'due',
        ];
    }
}
