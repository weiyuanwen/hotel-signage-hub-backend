<?php

namespace App\Http\Controllers\Api\Cms;

use App\Domains\Staff\StaffService;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(private StaffService $staff) {}

    public function index(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('staff.view'), 403);

        $users = $this->staff->list($hotel)->map(fn (User $user) => $this->present($user));

        return response()->json(['data' => $users->values()]);
    }

    public function store(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()?->can('staff.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:receptionist,hotel-manager,super-admin'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $user = $this->staff->create($actor, $hotel, $data);

        return response()->json(['data' => $this->present($user)], 201);
    }

    public function update(Request $request, Hotel $hotel, User $user): JsonResponse
    {
        abort_unless($request->user()?->can('staff.manage'), 403);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'role' => ['sometimes', 'required', 'in:receptionist,hotel-manager,super-admin'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $member = $this->staff->update($actor, $hotel, $user, $data);

        return response()->json(['data' => $this->present($member)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'roles' => $user->getRoleNames()->values(),
        ];
    }
}
