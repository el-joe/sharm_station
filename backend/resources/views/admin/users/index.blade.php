@php use App\Enums\UserRole; @endphp
<x-admin.layout title="Users and roles">
    <x-admin.page-header title="Users and roles" :breadcrumbs="['Settings' => null, 'Users and roles' => null]" subtitle="Team accounts of the back office. Customers and partners are managed in Sales.">
        <x-slot:actions><a href="{{ route('admin.users.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New user</a></x-slot:actions>
    </x-admin.page-header>

    <form id="user-filters" class="card mb-4 flex flex-wrap items-end gap-3 p-4" aria-label="Filters">
        <x-admin.select name="role" label="Role" :options="collect($roles)->mapWithKeys(fn ($role) => [$role->value => $role->label()])->all()" placeholder="All roles" data-s2-clear span="w-full sm:w-56" />
    </form>

    <x-admin.datatable id="users-table" :url="route('admin.users.data')" filters="#user-filters" :order="[[0, 'asc']]" search-placeholder="Search name or email" :columns="[
        ['data' => 'name', 'name' => 'name', 'title' => 'User'],
        ['data' => 'role', 'name' => 'role', 'title' => 'Role', 'searchable' => false],
        ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Created', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />

    <x-admin.card title="Permissions by role" subtitle="Fixed by role; change a user's role to change what they can do." class="mt-4" :padding="false">
        <div class="overflow-x-auto">
            <table class="admin-table min-w-[560px]">
                <thead><tr><th scope="col">Module</th>@foreach ($roles as $role)<th scope="col" class="text-center">{{ $role->label() }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($matrix as $module => $allowed)
                        <tr>
                            <th scope="row" class="px-4 py-3 text-left font-medium">{{ $module }}</th>
                            @foreach ($roles as $role)
                                <td class="text-center">
                                    @if ($allowed[$role->value])
                                        <span class="text-success" aria-label="Allowed"><x-admin.icon name="check" size="18" class="inline" /></span>
                                    @else
                                        <span class="text-ink-300" aria-label="Not allowed">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-admin.card>
</x-admin.layout>
