<!doctype html>
<html lang="{{ $payload['settings']['lang'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pesu – {{ auth()->user()->name }}</title>
    @vite(['resources/css/aac.css', 'resources/js/aac/app.js'])
</head>
<body>
@include('aac.partials.icons')
<div class="app">
    <header class="top">
        <div class="brand"><b lang="en">Pesu</b><span lang="ta">பேசு</span></div>
        <div class="seg" role="group" aria-label="Language">
            <button id="lang-en" type="button">English</button>
            <button id="lang-ta" type="button" lang="ta">தமிழ்</button>
        </div>
        <button class="iconbtn" id="editBtn" type="button" aria-pressed="false"><svg class="ic" aria-hidden="true"><use href="#i-pencil-simple"/></svg><span class="lbl"></span></button>
        <a class="iconbtn" id="actLink" href="{{ route('aac.activity') }}"><svg class="ic" aria-hidden="true"><use href="#i-pulse"/></svg><span class="lbl">Activity</span></a>
        @can('therapist')
            <a class="iconbtn" href="{{ route('therapist.recordings') }}"><span aria-hidden="true">🎙</span><span class="lbl">Recordings</span></a>
            <a class="iconbtn" href="{{ route('therapist.approvals') }}"><span aria-hidden="true">✅</span><span class="lbl">Approvals</span></a>
        @endcan
        <button class="iconbtn" id="setBtn" type="button" aria-haspopup="dialog"><svg class="ic" aria-hidden="true"><use href="#i-gear"/></svg><span class="lbl">Settings</span></button>
    </header>

    <section class="msgbar" id="msgbar" aria-label="Message">
        <span class="speaking-ind" aria-hidden="true"><svg class="ic" aria-hidden="true"><use href="#i-speaker-high"/></svg><span class="lbl" id="speakingLbl"></span></span>
        <div class="chips" id="chips"></div>
        <p class="sr" id="msgStatus" role="status" aria-live="polite"></p>
        <div class="actions">
            <button type="button" id="delBtn" aria-label="Delete last"><svg class="ic" aria-hidden="true"><use href="#i-backspace"/></svg></button>
            <button type="button" id="clrBtn"></button>
            <button type="button" class="speak" id="speakBtn"><svg class="ic" aria-hidden="true"><use href="#i-speaker-high"/></svg><span class="lbl"></span></button>
        </div>
    </section>

    <nav class="tabs" id="tabs" role="tablist" aria-label="Categories"></nav>
    <main class="board" id="board" role="tabpanel" tabindex="-1"></main>
</div>

<div id="toast" class="toast" hidden>
    <span id="toastMsg" role="status"></span>
    <button type="button" id="toastAct" hidden></button>
</div>

