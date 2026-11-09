<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserRole;
use App\Http\Concerns\ApiResponses;
use App\Http\Concerns\HandlesResourceQueries;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\CleanerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserController extends Controller
{
    use ApiResponses, HandlesResourceQueries;

    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly CleanerService $accounts,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::SuperAdmin->value])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->latest();

        return $this->paginated($query->paginate($this->perPage($request)), UserResource::class);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $hasPassword = ! empty($data['password']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $hasPassword ? $data['password'] : Str::password(16),
            'role' => UserRole::from($data['role']),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        if (! $hasPassword && $request->boolean('send_invite', true)) {
            $this->accounts->sendInvite($user, 'administrator');
        }

        $this->activity->log('admin_user.created', $user, ['role' => $user->role->value]);

        return $this->created(new UserResource($user), 'Admin user created.');
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($user->role->isAdministrative(), 404, 'Resource not found.');

        return $this->ok(new UserResource($user));
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        abort_unless($user->role->isAdministrative(), 404, 'Resource not found.');

        if ($user->id === $request->user()->id && $request->has('is_active') && ! $request->boolean('is_active')) {
            return $this->message('You cannot deactivate your own account.', 422);
        }

        $data = $request->validated();
        if (isset($data['role'])) {
            $data['role'] = UserRole::from($data['role']);
        }

        $user->update($data);
        $this->activity->log('admin_user.updated', $user, $user->getChanges());

        return $this->ok(new UserResource($user), 'Admin user updated.');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($user->role->isAdministrative(), 404, 'Resource not found.');

        if ($user->id === $request->user()->id) {
            return $this->message('You cannot delete your own account.', 422);
        }

        $user->tokens()->delete();
        $user->delete();
        $this->activity->log('admin_user.deleted', $user);

        return $this->message('Admin user removed.');
    }
}
