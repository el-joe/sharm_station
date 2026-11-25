@php($err = fn (string $key) => $errors->first("faq_rows.{$index}.{$key}"))
<div data-repeater-row class="grid grid-cols-1 gap-3 border-b border-ink-100 py-3 md:grid-cols-[24px_minmax(0,1fr)_minmax(0,1fr)_40px]">
    <span data-drag-handle class="hidden cursor-grab pt-7 text-ink-300 md:block" aria-hidden="true"><x-admin.icon name="grip" size="18" /></span>
    @foreach (['en' => 'English', 'it' => 'Italiano'] as $locale => $language)
        <div class="space-y-2">
            <label class="form-label text-xs">Question ({{ strtoupper($locale) }})</label>
            <input type="text" name="faq_rows[{{ $index }}][question][{{ $locale }}]" value="{{ $row['question'][$locale] ?? '' }}" lang="{{ $locale }}" maxlength="255" aria-label="Question ({{ $language }})" @class(['form-control', 'is-invalid' => $err("question.{$locale}")])>
            <textarea name="faq_rows[{{ $index }}][answer][{{ $locale }}]" rows="2" lang="{{ $locale }}" maxlength="2000" aria-label="Answer ({{ $language }})" placeholder="Answer" @class(['form-control', 'is-invalid' => $err("answer.{$locale}")])>{{ $row['answer'][$locale] ?? '' }}</textarea>
            @if ($err("question.{$locale}"))<p class="form-error">{{ $err("question.{$locale}") }}</p>@endif
        </div>
    @endforeach
    <button type="button" data-repeater-remove class="self-start rounded-control p-2 text-ink-500 hover:bg-error/10 hover:text-error md:mt-6" aria-label="Remove question"><x-admin.icon name="trash" size="18" /></button>
</div>
