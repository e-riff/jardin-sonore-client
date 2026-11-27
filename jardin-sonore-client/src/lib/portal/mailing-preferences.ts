export function changedMailingPreferences(form: FormData): {newsletterSubscribed?: boolean; outreachAllowed?: boolean} {
    const result: {newsletterSubscribed?: boolean; outreachAllowed?: boolean} = {};
    if (form.has("initialNewsletterSubscribed") && (form.get("newsletterSubscribed") === "on") !== (form.get("initialNewsletterSubscribed") === "true")) {
        result.newsletterSubscribed = form.get("newsletterSubscribed") === "on";
    }
    if (form.has("initialOutreachAllowed") && (form.get("outreachAllowed") === "on") !== (form.get("initialOutreachAllowed") === "true")) {
        result.outreachAllowed = form.get("outreachAllowed") === "on";
    }
    return result;
}
