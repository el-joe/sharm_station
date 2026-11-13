<?php

namespace App\DataTables\Concerns;

/**
 * Small HTML helpers for server-rendered DataTables cells. Every dynamic value is escaped.
 */
trait RendersRowActions
{
    protected function editLink(string $url, string $label = 'Edit'): string
    {
        return '<a href="'.e($url).'" class="btn-link">'.e($label).'</a>';
    }

    protected function deleteForm(string $url, string $confirm): string
    {
        return '<form method="POST" action="'.e($url).'" data-confirm="'.e($confirm).'" class="inline">'
            .'<input type="hidden" name="_token" value="'.e(csrf_token()).'">'
            .'<input type="hidden" name="_method" value="DELETE">'
            .'<button type="submit" class="text-sm font-semibold text-error hover:underline">Delete</button></form>';
    }

    protected function actions(string ...$items): string
    {
        return '<div class="flex items-center justify-end gap-4">'.implode('', $items).'</div>';
    }

    protected function titleCell(string $title, ?string $subtitle = null, ?string $url = null): string
    {
        $title = e($title);
        $title = $url !== null ? '<a href="'.e($url).'" class="font-semibold text-ink-900 hover:text-primary-700 hover:underline">'.$title.'</a>' : '<span class="font-semibold">'.$title.'</span>';

        return '<div class="min-w-0">'.$title.($subtitle !== null ? '<div class="text-xs text-ink-500">'.e($subtitle).'</div>' : '').'</div>';
    }

    protected function money(float|string|null $amount): string
    {
        return $amount === null ? '—' : '€'.number_format((float) $amount, 2);
    }
}
