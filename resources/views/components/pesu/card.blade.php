@props(['title' => null])
{{-- A flat, bordered Pesu card. With a title it is a labelled section. --}}
<section {{ $attributes->merge(['class' => 'pesu-card']) }}>
    @if ($title)
        <h2 class="pesu-card-title">{{ $title }}</h2>
    @endif
    {{ $slot }}
</section>
