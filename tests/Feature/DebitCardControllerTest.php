<?php

namespace Tests\Feature;

use App\Models\DebitCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DebitCardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Passport::actingAs($this->user);
    }

    public function testCustomerCanSeeAListOfDebitCards()
    {
        DebitCard::factory()->count(2)->create(['user_id' => $this->user->id]);
        DebitCard::factory()->count(1)->create(); // other user

        $response = $this->getJson('/api/debit-cards');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function testCustomerCannotSeeAListOfDebitCardsOfOtherCustomers()
    {
        $otherUser = User::factory()->create();
        DebitCard::factory()->count(3)->create(['user_id' => $otherUser->id]);

        $response = $this->getJson('/api/debit-cards');
        $response->assertOk()->assertJsonCount(0);
    }

    public function testCustomerCanCreateADebitCard()
    {
        $response = $this->postJson('/api/debit-cards', [
            'type' => 'VISA'
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('debit_cards', ['type' => 'VISA', 'user_id' => $this->user->id]);
    }

    public function testCustomerCanSeeASingleDebitCardDetails()
    {
        $card = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson("/api/debit-cards/{$card->id}");
        $response->assertOk()->assertJsonFragment(['id' => $card->id]);
    }

    public function testCustomerCannotSeeASingleDebitCardDetails()
    {
        $otherCard = DebitCard::factory()->create();

        $response = $this->getJson("/api/debit-cards/{$otherCard->id}");
        $response->assertForbidden();
    }

    public function testCustomerCanActivateADebitCard()
    {
        $card = DebitCard::factory()->create(['user_id' => $this->user->id, 'disabled_at' => now()]);

        $response = $this->putJson("/api/debit-cards/{$card->id}", ['is_active' => true]);
        $response->assertOk()->assertJsonFragment(['is_active' => true]);
    }

    public function testCustomerCanDeactivateADebitCard()
    {
        $card = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/debit-cards/{$card->id}", ['is_active' => false]);
        $response->assertOk()->assertJsonFragment(['is_active' => false]);
    }

    public function testCustomerCannotUpdateADebitCardWithWrongValidation()
    {
        $card = DebitCard::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/debit-cards/{$card->id}", ['is_active' => 'not_boolean']);
        $response->assertStatus(422);
    }

    public function testCustomerCanDeleteADebitCard()
    {
        // delete api/debit-cards/{debitCard}
    }

    public function testCustomerCannotDeleteADebitCardWithTransaction()
    {
        // delete api/debit-cards/{debitCard}
    }

    // Extra bonus for extra tests :)
}
