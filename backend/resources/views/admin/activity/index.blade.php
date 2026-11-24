<x-admin.layout title="Activity log">
    <x-admin.page-header title="Activity log" :breadcrumbs="['Settings' => null, 'Activity log' => null]" subtitle="Every change made by the team in the back office: who, what, when and from where." />

    <form id="activity-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label for="f-search" class="form-label">Search</label>
                <input id="f-search" name="search" type="search" placeholder="Description" class="form-control">
            </div>
            <x-admin.select name="causer_id" label="User" :options="$users" placeholder="Anyone" data-s2-clear span="" />
            <x-admin.select name="action" label="Action" :options="$actions" placeholder="Any action" data-s2-clear span="" />
            <x-admin.select name="subject_type" label="Record type" :options="$subjectTypes" placeholder="Any record" data-s2-clear span="" />
            <div class="grid grid-cols-2 gap-2">
                <x-admin.input name="date_from" label="From" data-datepicker placeholder="dd/mm/yyyy" span="" />
                <x-admin.input name="date_to" label="To" data-datepicker placeholder="dd/mm/yyyy" span="" />
            </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
            <button type="reset" class="btn-ghost btn-sm">Clear filters</button>
            <button type="submit" class="btn-primary btn-sm">Apply</button>
        </div>
    </form>

    <x-admin.datatable id="activity-table" :url="route('admin.activity.data')" filters="#activity-filters" :search="false" :order="[[0, 'desc']]" empty="No activity recorded yet." :columns="[
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'When', 'searchable' => false],
        ['data' => 'causer', 'name' => 'causer', 'title' => 'User', 'searchable' => false, 'orderable' => false],
        ['data' => 'action', 'name' => 'action', 'title' => 'Action', 'searchable' => false],
        ['data' => 'description', 'name' => 'description', 'title' => 'What', 'searchable' => false, 'orderable' => false],
        ['data' => 'subject', 'name' => 'subject', 'title' => 'Record', 'searchable' => false, 'orderable' => false],
        ['data' => 'origin', 'name' => 'origin', 'title' => 'IP', 'searchable' => false, 'orderable' => false],
    ]" />
</x-admin.layout>
