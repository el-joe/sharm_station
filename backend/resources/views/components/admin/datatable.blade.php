{{-- Server-side Yajra DataTable. $columns: list of {data, title, orderable?, searchable?, className?}. --}}
@props(['id', 'url', 'columns', 'order' => [], 'filters' => null, 'export' => null, 'search' => true, 'empty' => null, 'searchPlaceholder' => null])
<div class="card overflow-hidden">
    <div class="overflow-x-auto">
        <table id="{{ $id }}" data-datatable data-url="{{ $url }}" data-columns='@json($columns)' data-order='@json($order)'
               @if ($filters) data-filters="{{ $filters }}" @endif @if ($export) data-export="{{ $export }}" @endif
               @if (! $search) data-search="false" @endif @if ($empty) data-empty="{{ $empty }}" @endif
               @if ($searchPlaceholder) data-search-placeholder="{{ $searchPlaceholder }}" @endif
               class="w-full text-sm">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th scope="col">{{ $column['title'] }}</th>
                    @endforeach
                </tr>
            </thead>
        </table>
    </div>
</div>
