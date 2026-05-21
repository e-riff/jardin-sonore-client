import "server-only";
import {getDictionary} from "@/i18n/dictionaries";
import {defaultLocale} from "@/i18n/locales";

export function getTranslations() {
    return getDictionary(defaultLocale);
}
