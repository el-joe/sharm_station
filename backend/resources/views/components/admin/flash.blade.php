@foreach (['success', 'error', 'info'] as $type)
    @if (session($type))
        <template data-toast="{{ $type }}">{{ session($type) }}</template>
    @endif
@endforeach
@if ($errors->any() && ! session('error'))
    <template data-toast="error">{{ $errors->count() === 1 ? $errors->first() : 'Please fix the highlighted fields ('.$errors->count().').' }}</template>
@endif
