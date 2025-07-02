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

            // Hitung cicilan rata, dan distribusi sisa
            $base = intdiv($amount, $terms);
            $remainder = $amount % $terms;

            for ($i = 0; $i < $terms; $i++) {
                $installment = $base + ($i === $terms - 1 ? $remainder : 0);

                $loan->scheduledRepayments()->create([
                    'amount' => $installment,
                    'outstanding_amount' => $installment,
                    'currency_code' => $currencyCode,
                    'due_date' => Carbon::parse($processedAt)->addMonths($i + 1),
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
            $remaining = $amount;
            $received = null;

            foreach (
                $loan->scheduledRepayments()
                    ->where('status', '!=', ScheduledRepayment::STATUS_REPAID)
                    ->orderBy('due_date')
                    ->get() as $repayment
            ) {
                if ($remaining <= 0) break;

                $pay = min($repayment->outstanding_amount, $remaining);

                $received = $loan->receivedRepayments()->create([
                    'amount' => $pay,
                    'currency_code' => $currencyCode,
                    'received_at' => $receivedAt,
                ]);

                $remaining -= $pay;

                $newOutstanding = $repayment->outstanding_amount - $pay;

                $repayment->update([
                    'outstanding_amount' => $newOutstanding,
                    'status' => $newOutstanding === 0
                        ? ScheduledRepayment::STATUS_REPAID
                        : ScheduledRepayment::STATUS_PARTIAL,
                ]);
            }

            $loan->refresh();
            $totalOutstanding = $loan->scheduledRepayments()->sum('outstanding_amount');
            $loan->update([
                'outstanding_amount' => $totalOutstanding,
                'status' => $totalOutstanding > 0 ? Loan::STATUS_DUE : Loan::STATUS_REPAID,
            ]);

            return $received;
        });
    }
}
