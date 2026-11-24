{{-- SEO title and description per language: seo[locale][title|description]. --}}
<x-admin.form-section title="SEO" description="Leave empty to use the title and the introduction.">
    @foreach (['en' => 'English', 'it' => 'Italiano'] as $locale => $language)
        <x-admin.input name="seo[{{ $locale }}][title]" :label="'Meta title ('.$language.')'" :value="$seo[$locale]['title'] ?? null" maxlength="70" data-char-count :id="'seo-title-'.$locale" span="md:col-span-3" />
        <x-admin.textarea name="seo[{{ $locale }}][description]" :label="'Meta description ('.$language.')'" :value="$seo[$locale]['description'] ?? null" :rows="2" maxlength="170" span="md:col-span-3" />
    @endforeach
</x-admin.form-section>
