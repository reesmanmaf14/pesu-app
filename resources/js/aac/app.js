import { itemText, tileLabel, canFill, detectLang } from './grammar';
import { Speaker } from './speech';
import { api } from './api';
import { VoiceRecorder, MAX_SECONDS, recordingSupport, friendlyError, audioExtension } from './recorder';
import { AudioDialog } from './audio-dialog';

/* ---------- Data from the server (BoardController) ---------- */
const DATA = JSON.parse(document.getElementById('aac-data').textContent);
const S = DATA.settings;
const R = DATA.routes;
let CATS = DATA.categories;
let phrases = DATA.phrases;

const KEYBOARD_TAB = { slug: 'keyboard', e: '⌨️', en: 'Type', ta: 'தட்டச்சு' };
const MY_TAB = { slug: 'mine', e: '⭐', en: 'My words', ta: 'என் சொற்கள்' };

// Kinds a user can give their own category (must match Category::USER_KINDS).
const KINDS = [
    ['noun', 'Things (food, toys, objects)'],
    ['person', 'People'],
    ['place', 'Places'],
    ['verb', 'Actions'],
    ['feel', 'Feelings'],
    ['adj', 'Describing words'],
    ['phrase', 'Phrases'],
];

const STR = {
    en: {
        speak: 'Speak', clear: 'Clear', empty: 'Tap pictures to build a message', edit: 'Edit', done: 'Done',
        add: 'Add a word', type: 'Type a message…', save: 'Save phrase', saved: 'Saved phrases',
        noSaved: 'Phrases you save appear here, ready to speak with one tap.',
        noTa: "This device has no Tamil voice yet, so Tamil may not be spoken. Add one in the device's text-to-speech settings (on Android: Google Speech Services → Install voice data → Tamil).",
        noSpeech: "This browser can't speak aloud. Try Chrome, Edge or Safari.",
        cantSay: (w) => `Couldn't say “${w}” in Tamil: this device has no working Tamil voice, and there is no recording yet.`,
        newCat: 'New category', editCat: 'Edit category', delCat: 'Delete category', editWord: 'Edit word',
        noMine: 'Words you add appear here. Tap ✏️ Edit, then ＋ Add a word.',
        cleared: 'Message cleared', undo: 'Undo', cancel: 'Cancel', del: 'Delete',
        delWordQ: (w) => `Delete “${w}”?`, delCatQ: (c) => `Delete the category “${c}”?`,
        discardQ: 'Discard your changes?', discard: 'Discard', keep: 'Keep editing',
        activity: 'Activity', settings: 'Settings', speaking: 'Speaking…',
        phrases: 'Phrases', back: (c) => `Back to ${c}`,
    },
    ta: {
        speak: 'பேசு', clear: 'அழி', empty: 'படங்களைத் தட்டி செய்தியை உருவாக்குங்கள்', edit: 'திருத்து', done: 'முடிந்தது',
        add: 'சொல் சேர்', type: 'செய்தியைத் தட்டச்சு செய்யுங்கள்…', save: 'சேமி', saved: 'சேமித்த வாக்கியங்கள்',
        noSaved: 'நீங்கள் சேமிப்பவை இங்கே தோன்றும்.',
        noTa: 'இந்த சாதனத்தில் தமிழ் குரல் இல்லை. சாதனத்தின் உரை-பேச்சு அமைப்புகளில் தமிழ் குரலைச் சேர்க்கவும் (Android: Google Speech Services → Install voice data → Tamil).',
        noSpeech: 'இந்த உலாவியால் பேச முடியாது. Chrome, Edge அல்லது Safari முயற்சிக்கவும்.',
        cantSay: (w) => `“${w}” தமிழில் சொல்ல முடியவில்லை: இந்தச் சாதனத்தில் தமிழ் குரல் இல்லை, பதிவும் இன்னும் இல்லை.`,
        newCat: 'புதிய வகை', editCat: 'வகையைத் திருத்து', delCat: 'வகையை நீக்கு', editWord: 'சொல்லைத் திருத்து',
        noMine: 'நீங்கள் சேர்க்கும் சொற்கள் இங்கே தோன்றும்.',
        cleared: 'செய்தி அழிக்கப்பட்டது', undo: 'மீட்டமை', cancel: 'ரத்துசெய்', del: 'நீக்கு',
        delWordQ: (w) => `“${w}” நீக்கவா?`, delCatQ: (c) => `“${c}” வகையை நீக்கவா?`,
        discardQ: 'மாற்றங்களைக் கைவிடவா?', discard: 'கைவிடு', keep: 'தொடர்ந்து திருத்து',
        activity: 'செயல்பாடு', settings: 'அமைப்புகள்', speaking: 'பேசுகிறது…',
        phrases: 'வாக்கியங்கள்', back: (c) => `${c} பக்கம் திரும்பு`,
    },
};

const $ = (id) => document.getElementById(id);
const L = () => S.lang;
const other = () => (S.lang === 'en' ? 'ta' : 'en');
const str = (k) => STR[S.lang][k];
// Top-level categories are tabs; subcategories (parent set) open inside their parent's tab.
const topCats = () => CATS.filter((c) => !c.parent);
const childrenOf = (c) => CATS.filter((x) => x.parent === c.id);
const tabCategory = () => CATS.find((c) => c.slug === tab && !c.parent);
/** The category whose words are showing: the open subcategory, else the tab's own category. */
const currentCategory = () => (sub && CATS.find((c) => c.slug === sub)) || tabCategory();
const categoryOf = (t) => CATS.find((c) => c.id === t.cat);
const myTiles = () => CATS.flatMap((c) => c.tiles.filter((t) => t.custom));
const catName = (c) => c[L()] || c.en || c.ta;

