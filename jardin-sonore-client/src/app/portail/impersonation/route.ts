import {NextRequest, NextResponse} from "next/server";
import {PortalApiClient, PortalApiUnavailableError} from "@/lib/portal/api-client";
import {PORTAL_IMPERSONATION_MAX_AGE_SECONDS, writePortalImpersonation, writePortalSession} from "@/lib/portal/cookie-options";
import {portalRoutes} from "@/lib/portal/routes";

export async function POST(request: NextRequest): Promise<NextResponse> {
    const formData = await request.formData().catch((): null => null);
    const launchToken = formData?.get("launchToken");
    if (typeof launchToken !== "string" || !/^[a-f0-9]{64}$/.test(launchToken)) {
        return redirectTo(portalRoutes.unavailable);
    }

    try {
        const result = await (await PortalApiClient.fromCurrentRequest()).consumeImpersonationLaunch(launchToken);
        if (!result.response.ok || !result.data?.token) {
            return redirectTo(portalRoutes.unavailable);
        }

        const response = redirectTo(portalRoutes.sessions);
        writePortalSession(response.cookies, result.data.token, PORTAL_IMPERSONATION_MAX_AGE_SECONDS);
        writePortalImpersonation(response.cookies);
        return response;
    } catch (error) {
        if (error instanceof PortalApiUnavailableError) {
            return redirectTo(portalRoutes.unavailable);
        }
        throw error;
    }
}

function redirectTo(path: string): NextResponse {
    return new NextResponse(null, {status: 303, headers: {Location: path}});
}
