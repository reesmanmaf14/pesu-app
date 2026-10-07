<x-guest-layout>
    <x-slot name="title">Account status</x-slot>

    @if ($user->isRejected())
        <div>
            <p><span class="pesu-badge pesu-badge-rejected">Not approved</span></p>
            <h1 class="pesu-auth-title">Your account was not approved</h1>
        </div>
        <p class="pesu-hint">
            The therapist has not approved this account, so the talking board isn’t available.
            If you think this is a mistake, please contact the therapist.
        </p>
    @else
        <div>
            <p><span class="pesu-badge pesu-badge-pending">Pending</span></p>
            <h1 class="pesu-auth-title">Waiting for approval</h1>
        </div>
        <p class="pesu-hint">
            Thank you for registering, {{ $user->name }}. The therapist will check your account soon.
            Once it is approved, you can log in and use the talking board.
        </p>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="pesu-actions">
        @csrf
        <x-primary-button>Log out</x-primary-button>
    </form>
</x-guest-layout>
