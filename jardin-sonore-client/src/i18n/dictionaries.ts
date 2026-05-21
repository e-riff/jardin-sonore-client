import "server-only";
import {Locale} from "@/i18n/locales";
import {Dictionary} from "@/i18n/types";

const dictionaries = {
    fr: () => import("@/i18n/dictionaries/fr").then((module) => module.default),
};

export async function getDictionary(locale: Locale): Promise<Dictionary> {
    return dictionaries[locale]();
}
