<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Cleaner;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->string('q'));

        if (mb_strlen($q) < 2) {
            return $this->ok(['bookings' => [], 'customers' => [], 'cleaners' => []]);
        }

        $like = '%'.$q.'%';

        $bookings = Booking::query()
            ->with('customer.user')
            ->where('reference', 'like', $like)
            ->orWhereHas('customer.user', fn ($u) => $u->where('name', 'like', $like))
            ->limit(5)
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'reference' => $b->reference,
                'customer' => $b->customer?->user?->name,
                'scheduled_date' => $b->scheduled_date->toDateString(),
                'status' => $b->status->value,
            ]);

        $customers = Customer::query()
            ->with('user')
            ->whereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
            ->limit(5)
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->user?->name,
                'email' => $c->user?->email,
            ]);

        $cleaners = Cleaner::query()
            ->with('user')
            ->whereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->limit(5)
            ->get()
            ->map(fn (Cleaner $c) => [
                'id' => $c->id,
                'name' => $c->user?->name,
                'verification_status' => $c->verification_status->value,
            ]);

        return $this->ok([
            'bookings' => $bookings,
            'customers' => $customers,
            'cleaners' => $cleaners,
        ]);
    }
}
