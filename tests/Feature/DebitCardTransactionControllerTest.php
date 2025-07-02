<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\DebitCardTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardTransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected DebitCard $debitCard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->debitCard = DebitCard::factory()->create([
            'user_id' => $this->user->id
        ]);
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCardTransactions()
    {
        DebitCardTransaction::factory()->count(2)->create(['debit_card_id' => $this->debitCard->id]);

        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $this->debitCard->id);
        $response->assertOk()->assertJsonCount(2);
    }

    public function testCustomerCannotSeeAListOfDebitCardTransactionsOfOtherCustomerDebitCard()
    {
        $otherCard = DebitCard::factory()->create();
        DebitCardTransaction::factory()->create(['debit_card_id' => $otherCard->id]);

        $response = $this->getJson('/api/debit-card-transactions?debit_card_id=' . $otherCard->id);
        $response->assertForbidden();
    }

    public function testCustomerCanCreateADebitCardTransaction()
    {
        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => 10000,
            'currency_code' => 'IDR'
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('debit_card_transactions', ['amount' => 10000]);
    }

    public function testCustomerCannotCreateADebitCardTransactionToOtherCustomerDebitCard()
    {
        $otherCard = DebitCard::factory()->create();

        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $otherCard->id,
            'amount' => 10000,
            'currency_code' => 'IDR'
        ]);

        $response->assertForbidden();
    }

    public function testCustomerCanSeeADebitCardTransaction()
    {
        $txn = DebitCardTransaction::factory()->create(['debit_card_id' => $this->debitCard->id]);

        $response = $this->getJson("/api/debit-card-transactions/{$txn->id}");
        $response->assertOk()->assertJsonFragment(['amount' => $txn->amount]);
    }

    public function testCustomerCannotSeeADebitCardTransactionAttachedToOtherCustomerDebitCard()
    {
        $otherCard = DebitCard::factory()->create();
        $txn = DebitCardTransaction::factory()->create(['debit_card_id' => $otherCard->id]);

        $response = $this->getJson("/api/debit-card-transactions/{$txn->id}");
        $response->assertForbidden();
    }

    // Extra bonus for extra tests :)
    public function testCannotCreateTransactionWithInvalidAmountOrCurrency()
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $this->debitCard->id,
            'amount' => -1000,
            'currency_code' => 'BTC',
        ]);

        $response->assertStatus(422);
    }

    public function testCannotCreateTransactionToInactiveCard()
    {
        $inactiveCard = DebitCard::factory()->create([
            'user_id' => $this->user->id,
            'disabled_at' => now(),
        ]);

        $this->actingAs($this->user);

        $response = $this->postJson('/api/debit-card-transactions', [
            'debit_card_id' => $inactiveCard->id,
            'amount' => 1000,
            'currency_code' => 'IDR',
        ]);

        $response->assertStatus(400);
    }


}
