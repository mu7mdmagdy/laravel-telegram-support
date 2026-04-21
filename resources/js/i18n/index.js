import en from './en.js';
import ar from './ar.js';

const dictionaries = { en, ar };

const RTL_LANGS = new Set(['ar', 'he', 'fa', 'ur']);

/**
 * Minimal i18n composable.
 *
 * Lang resolution order:
 *   1. `langOverride` argument (e.g. from a component prop)
 *   2. `<html lang="…">` attribute (standard Laravel layout value)
 *   3. Fallback to 'en'
 *
 * Placeholder syntax: use `:key` in translation strings.
 * Example: t('unread', { count: 3 }) → "3 unread"
 */
export function useTranslations(langOverride = null) {
    const rawLang = (
        langOverride ||
        (typeof document !== 'undefined' ? document.documentElement.lang : '') ||
        'en'
    ).split('-')[0].toLowerCase();

    const lang = dictionaries[rawLang] ? rawLang : 'en';
    const dict = dictionaries[lang];

    function t(key, replacements = {}) {
        let str = dict[key] ?? dictionaries.en[key] ?? key;
        for (const [k, v] of Object.entries(replacements)) {
            str = str.replace(`:${k}`, String(v));
        }
        return str;
    }

    const isRtl = RTL_LANGS.has(lang);

    return { t, lang, isRtl };
}