const firstTab = () => (topCats().length ? topCats()[0].slug : 'keyboard');
let tab = firstTab();
let sub = null; // slug of the subcategory open inside the current tab, or null
let editMode = false;
let msg = [];
let hideTaNotice = false;
const speaker = new Speaker();

/* ---------- Helpers ---------- */
/**
 * Show a short message. With `action` ({ label, run }) it gets a button, e.g. Undo, and stays longer.
 * `key` lets hideToast(key) dismiss only that message. The toast stays up while it is focused or hovered.
 */
function toast(text, { action = null, key = '' } = {}) {
    const t = $('toast');
    const act = $('toastAct');
    t.hidden = false;
    t.dataset.key = key;
    $('toastMsg').textContent = '';
    requestAnimationFrame(() => ($('toastMsg').textContent = text));

    act.hidden = !action;
    act.onclick = null;
    if (action) {
        act.textContent = action.label;
        act.onclick = () => {
            hideToast();
            action.run();
        };
    }

    clearTimeout(toast.timer);
    const expire = () => {
        if (t.contains(document.activeElement) || t.matches(':hover')) toast.timer = setTimeout(expire, 1000);
        else hideToast();
    };
    toast.timer = setTimeout(expire, action ? 8000 : 4000);
}

function hideToast(key) {
    const t = $('toast');
    if (t.hidden || (key && t.dataset.key !== key)) return;
    clearTimeout(toast.timer);
    t.hidden = true;
}

/** Pesu's own confirm box. Resolves true only when the user picks the OK button (Esc or Cancel → false). */
function askConfirm(text, { ok = str('del'), cancel = str('cancel'), okIcon = 'trash' } = {}) {
    const d = $('cfmDlg');
    $('cfmText').textContent = text;
    $('cfmText').lang = L();
    if (okIcon) iconText($('cfmOk'), okIcon, ok);
    else $('cfmOk').textContent = ok;
    $('cfmCancel').textContent = cancel;
    d.returnValue = ''; // Esc leaves returnValue unchanged, so clear the previous answer
    d.showModal();
    return new Promise((resolve) => d.addEventListener('close', () => resolve(d.returnValue === 'ok'), { once: true }));
}

let saveTimer;
function saveSettings() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => api.put(R.settings, S).catch((e) => toast(e.message)), 400);
}

/** Speak text, or a list of parts from partsFor() so recorded Tamil words play their recording. */
function speak(textOrParts, lang, { log = false } = {}) {
    const parts = Array.isArray(textOrParts) ? textOrParts : [{ text: textOrParts }];
    const text = parts.map((p) => p.text).filter(Boolean).join(' ');
    if (!text) return;
    const bar = $('msgbar');
    speaker.speakParts(parts, lang, {
        rate: S.rate,
        voiceURI: lang === 'ta' ? S.voice_ta : S.voice_en,
        onStart: () => bar.classList.add('speaking'),
        onEnd: () => bar.classList.remove('speaking'),
        // Tamil only: shown when neither the Tamil voice nor a recording could say something.
        onUnspoken: (texts) => toast(str('cantSay')(texts.join(', ')), { key: 'unspoken' }),
    });
    if (log && S.log_usage) api.post(R.usage, { sentence: text, lang }).catch(() => {});
}

/**
 * Speech parts for message items. In Tamil each item is its own part, carrying its word's recording
 * and whether that word is built-in (`shared`), so Speaker.speakTamil can pick voice or recording per
 * word. Sentence starters (whose Tamil word forms change) never carry a recording.
 * In English, neighbouring text is joined so it is spoken naturally.
 */
function partsFor(items, lang) {
    if (lang === 'ta') {
        return items.map((it) => {
            const text = itemText(it, lang);
            return it.t?.audio ? { text, audio: it.t.audio, shared: !it.t.custom } : { text };
        });
    }

    const parts = [];
    items.forEach((it) => {
        const text = itemText(it, lang);
        const audio = lang === 'ta' && it.t ? it.t.audio : null;
        const last = parts[parts.length - 1];
        if (!audio && last && !last.audio) last.text += ` ${text}`;
        else parts.push(audio ? { audio, text } : { text });
    });
    return parts;
}

/** A decorative icon from the sprite in partials/icons.blade.php (hidden from screen readers). */
function icon(name) {
    const NS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('class', 'ic');
    svg.setAttribute('aria-hidden', 'true');
    const use = document.createElementNS(NS, 'use');
    use.setAttribute('href', `#i-${name}`);
    svg.appendChild(use);
    return svg;
}

/** Button content: a decorative icon followed by its text. */
function iconText(el, name, text) {
    el.replaceChildren(icon(name), text);
}

function resizeImage(file, size = 320) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const m = Math.min(img.width, img.height);
            const c = document.createElement('canvas');
            c.width = size;
            c.height = size;
            c.getContext('2d').drawImage(img, (img.width - m) / 2, (img.height - m) / 2, m, m, 0, 0, size, size);
            URL.revokeObjectURL(url);
            c.toBlob((b) => (b ? resolve(b) : reject(new Error('Could not read that image.'))), 'image/jpeg', 0.85);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('Could not read that image. Try a JPG or PNG.'));
        };
        img.src = url;
    });
}

/* ---------- Rendering ---------- */
function picEl(t, cls) {
    if (t.img) {
        const i = document.createElement('img');
        i.src = t.img;
        i.alt = '';
        i.className = cls;
        return i;
    }
    const s = document.createElement('span');
    s.className = cls;
    s.textContent = t.e || '🔤';
    s.setAttribute('aria-hidden', 'true');
    return s;
}

