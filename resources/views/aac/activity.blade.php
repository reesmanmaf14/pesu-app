<x-app-layout>
    <x-slot name="title">Activity</x-slot>

    <x-pesu.page-header title="Activity" subtitle="Sentences spoken on the board. Only you can see this history." />

    @if (session('status'))
        <x-pesu.alert type="success">{{ session('status') }}</x-pesu.alert>
    @endif

    @unless ($logging)
        <x-pesu.alert type="warn">
            History is turned off. Turn on “Keep a history of spoken sentences” in the board’s settings to see new activity here.
        </x-pesu.alert>
    @endunless

    <div class="pesu-stats">
        <div class="pesu-stat">
            <p class="pesu-stat-num">{{ $today }}</p>
            <p class="pesu-stat-label">sentences spoken today</p>
        </div>
        <div class="pesu-stat">
            <p class="pesu-stat-num">{{ $week }}</p>
            <p class="pesu-stat-label">in the last 7 days</p>
        </div>
    </div>

    <x-pesu.card title="Most used">
        @if ($top->isEmpty())
            <x-pesu.empty>Nothing spoken yet. Sentences appear here after pressing Speak on the board.</x-pesu.empty>
        @else
            <ol class="pesu-list">
                @foreach ($top as $row)
                    <li class="pesu-list-item">
                        <span class="pesu-list-text" lang="{{ $row->lang }}">{{ $row->sentence }}</span>
                        <span class="pesu-list-count">{{ $row->total }}×</span>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-pesu.card>

    <x-pesu.card title="Recent">
        @if ($recent->isEmpty())
            <x-pesu.empty>No recent activity.</x-pesu.empty>
        @else
            <ul class="pesu-list">
                @foreach ($recent as $log)
                    <li class="pesu-list-item">
                        <span class="pesu-list-text" lang="{{ $log->lang }}">{{ $log->sentence }}</span>
                        <time class="pesu-list-time" datetime="{{ $log->created_at->toIso8601String() }}" title="{{ $log->created_at->toDayDateTimeString() }}">{{ $log->created_at->diffForHumans() }}</time>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-pesu.card>

    @if ($recent->isNotEmpty())
        <form method="POST" action="{{ route('aac.activity.clear') }}" class="pesu-actions"
              onsubmit="return confirm('Delete all history? This can’t be undone.')">
            @csrf
            @method('DELETE')
            <button class="pesu-btn pesu-btn-danger">
                <svg class="ic" aria-hidden="true"><use href="#i-trash"/></svg>Clear history
            </button>
        </form>
    @endif
</x-app-layout>
