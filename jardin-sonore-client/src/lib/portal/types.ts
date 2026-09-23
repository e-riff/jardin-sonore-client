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

export interface PortalSessionSummary {
    uuid: string;
    title: string;
    sessionDate: string;
    sharedAt: string | null;
    organizations: PortalOrganization[];
    theme: string | null;
    documentStatus: PortalDocumentStatus;
}

export interface PortalSessionListResponse {
    items: PortalSessionSummary[];
    pagination: {
        page: number;
        pageSize: number;
        total: number;
    };
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