function makeTile(t, { onRemove, onClick } = {}) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = `tile k-${t.k}`;
    b.appendChild(picEl(t, 'pic'));

    const w = document.createElement('span');
    w.className = 'w';
    w.lang = L();
    w.textContent = tileLabel(t, L());
    b.appendChild(w);

    if (S.show_both) {
        const w2 = document.createElement('span');
        w2.className = 'w2';
        w2.lang = other();
        w2.textContent = tileLabel(t, other());
        b.appendChild(w2);
    }

    b.setAttribute('aria-label', tileLabel(t, L()));
    b.addEventListener('click', () => (onClick || onTile)(t));

    if (!onRemove) return b;

    // The × sits beside the tile, not inside it: a button nested in a button is unreachable for screen readers.
    const wrap = document.createElement('div');
    wrap.className = 'tilewrap';
    const x = document.createElement('button');
    x.type = 'button';
    x.className = 'x';
    x.textContent = '×';
    x.setAttribute('aria-label', `Delete ${tileLabel(t, L())}`);
    x.addEventListener('click', async () => {
        if (await askConfirm(str('delWordQ')(tileLabel(t, L())))) onRemove();
    });
    wrap.append(b, x);
    return wrap;
}

function renderChrome() {
    document.documentElement.lang = L();
    $('lang-en').setAttribute('aria-pressed', String(L() === 'en'));
    $('lang-ta').setAttribute('aria-pressed', String(L() === 'ta'));
    $('editBtn').querySelector('.lbl').textContent = editMode ? str('done') : str('edit');
    $('editBtn').setAttribute('aria-pressed', String(editMode));
    $('clrBtn').textContent = str('clear');
    $('speakBtn').querySelector('.lbl').textContent = str('speak');
    $('actLink').querySelector('.lbl').textContent = str('activity');
    $('setBtn').querySelector('.lbl').textContent = str('settings');
    $('speakingLbl').textContent = str('speaking');
    for (const el of [...document.querySelectorAll('.top .lbl'), $('speakingLbl')]) el.lang = L();

    // Picture size in px; label size in rem so it follows the user's text-size setting.
    const sizes = { 3: [50, 20], 4: [44, 18], 5: [38, 17], 6: [34, 16], 8: [28, 14] }[S.cols] || [38, 17];
    const r = document.documentElement.style;
    r.setProperty('--cols', S.cols);
    r.setProperty('--pic', `${sizes[0]}px`);
    r.setProperty('--lbl', `${sizes[1] / 16}rem`);
}

/** Announce the message as plain text (the chips themselves would be read as "Remove …" buttons). */
function announceMsg() {
    $('msgStatus').textContent = msg.map((it) => itemText(it, L())).join(' ');
}

function renderMsg({ focusChip = null, added = false } = {}) {
    const box = $('chips');
    box.textContent = '';
    announceMsg();
    // aria-disabled (not disabled) keeps Speak focusable, so focus isn't lost when the message empties.
    $('speakBtn').setAttribute('aria-disabled', String(!msg.length));

    if (!msg.length) {
        const p = document.createElement('span');
        p.className = 'empty';
        p.textContent = str('empty');
        box.appendChild(p);
        if (focusChip !== null) $('speakBtn').focus();
        return;
    }

    msg.forEach((it, i) => {
        const c = document.createElement('button');
        c.type = 'button';
        c.className = `chip k-${it.f ? it.f.k : it.t.k}`;
        c.appendChild(picEl(it.fill || it.f || it.t, 'pic'));

        const txt = document.createElement('span');
        txt.lang = L();
        if (it.f && !it.fill) {
            const [before, after] = it.f.frame[L()].split('{}');
            if (before.trim()) txt.append(`${before.trim()} `);
            const slot = document.createElement('span');
            slot.className = 'slot';
            slot.setAttribute('aria-label', 'blank');
            txt.append(slot);
            if (after.trim()) txt.append(` ${after.trim()}`);
        } else {
            txt.textContent = itemText(it, L());
        }
        c.appendChild(txt);

        c.setAttribute('aria-label', `Remove ${itemText(it, L())}`);
        c.addEventListener('click', () => {
            msg.splice(i, 1);
            renderMsg({ focusChip: i });
        });
        box.appendChild(c);
    });
    if (added) box.lastElementChild?.classList.add('is-new');
    if (focusChip === null) box.scrollTop = box.scrollHeight; // newest chip stays visible

    // After removing a chip with the keyboard, keep focus nearby instead of dropping it to <body>.
    if (focusChip !== null) {
        const chips = box.querySelectorAll('.chip');
        (chips[Math.min(focusChip, chips.length - 1)] || $('speakBtn')).focus();
    }
}

/**
 * Select a category. The tab buttons are updated in place, not rebuilt, so a tapped tab keeps
 * the browser's own focus (no forced focus ring); only arrow-key moves focus programmatically.
 */
function selectTab(slug, { focus = false } = {}) {
    tab = slug;
    sub = null; // tapping a tab, even the current one, goes back to its top level
    syncTabs();
    renderBoard();
    const sel = $(`tab-${tab}`);
    if (!sel) return;
    sel.scrollIntoView({ block: 'nearest', inline: 'nearest' });
    if (focus) sel.focus();
}

function syncTabs() {
    $('tabs').querySelectorAll('[role=tab]').forEach((b) => {
        const on = b.dataset.slug === tab;
        b.setAttribute('aria-selected', String(on));
        b.tabIndex = on ? 0 : -1;
    });
    const sel = $(`tab-${tab}`);
    $('board').setAttribute('aria-labelledby', sel ? sel.id : '');
}

