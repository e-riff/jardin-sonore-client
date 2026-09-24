export const portalRoutes = {
    login: "/portail/connexion",
    unavailable: "/portail/indisponible",
    sessions: "/portail/seances",
    repertoire: "/portail/comptines",
    account: "/portail/compte",
    passwordReset: "/portail/reinitialiser-mot-de-passe",
    sessionInvalid: "/portail/session-invalide",
    definePassword: (token: string): string => `/portail/definir-mot-de-passe/${encodeURIComponent(token)}`,
    session: (slug: string): string => `/portail/seances/${encodeURIComponent(slug)}`,
    repertoireItem: (slug: string): string => `/portail/comptines/${encodeURIComponent(slug)}`,
    document: (slug: string): string => `/portail/seances/${encodeURIComponent(slug)}/document.pdf`,
} as const;
