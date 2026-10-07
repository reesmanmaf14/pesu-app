{{-- Audio-only dialog for a built-in word's shared Tamil recording (resources/js/aac/audio-dialog.js).
     It never shows or sends the word's labels, category or picture, so those can't change here. --}}
<dialog id="audDlg" aria-labelledby="audTitle">
    <form class="dlg" method="dialog" novalidate>
        <div class="dlg-head"><h2 id="audTitle">Tamil voice · தமிழ் குரல்</h2><button type="button" class="close" id="audClose" aria-label="Close"><svg class="ic" aria-hidden="true"><use href="#i-x"/></svg></button></div>
        <p class="cfm" id="audWord"></p>
        <p class="hint">This recording is shared with every approved family. It plays when a device has no Tamil voice. Only the sound is saved; the word itself does not change.</p>
        <fieldset class="voice">
            <legend>Recording</legend>
            <p class="vtimer" id="audTimer" hidden><span class="dot" aria-hidden="true"></span>Recording <span id="audTime">00:00</span></p>
            <div class="row">
                <button class="btn big" type="button" id="audRec">🎙 Record</button>
                <button class="btn big primary" type="button" id="audStop" hidden>⏹ Stop recording</button>
                <button class="btn big" type="button" id="audPlay" hidden>▶ Play</button>
                <button class="btn big primary" type="button" id="audSave" hidden>💾 Save recording</button>
                <button class="btn big danger" type="button" id="audDel" hidden><svg class="ic" aria-hidden="true"><use href="#i-trash"/></svg>Delete</button>
            </div>
            <p class="status" id="audStatus" role="status" aria-live="polite"></p>
            <p class="status warn" id="audErr" role="alert"></p>
        </fieldset>
        <div class="row"><button class="btn big" type="button" id="audDone">Done</button></div>
    </form>
</dialog>