function renderTabs() {
    const n = $('tabs');
    const all = [...topCats(), MY_TAB, KEYBOARD_TAB];
    n.textContent = '';
    all.forEach((c, i) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.id = `tab-${c.slug}`;
        b.dataset.slug = c.slug;
        b.setAttribute('role', 'tab');
        b.setAttribute('aria-controls', 'board');

        const icon = document.createElement('span');
        icon.setAttribute('aria-hidden', 'true');
        icon.textContent = c.e;
        const label = document.createElement('span');
        label.lang = L();
        label.textContent = c[L()];
        b.append(icon, label);

        b.addEventListener('click', () => selectTab(c.slug));
        b.addEventListener('keydown', (e) => {
            const to = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: all.length - 1 }[e.key];
            if (to === undefined) return;
            e.preventDefault();
            selectTab(all[(to + all.length) % all.length].slug, { focus: true });
        });
        n.appendChild(b);
    });
    syncTabs();
    updateTabEdges();
}

/** Mark which ends of the tab row have more tabs off-screen (CSS fades those edges on phones). */
function updateTabEdges() {
    const n = $('tabs');
    const more = n.scrollWidth - n.clientWidth;
    n.classList.toggle('more-start', more > 1 && n.scrollLeft > 1);
    n.classList.toggle('more-end', more > 1 && n.scrollLeft < more - 1);
}
$('tabs').addEventListener('scroll', updateTabEdges, { passive: true });
window.addEventListener('resize', updateTabEdges, { passive: true });

function renderNotice() {
    $('taNotice')?.remove();
    let text = '';
    if (!speaker.supported) text = str('noSpeech');
    else if (L() === 'ta' && speaker.voices.length && !speaker.voicesFor('ta').length && !hideTaNotice) text = str('noTa');
    if (!text) return;

    const d = document.createElement('div');
    d.className = 'notice';
    d.id = 'taNotice';
    d.setAttribute('role', 'status');
    const p = document.createElement('p');
    p.textContent = text;
    const x = document.createElement('button');
    x.type = 'button';
    x.textContent = '×';
    x.setAttribute('aria-label', 'Dismiss');
    x.addEventListener('click', () => {
        hideTaNotice = true;
        d.remove();
    });
    d.append(p, x);
    $('board').prepend(d);
}

function renderBoard() {
    const n = $('board');
    n.textContent = '';

    if (tab === 'keyboard') {
        renderKeyboard(n);
        renderNotice();
        return;
    }

    if (sub && !CATS.some((c) => c.slug === sub)) sub = null;
    const cat = currentCategory();
    if (!cat && tab !== 'mine') {
        // The category was deleted; fall back to the first one.
        tab = firstTab();
        sub = null;
        renderTabs();
        renderBoard();
        return;
    }
    const tiles = cat ? cat.tiles : myTiles();

    if (editMode) n.appendChild(renderEditTools(cat));

    // Inside a subcategory: a way back to the parent, and a heading naming where we are.
    const parent = cat?.parent ? CATS.find((c) => c.id === cat.parent) : null;
    if (parent) n.appendChild(renderSubHeader(parent, cat));

    // A category with subcategories shows them first, then its own words under a heading.
    const children = cat && !parent ? childrenOf(cat) : [];
    if (children.length) {
        const fg = document.createElement('div');
        fg.className = 'grid';
        children.forEach((c) => fg.appendChild(makeFolder(c)));
        n.appendChild(fg);
        if (tiles.length || editMode) {
            const h = document.createElement('h2');
            h.className = 'subhead';
            h.lang = L();
            h.textContent = str('phrases');
            n.appendChild(h);
        }
    }

    if (!cat && !tiles.length && !editMode) {
        const p = document.createElement('p');
        p.className = 'hint';
        p.textContent = str('noMine');
        n.appendChild(p);
    }

    const g = document.createElement('div');
    g.className = 'grid';

    tiles.forEach((t) => {
        // In edit mode, tapping your own word opens it for editing; the therapist can also open
        // a built-in word, but only to record its Tamil voice.
        const opts = editMode && t.custom
            ? { onRemove: () => removeTile(t), onClick: () => openWord(t) }
            : editMode && DATA.canRecordShared ? { onClick: () => audioDlg.open(t) } : {};
        g.appendChild(makeTile(t, opts));
    });

    if (editMode) {
        const a = document.createElement('button');
        a.type = 'button';
        a.className = 'tile addtile';
        const plus = document.createElement('span');
        plus.className = 'pic';
        plus.setAttribute('aria-hidden', 'true');
        plus.textContent = '＋';
        const w = document.createElement('span');
        w.className = 'w';
        w.textContent = str('add');
        a.append(plus, w);
        a.addEventListener('click', () => openWord(null));
        g.appendChild(a);
    }

    n.appendChild(g);
    renderNotice();
}

/** A subcategory tile: opens the subcategory inside the current tab. */
function makeFolder(c) {
    const b = makeTile({ e: c.e, en: c.en, ta: c.ta, k: c.k }, { onClick: () => openSub(c) });
    b.classList.add('folder');
    b.dataset.sub = c.slug;
    return b;
}

function openSub(c) {
    sub = c.slug;
    renderBoard();
    $('board').querySelector('.backtile')?.focus(); // the tapped tile is gone; keep keyboard focus on the board
}

function closeSub() {
    const was = sub;
    sub = null;
    renderBoard();
    $('board').querySelector(`[data-sub="${was}"]`)?.focus();
}

/** "← Food & drink" back tile plus a "Food & drink › Fruits" heading. */
function renderSubHeader(parent, cat) {
    const wrap = document.createElement('div');
    wrap.className = 'subnav';

    const back = document.createElement('button');
    back.type = 'button';
    back.className = 'btn big backtile';
    back.lang = L();
    back.textContent = `← ${catName(parent)}`;
    back.setAttribute('aria-label', str('back')(catName(parent)));
    back.addEventListener('click', closeSub);

    const h = document.createElement('h2');
    h.className = 'subhead';
    h.lang = L();
    const pic = document.createElement('span');
    pic.setAttribute('aria-hidden', 'true');
    pic.textContent = cat.e || '';
    h.append(pic, ` ${catName(cat)}`);

    wrap.append(back, h);
    return wrap;
}

