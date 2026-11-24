<?php

namespace App\DataTables;

use App\DataTables\Concerns\RendersRowActions;
use App\DTOs\Customer\CustomerFilterData;
use App\Models\User;
use App\Services\CustomerService;
use App\View\Components\Admin\StatusChip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\EloquentDataTable;

/**
 * Customers list; the free-text search is applied by CustomerFilterData (name, email, phone).
 */
class CustomerDataTable
{
    use RendersRowActions;

    public function __construct(private readonly CustomerService $customers) {}

    public function json(CustomerFilterData $filters): JsonResponse
    {
        return (new EloquentDataTable($this->customers->adminQuery($filters)))
            ->editColumn('name', fn (User $user): string => $this->titleCell($user->name, $user->email, route('admin.customers.show', $user)))
            ->editColumn('phone', fn (User $user): string => e($user->phone ?? '—'))
            ->editColumn('nationality_code', fn (User $user): string => e($user->nationality_code ?? '—'))
            ->editColumn('total_spent', fn (User $user): string => $this->money($user->total_spent ?? 0))
            ->editColumn('created_at', fn (User $user): string => e($user->created_at?->format('j M Y') ?? ''))
            ->editColumn('is_active', fn (User $user): string => StatusChip::html($user->isActive() ? 'active' : 'inactive'))
            ->addColumn('actions', fn (User $user): string => $this->actions($this->editLink(route('admin.customers.show', $user), 'Open')))
            ->orderColumn('total_spent', fn (Builder $query, string $direction) => $query->orderBy('total_spent', $direction === 'asc' ? 'asc' : 'desc'))
            ->rawColumns(['name', 'phone', 'nationality_code', 'total_spent', 'created_at', 'is_active', 'actions'])
            ->toJson();
    }
}
