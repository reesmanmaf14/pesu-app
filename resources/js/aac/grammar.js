/**
 * Sentence building for English and Tamil.
 *
 * Tamil is verb-final and adds case endings, so we can't just join labels in tap order.
 * Frame tiles ("I want {}" / "எனக்கு {} வேண்டும்") hold a slot that the next picture fills,
 * and each word carries the forms it needs:
 *   places  → dat (dative "to" form): பூங்கா → பூங்காவுக்கு
 *   actions → inf (infinitive):       விளையாடு → விளையாட
 */

export const tidy = (s) => s.replace(/\s+/g, ' ').replace(/\s+([?.!])/g, '$1').trim();

export function canFill(frameTile, tile) {
    return !!frameTile.frame && frameTile.frame.accepts.includes(tile.k);
}

export function slotText(frame, t, lang) {
    const ta = lang === 'ta';
    const k = t.k;

    if (frame.key === 'go') {
        if (k === 'place') return ta ? (t.dat || t.ta) : (t.to || `to ${t.en}`);
        if (k === 'verb') return ta ? (t.inf || t.ta) : t.en;
    }

    // "I want the park" → "I want to go to the park" / "எனக்கு பூங்காவுக்கு போக வேண்டும்"
    if ((frame.key === 'want' || frame.key === 'dontwant') && k === 'place') {
        return ta ? `${t.dat || t.ta} போக` : `to go ${t.to || `to ${t.en}`}`;
    }

    if (k === 'verb') return ta ? (t.inf || t.ta) : (t.enInf || `to ${t.en}`);
    if (!ta && k === 'place' && t.the) return `the ${t.en}`;
    if (!ta && frame.key === 'where' && k === 'noun') return `the ${t.en}`;

    return ta ? t.ta : t.en;
}

/** Message items are either { t: tile } or { f: frameTile, fill: tile|null }. */
export function itemText(item, lang) {
    if (item.f) {
        const template = item.f.frame[lang];
        return tidy(template.replace('{}', item.fill ? slotText(item.f.frame, item.fill, lang) : ''));
    }
    return item.t[lang] || item.t.en || item.t.ta;
}

export const sentence = (items, lang) => items.map((i) => itemText(i, lang)).join(' ');

export const tileLabel = (t, lang) =>
    t.frame ? t.frame[lang].replace('{}', '…') : (t[lang] || t.en || t.ta);

export const detectLang = (text) => (/[\u0B80-\u0BFF]/.test(text) ? 'ta' : 'en');
