<?php

namespace Tests\Feature\Account;

use App\Models\Address;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTest extends TestCase
{
    use RefreshDatabase;

    private function customerUser(): User
    {
        $user = User::factory()->customer()->create();
        Customer::factory()->create(['user_id' => $user->id]);

        return $user;
    }

    public function test_first_address_is_automatically_default(): void
    {
        $user = $this->customerUser();

        $this->actingAs($user)
            ->postJson('/api/v1/customer/addresses', [
                'label' => 'Home',
                'line1' => '12 Palm St',
                'area' => 'Dubai Marina',
                'city' => 'Dubai',
            ])
            ->assertCreated()
            ->assertJsonPath('data.is_default', true);
    }

    public function test_setting_a_new_default_unsets_the_previous_one(): void
    {
        $user = $this->customerUser();
        $customer = $user->customer;
        $first = Address::factory()->default()->create(['customer_id' => $customer->id]);
        $second = Address::factory()->create(['customer_id' => $customer->id, 'is_default' => false]);

        $this->actingAs($user)
            ->postJson("/api/v1/customer/addresses/{$second->id}/default")
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_a_customer_cannot_modify_another_customers_address(): void
    {
        $user = $this->customerUser();
        $otherCustomer = Customer::factory()->create();
        $foreign = Address::factory()->create(['customer_id' => $otherCustomer->id]);

        $this->actingAs($user)
            ->putJson("/api/v1/customer/addresses/{$foreign->id}", ['label' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_deleting_the_default_address_promotes_another(): void
    {
        $user = $this->customerUser();
        $customer = $user->customer;
        $default = Address::factory()->default()->create(['customer_id' => $customer->id]);
        $other = Address::factory()->create(['customer_id' => $customer->id, 'is_default' => false]);

        $this->actingAs($user)
            ->deleteJson("/api/v1/customer/addresses/{$default->id}")
            ->assertOk();

        $this->assertTrue($other->fresh()->is_default);
    }
}
