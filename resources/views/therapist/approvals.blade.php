<x-app-layout>
    <x-slot name="title">Parent accounts</x-slot>

    <x-pesu.page-header title="Parent accounts" subtitle="Approve new registrations before they can use the board. You can change a decision later." />

    @if (session('status'))
        <x-pesu.alert type="success">{{ session('status') }}</x-pesu.alert>
    @endif

    @foreach ([
        ['Waiting for approval', $pending, 'No one is waiting.', ['approve', 'reject']],
        ['Not approved', $rejected, 'No rejected accounts.', ['approve']],
        ['Approved', $approved, 'No approved parent accounts yet.', ['reject']],
    ] as [$heading, $users, $empty, $actions])
        <section class="pesu-card" aria-labelledby="sec-{{ $loop->index }}">
            <h2 class="pesu-card-title" id="sec-{{ $loop->index }}">{{ $heading }} <span class="count">({{ $users->count() }})</span></h2>

            @if ($users->isEmpty())
                <x-pesu.empty>{{ $empty }}</x-pesu.empty>
            @else
                <ul class="pesu-list">
                    @foreach ($users as $u)
                        <li class="pesu-person">
                            <div>
                                <p class="pesu-person-name">
                                    {{ $u->name }}
                                    @if ($u->isPending())
                                        <span class="pesu-badge pesu-badge-pending">Pending</span>
                                    @elseif ($u->isRejected())
                                        <span class="pesu-badge pesu-badge-rejected">Not approved</span>
                                    @else
                                        <span class="pesu-badge pesu-badge-approved">Approved</span>
                                    @endif
                                </p>
                                <p class="pesu-person-email">{{ $u->email }}</p>
                                <p class="pesu-meta">
                                    Registered <time datetime="{{ $u->created_at?->toIso8601String() }}">{{ $u->created_at?->diffForHumans() }}</time>
                                    @if ($u->reviewed_at)
                                        · last decision <time datetime="{{ $u->reviewed_at->toIso8601String() }}">{{ $u->reviewed_at->diffForHumans() }}</time>{{ $u->reviewer ? ' by '.$u->reviewer->name : '' }}
                                    @endif
                                </p>
                            </div>
                            <div class="pesu-person-actions">
                                @if (in_array('approve', $actions))
                                    <form method="POST" action="{{ route('therapist.approvals.approve', $u) }}">
                                        @csrf
                                        <button class="pesu-btn pesu-btn-primary" aria-label="Approve {{ $u->name }}">Approve</button>
                                    </form>
                                @endif
                                @if (in_array('reject', $actions))
                                    <form method="POST" action="{{ route('therapist.approvals.reject', $u) }}"
                                          onsubmit="return confirm('Reject this account? They will not be able to use the board.')">
                                        @csrf
                                        <button class="pesu-btn pesu-btn-danger" aria-label="Reject {{ $u->name }}">Reject</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</x-app-layout>
