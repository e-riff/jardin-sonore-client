export interface ContactSubmissionIdentity {
    fingerprint: string;
    key: string;
}

export function nextContactSubmission(
    previous: ContactSubmissionIdentity | null,
    fields: Record<string, string>,
    createKey: () => string = (): string => crypto.randomUUID(),
): ContactSubmissionIdentity {
    const fingerprint = JSON.stringify(fields);
    if (previous?.fingerprint === fingerprint) return previous;

    return {fingerprint, key: createKey()};
}
