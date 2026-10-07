<x-app-layout>
    <x-slot name="title">Tamil recordings</x-slot>

    @push('head')
        {{-- The board's styles for the shared recording dialog, rows and toast, plus the recorder. --}}
        @vite(['resources/css/aac.css', 'resources/js/aac/recordings.js'])
    @endpush

    <div class="recpage">
        <x-pesu.page-header title="Tamil recordings of built-in words" />

        <p class="hint">
            <span id="recCount">{{ $recorded }}</span> of {{ $total }} built-in words have a recording.
            Recordings are shared with every approved family and play when a device has no Tamil voice.
            Families’ own words are private and are not listed here.
        </p>

        <nav class="pesu-seg" aria-label="Filter words">
            <a class="pesu-btn" href="{{ route('therapist.recordings') }}" @if (! $missingOnly) aria-current="page" @endif>All words</a>
            <a class="pesu-btn" href="{{ route('therapist.recordings', ['missing' => 1]) }}" @if ($missingOnly) aria-current="page" @endif>Missing recordings only</a>
        </nav>

        <div id="recList"></div>
    </div>

    <div id="toast" class="toast" hidden>
        <span id="toastMsg" role="status"></span>
    </div>

    @include('aac.partials.audio-dialog')

    <script id="rec-data" type="application/json">@json($payload)</script>
</x-app-layout>
