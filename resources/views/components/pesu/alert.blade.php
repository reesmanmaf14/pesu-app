@props(['type' => 'info'])
{{-- A short message. Problems use role="alert"; everything else is announced politely. --}}
@php
    $class = match ($type) {
        'success' => 'pesu-alert pesu-alert-success',
        'warn' => 'pesu-alert pesu-alert-warn',
        'danger' => 'pesu-alert pesu-alert-danger',
        default => 'pesu-alert',
    };
@endphp
<p {{ $attributes->merge(['class' => $class, 'role' => $type === 'danger' ? 'alert' : 'status']) }}>{{ $slot }}</p>
