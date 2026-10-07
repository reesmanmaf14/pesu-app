@props(['title', 'subtitle' => null])
{{-- Page title (the page's only h1), an optional description, and optional actions in the slot. --}}
<header {{ $attributes->merge(['class' => 'pesu-header']) }}>
    <div>
        <h1 class="pesu-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="pesu-subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if (trim($slot) !== '')
        <div class="pesu-header-actions">{{ $slot }}</div>
    @endif
</header>
