const DISPLAY_TIME_ZONE = "Europe/Paris";

export const EXPERIENCE_START_YEAR = 2015;

export function getCurrentYear(date: Date = new Date()): number {
    return Number(new Intl.DateTimeFormat("fr-FR", {timeZone: DISPLAY_TIME_ZONE, year: "numeric"}).format(date));
}

export function getYearsSince(startYear: number, date: Date = new Date()): number {
    return getCurrentYear(date) - startYear;
}

export function formatYearsSince(startYear: number, date: Date = new Date()): string {
    const years = getYearsSince(startYear, date);

    return `${years} ${years > 1 ? "ans" : "an"}`;
}
