<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesBulkDeletion;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCustomerRequest;
use App\Http\Requests\Admin\UpdateCustomerRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\CustomerListResource;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CleanerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    use ApiResponses, HandlesBulkDeletion, HandlesResourceQueries;

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly CleanerService $accounts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->with('user')
            ->withCount(['bookings', 'addresses'])
            ->withSum(['bookings as lifetime_spend' => fn ($q) => $q->where('status', BookingStatus::Completed->value)], 'total_price')
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            });

        $sortMap = [
            'spend' => 'lifetime_spend',
            'bookings' => 'bookings_count',
            'since' => 'customer_since',
            'lifetime_spend' => 'lifetime_spend',
            'bookings_count' => 'bookings_count',
            'customer_since' => 'customer_since',
            'created_at' => 'created_at',
        ];
        $sort = (string) $request->string('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = $sortMap[ltrim($sort, '-')] ?? 'created_at';
        $query->orderBy($column, $direction);

        return $this->paginated($query->paginate($this->perPage($request)), CustomerListResource::class);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $customer = DB::transaction(function () use ($data): Customer {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Str::password(16),
                'role' => UserRole::Customer,
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'user_id' => $user->id,
                'customer_since' => $data['customer_since'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['addresses'] ?? [] as $i => $address) {
                $customer->addresses()->create([
                    ...$address,
                    'is_default' => $address['is_default'] ?? ($i === 0),
                ]);
            }

            return $customer;
        });

        if ($request->boolean('send_invite')) {
            $this->accounts->sendInvite($customer->user, 'customer');
        }

        $this->activity->log('customer.created', $customer, ['name' => $customer->user->name]);

        return $this->created(new CustomerResource($customer->load(['user', 'addresses'])), 'Customer created.');
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['user', 'addresses'])
            ->loadCount('bookings')
            ->loadSum(['bookings as lifetime_spend' => fn ($q) => $q->where('status', BookingStatus::Completed->value)], 'total_price');

        return $this->ok(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $data = $request->validated();
        $user = $customer->user;

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ], fn ($v) => $v !== null));
        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }
        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }
        $user->save();

        $customer->fill(array_filter([
            'customer_since' => $data['customer_since'] ?? null,
        ], fn ($v) => $v !== null));
        if (array_key_exists('notes', $data)) {
            $customer->notes = $data['notes'];
        }
        $customer->save();

        $this->activity->log('customer.updated', $customer, $customer->getChanges());

        return $this->ok(new CustomerResource($customer->load(['user', 'addresses'])), 'Customer updated.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->user?->update(['is_active' => false]);
        $customer->delete();
        $this->activity->log('customer.deleted', $customer);

        return $this->message('Customer removed.');
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $ids = $this->validateBulkIds($request, 'customers');

        $result = $this->runBulkDelete(
            Customer::with('user')->whereIn('id', $ids)->get(),
            function (Customer $customer): ?string {
                $customer->user?->update(['is_active' => false]);
                $customer->delete();

                return null;
            },
        );

        $this->activity->log('customer.bulk_deleted', null, $result);

        return $this->ok($result, "Removed {$result['deleted']} customer(s).");
    }

    public function bookings(Request $request, Customer $customer): JsonResponse
    {
        $bookings = $customer->bookings()
            ->with(['service', 'cleaner.user'])
            ->latest('scheduled_date')
            ->paginate($this->perPage($request));

        return $this->paginated($bookings, BookingResource::class);
    }
}
