import type {PortalDocumentStatus} from "./types";

export type PortalDocumentState = "pending" | "ready" | "unavailable";

export const portalDocumentState = (status: PortalDocumentStatus): PortalDocumentState => {
    if (status === "ready") {
        return "ready";
    }

    return status === "failed" ? "unavailable" : "pending";
};