/** Edit-mode buttons for categories: always "New category"; edit/delete only for the user's own. */
function renderEditTools(cat) {
    const bar = document.createElement('div');
    bar.className = 'edittools';
    bar.setAttribute('role', 'group');
    bar.setAttribute('aria-label', 'Category tools');

    const btn = (text, onClick, cls = '') => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = `btn big ${cls}`;
        b.textContent = text;
        b.addEventListener('click', onClick);
        bar.appendChild(b);
        return b;
    };

    iconText(btn('', () => openCategory(null)), 'plus', str('newCat'));
    if (cat?.custom) {
        const edit = btn('', () => openCategory(cat));
        iconText(edit, 'pencil-simple', str('editCat'));
        edit.setAttribute('aria-label', `${str('editCat')}: ${catName(cat)}`);
        const del = btn('', () => deleteCategory(cat), 'danger');
        iconText(del, 'trash', str('delCat'));
        del.setAttribute('aria-label', `${str('delCat')}: ${catName(cat)}`);
    }
    return bar;
}

function renderKeyboard(n) {
    const w = document.createElement('div');
    w.className = 'kbd';

    const ta = document.createElement('textarea');
    ta.placeholder = str('type');
    ta.setAttribute('aria-label', str('type'));

    const row = document.createElement('div');
    row.className = 'row';

    const sp = document.createElement('button');
    sp.type = 'button';
    sp.className = 'btn primary';
    iconText(sp, 'speaker-high', str('speak'));
    sp.addEventListener('click', () => {
        const v = ta.value.trim();
        speak(v, detectLang(v), { log: true });
    });

    const sv = document.createElement('button');
    sv.type = 'button';
    sv.className = 'btn';
    sv.textContent = str('save');
    sv.addEventListener('click', async () => {
        const v = ta.value.trim();
        if (!v) return;
        sv.disabled = true;
        try {
            const { phrase } = await api.post(R.phrasesStore, { text: v });
            phrases.unshift(phrase);
            renderBoard();
        } catch (e) {
            toast(e.message);
            sv.disabled = false;
        }
    });

    const cl = document.createElement('button');
    cl.type = 'button';
    cl.className = 'btn';
    cl.textContent = str('clear');
    cl.addEventListener('click', () => {
        ta.value = '';
        ta.focus();
    });

    row.append(sp, sv, cl);

    const h = document.createElement('h2');
    h.textContent = str('saved');
    w.append(ta, row, h);

    if (!phrases.length) {
        const p = document.createElement('p');
        p.className = 'hint';
        p.textContent = str('noSaved');
        w.appendChild(p);
    } else {
        const g = document.createElement('div');
        g.className = 'grid';
        phrases.forEach((ph) => {
            const t = { e: '💬', en: ph.text, ta: ph.text, k: 'phrase' };
            const b = makeTile(t, {
                onClick: () => speak(ph.text, detectLang(ph.text), { log: true }),
                onRemove: () => removePhrase(ph),
            });
            b.querySelectorAll('.w2').forEach((e) => e.remove());
            g.appendChild(b);
        });
        w.appendChild(g);
    }
    n.appendChild(w);
}

function renderAll() {
    renderChrome();
    renderMsg();
    renderTabs();
    renderBoard();
}

/* ---------- Interaction ---------- */
function onTile(t) {
    hideToast('clear');
    if (t.frame) {
        msg.push({ f: t, fill: null });
        if (S.speak_each) speak(itemText({ f: t, fill: null }, L()), L());
    } else {
        const last = msg[msg.length - 1];
        if (last && last.f && !last.fill && canFill(last.f, t)) {
            last.fill = t;
            if (S.speak_each) speak(itemText(last, L()), L());
        } else {
            msg.push({ t });
            if (S.speak_each) speak(partsFor([{ t }], L()), L());
        }
    }
    renderMsg({ added: true });
}

async function removeTile(t) {
    try {
        await api.del(R.tileDestroy.replace('__ID__', t.id));
        const cat = categoryOf(t);
        if (cat) cat.tiles = cat.tiles.filter((x) => x.id !== t.id);
        renderBoard();
        return true;
    } catch (e) {
        toast(e.message);
        return false;
    }
}

async function removePhrase(ph) {
    try {
        await api.del(R.phraseDestroy.replace('__ID__', ph.id));
        phrases = phrases.filter((x) => x.id !== ph.id);
        renderBoard();
    } catch (e) {
        toast(e.message);
    }
}

$('lang-en').addEventListener('click', () => { S.lang = 'en'; saveSettings(); renderAll(); });
$('lang-ta').addEventListener('click', () => { S.lang = 'ta'; saveSettings(); renderAll(); });
$('editBtn').addEventListener('click', () => { editMode = !editMode; renderChrome(); renderBoard(); });
$('delBtn').addEventListener('click', () => {
    const last = msg[msg.length - 1];
    if (!last) return;
    if (last.f && last.fill) last.fill = null;
    else msg.pop();
    renderMsg();
});
$('clrBtn').addEventListener('click', () => {
    speaker.cancel();
    if (!msg.length) return;
    const cleared = msg;
    msg = [];
    renderMsg();
    toast(str('cleared'), {
        key: 'clear',
        action: {
            label: str('undo'),
            run: () => {
                msg = cleared;
                renderMsg();
                $('clrBtn').focus();
            },
        },
    });
});
$('speakBtn').addEventListener('click', () => {
    if (msg.length) speak(partsFor(msg, L()), L(), { log: true });
});

/* ---------- Add or edit a word ---------- */
let editing = null; // the tile being edited, or null when adding

