<?php

namespace App\Policies;

use App\Models\Address;
use App\Models\User;

class AddressPolicy
{
    /**
     * Only the owning customer may manage their own address.
     */
    public function manage(User $user, Address $address): bool
    {
        return $user->isCustomer()
            && $user->customer !== null
            && $address->customer_id === $user->customer->id;
    }

    public function update(User $user, Address $address): bool
    {
        return $this->manage($user, $address);
    }

    public function delete(User $user, Address $address): bool
    {
        return $this->manage($user, $address);
    }
}
