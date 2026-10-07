@props(['href' => null])
{{-- The Pesu wordmark, as on the board's header. Pass href to make it a link. --}}
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'pesu-brand']) }}><b lang="en">Pesu</b><span lang="ta">பேசு</span></a>
@else
    <span {{ $attributes->merge(['class' => 'pesu-brand']) }}><b lang="en">Pesu</b><span lang="ta">பேசு</span></span>
@endif