function fillCategorySelect(selectedId) {
    const sel = $('fCat');
    sel.textContent = '';
    const name = (c) => `${c.en}${c.ta && c.ta !== c.en ? ` · ${c.ta}` : ''}`;
    const add = (c, parent = null) => {
        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = `${c.e || ''} ${parent ? `${parent.en} › ` : ''}${name(c)}`.trim();
        o.selected = c.id === selectedId;
        sel.appendChild(o);
    };
    // Each subcategory is listed right after its parent: "Food & drink › Fruits".
    topCats().forEach((c) => {
        add(c);
        childrenOf(c).forEach((child) => add(child, c));
    });
}

const selectedCategory = () => CATS.find((c) => String(c.id) === $('fCat').value);

function updateExtraField() {
    const cat = selectedCategory();
    const ef = $('extraField');
    if (cat?.k === 'place') {
        ef.hidden = false;
        $('extraLabel').textContent = 'Tamil “to” form (optional)';
        $('extraHint').textContent = 'Used in “Let’s go…”. Example: பூங்கா → பூங்காவுக்கு';
    } else if (cat?.k === 'verb') {
        ef.hidden = false;
        $('extraLabel').textContent = 'Tamil form after “I want” (optional)';
        $('extraHint').textContent = 'Example: விளையாடு → விளையாட, so it says எனக்கு விளையாட வேண்டும்';
    } else {
        ef.hidden = true;
    }
}

function openWord(t) {
    editing = t;
    const cat = t ? categoryOf(t) : currentCategory() || CATS.find((c) => c.custom) || CATS[0];
    if (!cat) return;

    $('addTitle').textContent = t ? str('editWord') : str('add');
    $('addSave').textContent = t ? 'Save changes' : 'Add word';
    $('addDelete').hidden = !t;
    $('fEn').value = t?.en || '';
    $('fTa').value = t?.ta || '';
    $('fEmoji').value = t?.e || '';
    $('fExtra').value = t ? t.dat || t.inf || '' : '';
    $('fPhoto').value = '';
    $('fRmPhoto').checked = false;
    $('fRmPhotoRow').hidden = !t?.img;
    $('addErr').textContent = '';

    fillCategorySelect(cat.id);
    updateExtraField();
    resetVoice(t?.audio || null);

    $('addDlg').showModal();
    $('fEn').focus();
    addSnapshot = addState();
}

/** Field values of the word dialog, to tell whether anything changed since it opened. */
const addState = () =>
    JSON.stringify(['fEn', 'fTa', 'fCat', 'fExtra', 'fEmoji', 'fPhoto', 'fRmPhoto'].map((id) =>
        $(id).type === 'checkbox' ? $(id).checked : $(id).value));
let addSnapshot = '';

/** Put a saved tile into its category, replacing the old copy (it may have moved category). */
function placeTile(tile, old) {
    const oldCat = old ? categoryOf(old) : null;
    const cat = CATS.find((c) => c.id === tile.cat);
    const at = oldCat && oldCat === cat ? cat.tiles.findIndex((x) => x.id === tile.id) : -1;

    if (oldCat) oldCat.tiles = oldCat.tiles.filter((x) => x.id !== tile.id);
    if (cat) {
        if (at >= 0) cat.tiles.splice(at, 0, tile);
        else cat.tiles.push(tile);
    }

    if (old) {
        msg.forEach((it) => {
            if (it.t === old) it.t = tile;
            if (it.fill === old) it.fill = tile;
        });
        renderMsg();
    }
}

$('fCat').addEventListener('change', updateExtraField);
$('addCancel').addEventListener('click', () => requestClose('addDlg'));
$('addDlg').addEventListener('close', () => {
    recorder.cancel();
    speaker.cancel();
});

$('addDelete').addEventListener('click', async () => {
    if (!editing) return;
    if (!(await askConfirm(str('delWordQ')(tileLabel(editing, L()))))) return;
    if (await removeTile(editing)) $('addDlg').close();
});

$('addForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cat = selectedCategory();
    if (!cat) {
        $('addErr').textContent = 'Choose a category.';
        return;
    }
    if (recorder.recording) {
        $('addErr').textContent = 'Stop the recording first.';
        return;
    }

    const en = $('fEn').value.trim();
    const taWord = $('fTa').value.trim();
    if (!en && !taWord) {
        $('addErr').textContent = 'Type the word in English, Tamil, or both.';
        return;
    }

    const fd = new FormData();
    fd.append('category_id', cat.id);
    fd.append('label_en', en);
    fd.append('label_ta', taWord);
    fd.append('emoji', $('fEmoji').value.trim());

    const extra = $('fExtra').value.trim();
    if (cat.k === 'place') fd.append('ta_dative', extra);
    if (cat.k === 'verb') fd.append('ta_infinitive', extra);

    if (voice.blob) fd.append('ta_audio', voice.blob, `voice.${audioExtension(voice.blob.type)}`);
    if (editing) {
        fd.append('_method', 'PUT'); // multipart uploads must be POSTed
        if ($('fRmPhoto').checked) fd.append('remove_photo', '1');
        if (voice.remove && !voice.blob) fd.append('remove_audio', '1');
    }

    const btn = $('addSave');
    btn.disabled = true;
    $('addErr').textContent = '';
    const uploadingVoice = !!voice.blob;
    if (uploadingVoice) renderVoice('uploading');
    try {
        const file = $('fPhoto').files[0];
        if (file) fd.append('photo', await resizeImage(file), 'photo.jpg');

        const { tile } = await api.form(editing ? R.tileUpdate.replace('__ID__', editing.id) : R.tilesStore, fd);
        placeTile(tile, editing);
        $('addDlg').close();
        renderBoard();
        toast(uploadingVoice ? '✓ Tamil voice saved' : editing ? '✓ Word saved' : '✓ Word added');
    } catch (err) {
        $('addErr').textContent = err.errors ? Object.values(err.errors)[0][0] : err.message;
        if (uploadingVoice) renderVoice('ready');
    } finally {
        btn.disabled = false;
    }
});

