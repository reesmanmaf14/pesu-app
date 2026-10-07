@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'pesu-error', 'role' => 'alert']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
