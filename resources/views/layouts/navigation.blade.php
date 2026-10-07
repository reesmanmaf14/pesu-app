{{-- Pesu's top bar for every page except the board (which has its own). On phones the buttons show
     icons only; each label stays as the button's accessible name. Log out stays a POST form. --}}
<header class="pesu-nav">
    <div class="pesu-nav-inner">
        <x-pesu.brand :href="route('aac.board')" />

        <nav class="pesu-nav-links" aria-label="Main">
            <a class="pesu-navbtn" href="{{ route('aac.board') }}" @if (request()->routeIs('aac.board')) aria-current="page" @endif>
                <span class="emoji" aria-hidden="true">💬</span><span class="pesu-lbl">Board</span>
            </a>
            <a class="pesu-navbtn" href="{{ route('aac.activity') }}" @if (request()->routeIs('aac.activity')) aria-current="page" @endif>
                <svg class="ic" aria-hidden="true"><use href="#i-pulse"/></svg><span class="pesu-lbl">Activity</span>
            </a>
            @can('therapist')
                <a class="pesu-navbtn" href="{{ route('therapist.recordings') }}" @if (request()->routeIs('therapist.recordings')) aria-current="page" @endif>
                    <span class="emoji" aria-hidden="true">🎙</span><span class="pesu-lbl">Recordings</span>
                </a>
                <a class="pesu-navbtn" href="{{ route('therapist.approvals') }}" @if (request()->routeIs('therapist.approvals')) aria-current="page" @endif>
                    <span class="emoji" aria-hidden="true">✅</span><span class="pesu-lbl">Approvals</span>
                </a>
            @endcan
            <a class="pesu-navbtn" href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.edit')) aria-current="page" @endif>
                <span class="emoji" aria-hidden="true">👤</span><span class="pesu-lbl">Profile</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="pesu-navbtn">
                    <svg class="ic" aria-hidden="true"><use href="#i-sign-out"/></svg><span class="pesu-lbl">Log out</span>
                </button>
            </form>
        </nav>
    </div>
</header>