{{-- Pesu's own confirm box (replaces window.confirm): large, follows the board language, Cancel is the default. --}}
<dialog id="cfmDlg" role="alertdialog" aria-labelledby="cfmText">
    <form class="dlg" method="dialog">
        <p class="cfm" id="cfmText"></p>
        <div class="row cfm-row">
            <button class="btn big" value="cancel" id="cfmCancel" autofocus></button>
            <button class="btn big danger" value="ok" id="cfmOk"></button>
        </div>
    </form>
</dialog>

<dialog id="addDlg" aria-labelledby="addTitle">
    <form class="dlg" id="addForm" novalidate>
        <div class="dlg-head"><h2 id="addTitle">Add a word</h2><button type="button" class="close" data-close aria-label="Close"><svg class="ic" aria-hidden="true"><use href="#i-x"/></svg></button></div>
        <div class="field"><label for="fEn">English</label><input type="text" id="fEn" autocomplete="off" maxlength="120"></div>
        <div class="field"><label for="fTa">Tamil · தமிழ்</label><input type="text" id="fTa" lang="ta" autocomplete="off" maxlength="120"></div>
        <div class="field"><label for="fCat">Category</label><select id="fCat"></select></div>
        <div class="field" id="extraField">
            <label for="fExtra" id="extraLabel"></label>
            <input type="text" id="fExtra" lang="ta" autocomplete="off" maxlength="120">
            <small id="extraHint"></small>
        </div>
        <div class="field"><label for="fEmoji">Picture: an emoji</label><input type="text" id="fEmoji" maxlength="16" autocomplete="off" placeholder="🙂"></div>
        <div class="field"><label for="fPhoto">…or a photo from this device</label><input type="file" id="fPhoto" accept="image/*"></div>
        <label class="check" id="fRmPhotoRow" hidden><input type="checkbox" id="fRmPhoto"> Remove the current photo</label>

        <fieldset class="voice" aria-describedby="vHint">
            <legend>Tamil voice · தமிழ் குரல் <span class="opt">(optional)</span></legend>
            <p class="hint" id="vHint">Record how this word is said in Tamil. It plays instead of the computer voice.</p>
            <p class="vtimer" id="vTimer" hidden><span class="dot" aria-hidden="true"></span>Recording <span id="vTime">00:00</span></p>
            <div class="row">
                <button class="btn big" type="button" id="vRec" aria-label="Record Tamil pronunciation">🎙 Record Tamil voice</button>
                <button class="btn big primary" type="button" id="vStop" hidden>⏹ Stop recording</button>
                <button class="btn big" type="button" id="vPlay" hidden aria-label="Play Tamil recording">▶ Play</button>
                <button class="btn big" type="button" id="vDel" hidden aria-label="Delete Tamil recording">🗑 Delete</button>
            </div>
            <p class="status" id="vStatus" role="status" aria-live="polite"></p>
            <p class="status warn" id="vErr" role="alert"></p>
        </fieldset>

        <p class="status warn" id="addErr" role="alert"></p>
        <div class="row">
            <button class="btn primary big" type="submit" id="addSave">Add word</button>
            <button class="btn big" type="button" id="addCancel">Cancel</button>
            <button class="btn danger big" type="button" id="addDelete" hidden><svg class="ic" aria-hidden="true"><use href="#i-trash"/></svg>Delete word</button>
        </div>
    </form>
</dialog>

@can('therapist')
    @include('aac.partials.audio-dialog')
@endcan

<dialog id="catDlg" aria-labelledby="catTitle">
    <form class="dlg" id="catForm" novalidate>
        <div class="dlg-head"><h2 id="catTitle">New category</h2><button type="button" class="close" data-close aria-label="Close"><svg class="ic" aria-hidden="true"><use href="#i-x"/></svg></button></div>
        <div class="field"><label for="cEn">Name in English</label><input type="text" id="cEn" autocomplete="off" maxlength="60"></div>
        <div class="field"><label for="cTa">Name in Tamil · தமிழ்</label><input type="text" id="cTa" lang="ta" autocomplete="off" maxlength="60"></div>
        <div class="field"><label for="cEmoji">Icon: an emoji (optional)</label><input type="text" id="cEmoji" maxlength="16" autocomplete="off" placeholder="📁"></div>
        <div class="field">
            <label for="cKind">What kind of words go here?</label>
            <select id="cKind" aria-describedby="cKindHint"></select>
            <small id="cKindHint">This sets the colour of the pictures and how sentences are built. For example, Places can follow “Let’s go…”.</small>
        </div>
        <p class="status warn" id="catErr" role="alert"></p>
        <div class="row">
            <button class="btn primary big" type="submit" id="catSave">Create category</button>
            <button class="btn big" type="button" id="catCancel">Cancel</button>
        </div>
    </form>
</dialog>

<dialog id="setDlg" aria-labelledby="setTitle">
    <form class="dlg" method="dialog">
        <div class="dlg-head"><h2 id="setTitle">Settings</h2><button type="button" class="close" data-close aria-label="Close"><svg class="ic" aria-hidden="true"><use href="#i-x"/></svg></button></div>
        <fieldset class="group">
            <legend>Display</legend>
            <label class="check"><input type="checkbox" id="sBoth"> Show both languages on pictures</label>
            <div class="field"><label for="sCols">Pictures per row</label>
                <select id="sCols"><option>3</option><option>4</option><option>5</option><option>6</option><option>8</option></select></div>
        </fieldset>
        <fieldset class="group">
            <legend>Speech</legend>
            <label class="check"><input type="checkbox" id="sEach"> Speak each picture when tapped</label>
            <div class="field">
                <div class="rate-head"><label for="sRate">Speech speed</label><output id="sRateOut" for="sRate" aria-hidden="true"></output></div>
                <input type="range" id="sRate" min="0.6" max="1.4" step="0.1">
            </div>
            <div class="field"><label for="sVEn">English voice</label><select id="sVEn"></select></div>
            <div class="field"><label for="sVTa">Tamil voice</label><select id="sVTa"></select><span class="status" id="taStatus"></span></div>
            <div class="row">
                <button class="btn" type="button" id="testEn">Test English</button>
                <button class="btn" type="button" id="testTa" lang="ta">தமிழ் சோதனை</button>
            </div>
        </fieldset>
        <fieldset class="group">
            <legend>Privacy</legend>
            <label class="check"><input type="checkbox" id="sLog"> Keep a history of spoken sentences</label>
        </fieldset>
        <div class="row"><button class="btn primary big" value="close">Done</button></div>
        {{-- Log out lives here, not in the header, so it can't be tapped by accident mid-conversation. --}}
        <fieldset class="group">
            <legend>Account</legend>
            <div class="row">
                <button class="btn big" type="submit" form="logoutForm"><svg class="ic" aria-hidden="true"><use href="#i-sign-out"/></svg>Log out</button>
            </div>
        </fieldset>
    </form>
</dialog>
<form id="logoutForm" method="POST" action="{{ route('logout') }}" hidden>@csrf</form>

<script id="aac-data" type="application/json">@json($payload)</script>
</body>
</html>
