export const portalRoutes = {
    login: "/portail/connexion",
    unavailable: "/portail/indisponible",
    sessions: "/portail/seances",
    account: "/portail/compte",
    passwordReset: "/portail/reinitialiser-mot-de-passe",
    sessionInvalid: "/portail/session-invalide",
    definePassword: (token: string): string => `/portail/definir-mot-de-passe/${encodeURIComponent(token)}`,
    session: (slug: string): string => `/portail/seances/${encodeURIComponent(slug)}`,
    document: (slug: string): string => `/portail/seances/${encodeURIComponent(slug)}/document.pdf`,
} as const;
