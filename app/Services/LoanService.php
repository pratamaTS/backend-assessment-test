<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\ReceivedRepayment;
use App\Models\ScheduledRepayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanService
{
    /**
     * Create a Loan
     *
     * @param  User  $user
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  int  $terms
     * @param  string  $processedAt
     *
     * @return Loan
     */
    public function createLoan(User $user, int $amount, string $currencyCode, int $terms, string $processedAt): Loan
    {
        return DB::transaction(function () use ($user, $amount, $currencyCode, $terms, $processedAt) {
            $loan = Loan::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'terms' => $terms,
                'outstanding_amount' => $amount,
                'currency_code' => $currencyCode,
                'processed_at' => $processedAt,
                'status' => Loan::STATUS_DUE,
            ]);

            // Calculate repayment per term
            $baseAmount = intdiv($amount, $terms);
            $remainder = $amount % $terms;

            for ($i = 1; $i <= $terms; $i++) {
                $installment = $baseAmount + ($i === $terms ? $remainder : 0);

                $loan->scheduledRepayments()->create([
                    'amount' => $installment,
                    'outstanding_amount' => $installment,
                    'currency_code' => $currencyCode,
                    'due_date' => Carbon::parse($processedAt)->addMonths($i),
                    'status' => ScheduledRepayment::STATUS_DUE,
                ]);
            }

            return $loan->fresh('scheduledRepayments');
        });
    }

    /**
     * Repay Scheduled Repayments for a Loan
     *
     * @param  Loan  $loan
     * @param  int  $amount
     * @param  string  $currencyCode
     * @param  string  $receivedAt
     *
     * @return ReceivedRepayment
     */
    public function repayLoan(Loan $loan, int $amount, string $currencyCode, string $receivedAt): ReceivedRepayment
    {
        return DB::transaction(function () use ($loan, $amount, $currencyCode, $receivedAt) {
            // Log received repayment
            $received = $loan->receivedRepayments()->create([
                'amount' => $amount,
                'currency_code' => $currencyCode,
                'received_at' => $receivedAt,
            ]);

            $remaining = $amount;
            foreach ($loan->scheduledRepayments()->where('status', '!=', ScheduledRepayment::STATUS_REPAID)->orderBy('due_date') as $repayment) {
                if ($remaining <= 0) break;

                if ($remaining >= $repayment->outstanding_amount) {
                    $remaining -= $repayment->outstanding_amount;
                    $repayment->update([
                        'outstanding_amount' => 0,
                        'status' => ScheduledRepayment::STATUS_REPAID,
                    ]);
                } else {
                    $repayment->update([
                        'outstanding_amount' => $repayment->outstanding_amount - $remaining,
                        'status' => ScheduledRepayment::STATUS_PARTIAL,
                    ]);
                    $remaining = 0;
                }
            }

            // Update loan
            $loan->refresh();
            $newOutstanding = $loan->scheduledRepayments()->sum('outstanding_amount');
            $loan->update([
                'outstanding_amount' => $newOutstanding,
                'status' => $newOutstanding > 0 ? Loan::STATUS_DUE : Loan::STATUS_REPAID,
            ]);

            return $loan->fresh();
        });
    }
}
