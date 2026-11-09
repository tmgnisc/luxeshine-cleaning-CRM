<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreAddressRequest;
use App\Http\Requests\Customer\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $addresses = $this->customer($request)->addresses()->orderByDesc('is_default')->latest()->get();

        return $this->ok(AddressResource::collection($addresses));
    }

    public function store(StoreAddressRequest $request): JsonResponse
    {
        $customer = $this->customer($request);
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

    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);
        $data = $request->validated();

        DB::transaction(function () use ($address, $data): void {
            if (($data['is_default'] ?? false) === true) {
                $address->customer->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
            }
            $address->update($data);
        });

        return $this->ok(new AddressResource($address->fresh()), 'Address updated.');
    }

    public function destroy(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);
        $wasDefault = $address->is_default;
        $customer = $address->customer;

        $address->delete();

        if ($wasDefault) {
            $next = $customer->addresses()->latest()->first();
            $next?->update(['is_default' => true]);
        }

        return $this->message('Address removed.');
    }

    public function setDefault(Request $request, Address $address): JsonResponse
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($address): void {
            $address->customer->addresses()->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        });

        return $this->ok(new AddressResource($address->fresh()), 'Default address updated.');
    }

    private function customer(Request $request): Customer
    {
        $customer = $request->user()->customer;

        abort_if($customer === null, 403, 'No customer profile found for this account.');

        return $customer;
    }

    private function authorizeAddress(Request $request, Address $address): void
    {
        abort_unless(
            $request->user()->customer !== null && $address->customer_id === $request->user()->customer->id,
            403,
            'This action is unauthorized.',
        );
    }
}