/* ---------- Tamil voice recording (in the word dialog) ---------- */
// blob/url: a new recording not uploaded yet; saved: URL of the word's stored recording;
// remove: delete the stored recording when the word is saved.
let voice = { blob: null, url: null, saved: null, remove: false };

const clock = (s) => {
    const n = Math.floor(s);
    return `${String(Math.floor(n / 60)).padStart(2, '0')}:${String(n % 60).padStart(2, '0')}`;
};

const restingVoiceState = () =>
    voice.blob ? 'ready' : voice.saved && !voice.remove ? 'saved' : voice.remove ? 'removed' : 'idle';

const VOICE_STATUS = {
    idle: '',
    requesting: 'Requesting microphone permission…',
    recording: 'Recording. Say the word now, then tap Stop.',
    ready: 'Recording ready. Tap ▶ Play to check it. It is saved when you save the word.',
    saved: '✓ Tamil voice saved',
    removed: 'The saved recording will be removed when you save the word.',
    uploading: 'Uploading voice…',
};

function renderVoice(state, note = '') {
    const support = recordingSupport();
    const hasSound = state === 'ready' || state === 'saved';
    const busy = state === 'recording' || state === 'uploading';

    $('vRec').hidden = busy;
    $('vRec').disabled = support !== 'ok' || state === 'requesting';
    $('vRec').textContent = hasSound ? '🎙 Record again' : '🎙 Record Tamil voice';
    $('vStop').hidden = state !== 'recording';
    $('vPlay').hidden = !hasSound;
    $('vDel').hidden = !hasSound;
    $('vTimer').hidden = state !== 'recording';
    $('vStatus').textContent = note || VOICE_STATUS[state];

    if (support !== 'ok') {
        $('vErr').textContent =
            support === 'insecure'
                ? 'Voice recording needs a secure connection (https, or localhost on this computer).'
                : 'Voice recording is not supported by this browser.';
    }
}

function gotRecording({ blob, hitLimit }) {
    if (!blob.size) {
        renderVoice(restingVoiceState());
        $('vErr').textContent = 'Nothing was recorded. Please try again.';
        return;
    }
    if (voice.url) URL.revokeObjectURL(voice.url);
    voice.blob = blob;
    voice.url = URL.createObjectURL(blob);
    renderVoice(
        'ready',
        hitLimit ? `Recording stopped at the ${MAX_SECONDS}-second limit. Your recording was kept. Tap ▶ Play to check it.` : ''
    );
    $('vPlay').focus();
}

const recorder = new VoiceRecorder({
    maxSeconds: MAX_SECONDS,
    onTick: (s) => ($('vTime').textContent = clock(s)),
    onStop: gotRecording,
    onError: (message) => {
        renderVoice(restingVoiceState());
        $('vErr').textContent = message;
    },
});

function resetVoice(savedUrl) {
    recorder.cancel();
    if (voice.url) URL.revokeObjectURL(voice.url);
    voice = { blob: null, url: null, saved: savedUrl, remove: false };
    $('vErr').textContent = '';
    renderVoice(restingVoiceState());
}

$('vRec').addEventListener('click', async () => {
    $('vErr').textContent = '';
    speaker.cancel();
    renderVoice('requesting');
    try {
        await recorder.start();
        renderVoice('recording');
        $('vStop').focus();
    } catch (err) {
        renderVoice(restingVoiceState());
        $('vErr').textContent = friendlyError(err);
        $('vRec').focus();
    }
});

$('vStop').addEventListener('click', () => recorder.stop());

$('vPlay').addEventListener('click', () => speaker.speakParts([{ audio: voice.url || voice.saved }], 'ta'));

$('vDel').addEventListener('click', () => {
    speaker.cancel();
    if (voice.blob) {
        URL.revokeObjectURL(voice.url);
        voice.blob = null;
        voice.url = null;
        renderVoice(restingVoiceState(), 'Recording deleted.');
    } else if (voice.saved) {
        voice.remove = true;
        renderVoice('removed');
    }
    $('vRec').focus();
});

/* ---------- Shared Tamil recordings of built-in words (therapist only) ---------- */
const audioDlg = DATA.canRecordShared
    ? new AudioDialog({
          speaker,
          routes: R,
          confirm: (text, opts) => askConfirm(text, opts),
          onChange: (tile, old) => {
              placeTile(tile, old);
              renderBoard();
          },
      })
    : null;

/* ---------- Categories ---------- */
let editingCat = null;

KINDS.forEach(([k, label]) => {
    const o = document.createElement('option');
    o.value = k;
    o.textContent = label;
    $('cKind').appendChild(o);
});

function openCategory(c) {
    editingCat = c;
    $('catTitle').textContent = c ? str('editCat') : str('newCat');
    $('catSave').textContent = c ? 'Save changes' : 'Create category';
    $('cEn').value = c?.en || '';
    $('cTa').value = c?.ta || '';
    $('cEmoji').value = c?.e || '';
    $('cKind').value = c?.k || 'noun';
    $('catErr').textContent = '';
    $('catDlg').showModal();
    $('cEn').focus();
    catSnapshot = catState();
}

const catState = () => JSON.stringify(['cEn', 'cTa', 'cEmoji', 'cKind'].map((id) => $(id).value));
let catSnapshot = '';

$('catCancel').addEventListener('click', () => requestClose('catDlg'));

