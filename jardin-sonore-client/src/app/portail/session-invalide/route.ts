import {NextRequest, NextResponse} from "next/server";
import {expirePortalImpersonation, expirePortalSession} from "@/lib/portal/cookie-options";

export function GET(request: NextRequest): NextResponse {
    const response = NextResponse.redirect(new URL("/portail/connexion", request.url));
    expirePortalSession(response.cookies);
    expirePortalImpersonation(response.cookies);
    return response;
}
