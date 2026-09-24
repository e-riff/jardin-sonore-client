import "server-only";
import {redirect} from "next/navigation";
import {PortalApiUnavailableError} from "./api-client";
import {portalRoutes} from "./routes";

export async function portalRequestOrUnavailable<T>(request: () => Promise<T>): Promise<T> {
    try {
        return await request();
    } catch (error) {
        if (error instanceof PortalApiUnavailableError) redirect(portalRoutes.unavailable);
        throw error;
    }
}
