@props(['value'])

<label {{ $attributes->merge(['class' => 'pesu-label']) }}>
    {{ $value ?? $slot }}
</label>
