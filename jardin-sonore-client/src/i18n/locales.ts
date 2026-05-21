export const locales = ["fr"] as const;
export const defaultLocale = "fr";

export type Locale = (typeof locales)[number];

export function hasLocale(locale: string): locale is Locale {
    return locales.includes(locale as Locale);
}
