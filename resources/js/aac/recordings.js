/** The therapist's "Tamil recordings" page: built-in words and their shared recordings. */
import { Speaker } from './speech';
import { AudioDialog } from './audio-dialog';

const DATA = JSON.parse(document.getElementById('rec-data').textContent);
const $ = (id) => document.getElementById(id);
const speaker = new Speaker();

function render() {
    const list = $('recList');
    list.textContent = '';

    if (!DATA.categories.length) {
        const p = document.createElement('p');
        p.className = 'hint';
        p.textContent = 'Every built-in word has a recording.';
        list.appendChild(p);
    }

    DATA.categories.forEach((c) => {
        const box = document.createElement('section');
        box.className = 'group';
        const h = document.createElement('h2');
        h.textContent = `${c.e || ''} ${c.en} · ${c.ta}`;
        box.appendChild(h);

        c.tiles.forEach((t) => {
            const row = document.createElement('div');
            row.className = 'recrow';

            const word = document.createElement('span');
            word.className = 'word';
            const pic = document.createElement('span');
            pic.className = 'pic';
            pic.setAttribute('aria-hidden', 'true');
            pic.textContent = t.e || '🔤';
            const en = document.createElement('span');
            en.textContent = t.en;
            const ta = document.createElement('span');
            ta.lang = 'ta';
            ta.textContent = t.ta;
            word.append(pic, en, ta);

            const state = document.createElement('span');
            state.className = t.audio ? 'state' : 'state missing';
            state.textContent = t.audio ? '✓ Recorded' : 'Missing';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn';
            btn.textContent = t.audio ? '▶ Play / re-record' : '🎙 Record';
            btn.setAttribute('aria-label', `${btn.textContent}: ${t.en}`);
            btn.addEventListener('click', () => dialog.open(t));

            row.append(word, state, btn);
            box.appendChild(row);
        });

        list.appendChild(box);
    });
}

function toast(text) {
    $('toastMsg').textContent = text;
    $('toast').hidden = false;
    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => ($('toast').hidden = true), 4000);
}

const dialog = new AudioDialog({
    speaker,
    routes: DATA.routes,
    confirm: (text) => Promise.resolve(window.confirm(text)),
    onChange: (tile, old) => {
        for (const c of DATA.categories) {
            const i = c.tiles.findIndex((x) => x.id === tile.id);
            if (i >= 0) c.tiles[i] = tile;
        }
        $('recCount').textContent = String(Number($('recCount').textContent) + (tile.audio ? 1 : 0) - (old.audio ? 1 : 0));
        toast(tile.audio ? '✓ Tamil voice saved' : 'Recording deleted');
        render();
    },
});

render();
