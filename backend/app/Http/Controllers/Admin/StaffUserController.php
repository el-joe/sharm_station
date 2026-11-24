<?php

namespace App\Http\Controllers\Admin;

use App\DataTables\StaffUserDataTable;
use App\DTOs\Staff\StaffUserData;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffUserRequest;
use App\Models\User;
use App\Services\StaffUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Users and roles (A5, super admins only): staff accounts and the read-only permissions matrix.
 */
class StaffUserController extends Controller
{
    public function __construct(private readonly StaffUserService $staff) {}

    public function index(): View
    {
        return view('admin.users.index', ['matrix' => UserRole::permissionMatrix(), 'roles' => UserRole::staff()]);
    }

    public function data(Request $request, StaffUserDataTable $table): JsonResponse
    {
        return $table->json(UserRole::tryFrom((string) $request->query('role')), $request->user());
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['role' => UserRole::Agent, 'is_active' => true])]);
    }

    public function store(StaffUserRequest $request): RedirectResponse
    {
        $user = $this->staff->create(StaffUserData::fromRequest($request), $request->user());

        return redirect()->route('admin.users.index')->with('success', "{$user->name} can now sign in to the back office.");
    }

    public function edit(User $user): View
    {
        abort_unless($this->staff->isStaff($user), 404);

        return view('admin.users.form', ['user' => $user]);
    }

    public function update(StaffUserRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->staff->isStaff($user), 404);
        $this->staff->update($user, StaffUserData::fromRequest($request), $request->user());

        return redirect()->route('admin.users.index')->with('success', "{$user->name} saved.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($this->staff->isStaff($user), 404);
        $this->staff->delete($user, $request->user());

        return redirect()->route('admin.users.index')->with('success', 'User deleted.');
    }
}
