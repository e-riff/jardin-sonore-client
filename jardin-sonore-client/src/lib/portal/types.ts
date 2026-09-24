export interface PortalOrganization {
    uuid: string;
    name: string;
}

export interface PortalAccount {
    email: string;
    firstName: string | null;
    lastName: string | null;
    avatarPath: string | null;
    newSessionNotificationsEnabled: boolean;
    organizations: PortalOrganization[];
}

export const portalAccountDisplayName = (account: PortalAccount): string => {
    if (account.firstName && account.lastName) return `${account.firstName} ${account.lastName}`;
    if (account.firstName) return account.firstName;

    return account.email;
};

export const portalAvatarUrl = (avatarPath: string): string => `/portail/avatar?v=${encodeURIComponent(avatarPath)}`;

export interface PortalProfileData { firstName: string; lastName: string; newSessionNotificationsEnabled: boolean; }

export interface PortalLoginData {
    email: string;
    password: string;
}

export interface PortalPasswordData {
    password: string;
}

export interface PortalTokenResponse {
    token: string;
}

export interface PortalApiResult<T> {
    data: T | null;
    response: Response;
}

export type PortalDocumentStatus = "pending" | "generating" | "ready" | "failed";

export interface PortalTheme {uuid: string; label: string; color: string;}
export interface PortalPagination {page: number; pageSize: number; total: number;}

export interface PortalSessionSummary {
    slug: string;
    title: string;
    sessionDate: string;
    sharedAt: string | null;
    organizations: PortalOrganization[];
    themes: PortalTheme[];
    subtitle: string | null;
    documentStatus: PortalDocumentStatus;
}

export interface PortalSessionListResponse {
    items: PortalSessionSummary[];
    pagination: PortalPagination;
    availableThemes: PortalTheme[];
}

export interface PortalRepertoireSummary {
    slug: string;
    title: string;
    type: "nursery_rhyme" | "fingerplay";
    updatedAt: string;
    organizations: PortalOrganization[];
    themes: PortalTheme[];
    thumbnailUrl: string | null;
}

export interface PortalRepertoireListResponse {
    items: PortalRepertoireSummary[];
    pagination: PortalPagination;
    availableThemes: PortalTheme[];
}

export interface PortalRepertoireMedia {type: "video" | "soundtrack" | "link"; title: string; url: string;}
export interface PortalRepertoireBlock {kind: string; text?: string; gesture?: string;}
export interface PortalRepertoireDetail extends PortalRepertoireSummary {
    source: string | null;
    body: string;
    generalInstructions: string | null;
    contentBlocks: PortalRepertoireBlock[];
    notes: string | null;
    media: PortalRepertoireMedia[];
}

export interface PortalSessionDetail extends PortalSessionSummary {
    generalNotes: string | null;
    materialSummary: string | null;
    furtherExploration: string | null;
    instrumentUuids: string[];
    instrumentNames: string[];
    recommendationUuids: string[];
    sequences: Array<Record<string, unknown>>;
}
