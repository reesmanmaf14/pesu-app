@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'pesu-alert pesu-alert-success', 'role' => 'status']) }}>
        {{ $status }}
    </div>
@endif
