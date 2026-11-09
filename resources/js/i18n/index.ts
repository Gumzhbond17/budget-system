import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import lo from './locales/lo.json';

export const SUPPORTED_LOCALES = ['lo', 'en'] as const;
export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number];

const STORAGE_KEY = 'locale';

/** ລະບົບນີ້ໃຊ້ຢູ່ລາວ — ພາສາລາວຄືຄ່າຕັ້ງຕົ້ນເມື່ອເດົາຈາກ browser ບໍ່ໄດ້ */
const DEFAULT_LOCALE: SupportedLocale = 'lo';

function isSupported(
    value: string | null | undefined,
): value is SupportedLocale {
    return (
        value != null &&
        (SUPPORTED_LOCALES as readonly string[]).includes(value)
    );
}

/**
 * ລຳດັບການເລືອກ: ຄ່າທີ່ຜູ້ໃຊ້ເລືອກໄວ້ → ພາສາຂອງ browser → ລາວ
 */
function resolveInitialLocale(): SupportedLocale {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);

        if (isSupported(stored)) {
            return stored;
        }
    } catch {
        // private mode — ຂ້າມໄປໃຊ້ພາສາຂອງ browser
    }

    for (const tag of navigator.languages ?? [navigator.language]) {
        // "lo-LA" → "lo"
        const base = tag.split('-')[0]?.toLowerCase();

        if (isSupported(base)) {
            return base;
        }
    }

    return DEFAULT_LOCALE;
}

const initialLocale = resolveInitialLocale();

export const i18n = createI18n({
    legacy: false,
    globalInjection: true,
    locale: initialLocale,
    fallbackLocale: 'en',
    messages: { en, lo },
});

export function setLocale(locale: SupportedLocale): void {
    i18n.global.locale.value = locale;

    try {
        localStorage.setItem(STORAGE_KEY, locale);
    } catch {
        // ຈື່ບໍ່ໄດ້ກໍບໍ່ເປັນຫຍັງ — ຍັງປ່ຽນພາສາໃນ session ນີ້ໄດ້
    }

    applyLangAttribute(locale);
}

/**
 * `<html lang>` ບອກ browser ວ່າຈະເລືອກຟອນ ແລະ ຕັດຄຳແນວໃດ ແລະ ບອກ screen reader
 * ວ່າຈະອ່ານດ້ວຍພາສາໃດ. blade ໃສ່ຄ່າຈາກ Laravel ໄວ້ຕອນ render ເຊິ່ງອາດບໍ່ກົງ
 * ກັບພາສາທີ່ຜູ້ໃຊ້ເລືອກໄວ້ໃນ SPA ຈຶ່ງຕ້ອງຕັ້ງຄືນຕອນ boot.
 */
function applyLangAttribute(locale: SupportedLocale): void {
    document.documentElement.setAttribute('lang', locale);
}

applyLangAttribute(initialLocale);
