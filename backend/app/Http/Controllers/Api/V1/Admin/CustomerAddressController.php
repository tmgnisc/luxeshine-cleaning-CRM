<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreAddressRequest;
use App\Http\Requests\Customer\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CustomerAddressController extends Controller
{
    use ApiResponses;

    public function index(Customer $customer): JsonResponse
    {
        return $this->ok(AddressResource::collection(
            $customer->addresses()->orderByDesc('is_default')->get(),
        ));
    }

    public function store(StoreAddressRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();

        $address = DB::transaction(function () use ($customer, $data): Address {
            $makeDefault = ($data['is_default'] ?? false) || $customer->addresses()->count() === 0;
            if ($makeDefault) {
                $customer->addresses()->update(['is_default' => false]);
            }
            $data['is_default'] = $makeDefault;

            return $customer->addresses()->create($data);
        });

        return $this->created(new AddressResource($address), 'Address added.');
    }

    public function update(UpdateAddressRequest $request, Customer $customer, Address $address): JsonResponse
    {
        $this->ensureOwns($customer, $address);
        $data = $request->validated();

        DB::transaction(function () use ($customer, $address, $data): void {
            if (($data['is_default'] ?? false) === true) {
                $customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }
            $address->update($data);
        });

        return $this->ok(new AddressResource($address->fresh()), 'Address updated.');
    }

    public function destroy(Customer $customer, Address $address): JsonResponse
    {
        $this->ensureOwns($customer, $address);
        $address->delete();

        return $this->message('Address removed.');
    }

    private function ensureOwns(Customer $customer, Address $address): void
    {
        abort_unless($address->customer_id === $customer->id, 404, 'Resource not found.');
    }
}
