<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\StaffUserService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

class StaffUserDataTable
{
    use RendersRowActions;

    public function __construct(private readonly StaffUserService $staff) {}

    public function json(?UserRole $role, User $viewer): JsonResponse
    {
        return (new EloquentDataTable($this->staff->adminQuery($role)))
            ->editColumn('name', fn (User $user): string => $this->titleCell($user->name.($user->is($viewer) ? ' (you)' : ''), $user->email, route('admin.users.edit', $user)))
            ->editColumn('role', fn (User $user): string => '<span class="chip bg-primary-100 text-primary-700">'.e($user->role?->label()).'</span>')
            ->editColumn('is_active', fn (User $user): string => StatusChip::html($user->isActive() ? 'active' : 'inactive'))
            ->editColumn('created_at', fn (User $user): string => e($user->created_at?->format('j M Y') ?? ''))
            ->addColumn('actions', fn (User $user): string => $this->actions(...array_filter([
                $this->editLink(route('admin.users.edit', $user)),
                $user->is($viewer) ? null : $this->deleteForm(route('admin.users.destroy', $user), "Delete the account of {$user->name}? Their actions stay in the activity log."),
            ])))
            ->filterColumn('name', fn (Builder $query, string $keyword) => $query->where(fn (Builder $query) => $query
                ->where('users.name', 'like', "%{$keyword}%")->orWhere('users.email', 'like', "%{$keyword}%")))
            ->rawColumns(['name', 'role', 'is_active', 'created_at', 'actions'])
            ->toJson();
    }
}