$('catForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = {
        name_en: $('cEn').value.trim(),
        name_ta: $('cTa').value.trim(),
        emoji: $('cEmoji').value.trim(),
        kind: $('cKind').value,
    };
    if (!body.name_en && !body.name_ta) {
        $('catErr').textContent = 'Type the category name in English, Tamil, or both.';
        return;
    }

    const btn = $('catSave');
    btn.disabled = true;
    try {
        if (editingCat) {
            const { category } = await api.put(R.categoryUpdate.replace('__ID__', editingCat.id), body);
            Object.assign(editingCat, category);
        } else {
            const { category } = await api.post(R.categoriesStore, body);
            CATS.push(category);
            tab = category.slug;
            sub = null;
        }
        $('catDlg').close();
        renderTabs();
        renderBoard();
        toast(editingCat ? '✓ Category saved' : '✓ Category created');
    } catch (err) {
        $('catErr').textContent = err.errors ? Object.values(err.errors)[0][0] : err.message;
    } finally {
        btn.disabled = false;
    }
});

async function deleteCategory(c) {
    // The server refuses too; checking here explains why without a round trip.
    if (c.tiles.length) {
        const n = c.tiles.length;
        toast(`“${catName(c)}” still has ${n} word${n === 1 ? '' : 's'}. Move or delete ${n === 1 ? 'it' : 'them'} first.`);
        return;
    }
    if (!(await askConfirm(str('delCatQ')(catName(c))))) return;
    try {
        await api.del(R.categoryDestroy.replace('__ID__', c.id));
        CATS = CATS.filter((x) => x.id !== c.id);
        tab = firstTab();
        sub = null;
        renderTabs();
        renderBoard();
        toast('✓ Category deleted');
    } catch (err) {
        toast(err.message);
    }
}

/* ---------- Closing dialogs ---------- */
// Unsaved work in a dialog is only thrown away after asking. Settings save as they change, so never ask there.
const UNSAVED = {
    addDlg: () => addState() !== addSnapshot || !!voice.blob || voice.remove || recorder.recording,
    catDlg: () => catState() !== catSnapshot,
};

async function requestClose(id) {
    const unsaved = UNSAVED[id];
    if (unsaved && unsaved() && !(await askConfirm(str('discardQ'), { ok: str('discard'), cancel: str('keep'), okIcon: null }))) return;
    $(id).close();
}

['addDlg', 'catDlg', 'setDlg'].forEach((id) => {
    $(id).addEventListener('cancel', (e) => {
        // Esc: route through requestClose so unsaved changes are confirmed first.
        e.preventDefault();
        requestClose(id);
    });
    $(id).querySelector('[data-close]').addEventListener('click', () => requestClose(id));
});

/* ---------- Settings ---------- */
function fillVoiceSelects() {
    [['sVEn', 'en', 'voice_en'], ['sVTa', 'ta', 'voice_ta']].forEach(([id, lang, key]) => {
        const sel = $(id);
        sel.textContent = '';
        const auto = document.createElement('option');
        auto.value = '';
        auto.textContent = 'Automatic';
        sel.appendChild(auto);
        speaker.voicesFor(lang).forEach((v) => {
            const o = document.createElement('option');
            o.value = v.voiceURI;
            o.textContent = `${v.name} (${v.lang})`;
            sel.appendChild(o);
        });
        sel.value = S[key] || '';
    });

    const st = $('taStatus');
    const count = speaker.voicesFor('ta').length;
    if (!speaker.supported) {
        st.textContent = 'Speech isn’t available in this browser.';
        st.className = 'status warn';
    } else if (!count) {
        st.textContent = 'No Tamil voice found on this device. Add one in the device’s text-to-speech settings.';
        st.className = 'status warn';
    } else {
        st.textContent = `${count} Tamil voice(s) found.`;
        st.className = 'status';
    }
}

/** "Normal · 1.0×" for the speed slider (display only; saving is unchanged). */
function showRate() {
    const v = Number($('sRate').value);
    const tenths = Math.round(v * 10);
    const word = tenths <= 8 ? 'Slow' : tenths >= 12 ? 'Fast' : 'Normal';
    $('sRateOut').textContent = `${word} · ${v.toFixed(1)}×`;
    $('sRate').setAttribute('aria-valuetext', `${word}, ${v.toFixed(1)} times`);
}
$('sRate').addEventListener('input', showRate);

$('setBtn').addEventListener('click', () => {
    $('sBoth').checked = S.show_both;
    $('sEach').checked = S.speak_each;
    $('sLog').checked = S.log_usage;
    $('sCols').value = String(S.cols);
    $('sRate').value = S.rate;
    showRate();
    fillVoiceSelects();
    $('setDlg').showModal();
});
$('sBoth').addEventListener('change', (e) => { S.show_both = e.target.checked; saveSettings(); renderAll(); });
$('sEach').addEventListener('change', (e) => { S.speak_each = e.target.checked; saveSettings(); });
$('sLog').addEventListener('change', (e) => { S.log_usage = e.target.checked; saveSettings(); });
$('sCols').addEventListener('change', (e) => { S.cols = Number(e.target.value); saveSettings(); renderChrome(); });
$('sRate').addEventListener('change', (e) => { S.rate = Number(e.target.value); saveSettings(); });
$('sVEn').addEventListener('change', (e) => { S.voice_en = e.target.value || null; saveSettings(); });
$('sVTa').addEventListener('change', (e) => { S.voice_ta = e.target.value || null; saveSettings(); });
$('testEn').addEventListener('click', () => speak('Hello, I can talk with pictures.', 'en'));
$('testTa').addEventListener('click', () => speak('வணக்கம், நான் படங்களால் பேசுகிறேன்.', 'ta'));

/* ---------- Start ---------- */
renderAll();
speaker.init(() => {
    fillVoiceSelects();
    renderNotice();
});
