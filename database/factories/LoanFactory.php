<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Loan::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(1000, 100000);
        $terms = $this->faker->numberBetween(1, 12);
        return [
            // TODO: Complete factory
            'user_id' => User::factory(),
            'amount' => $amount,
            'terms' => $terms,
            'outstanding_amount' => $amount,
            'currency_code' => 'VND',
            'processed_at' => Carbon::now()->subDays(5)->toDateString(),
            'status' => Loan::STATUS_DUE,
        ];
    }
}
