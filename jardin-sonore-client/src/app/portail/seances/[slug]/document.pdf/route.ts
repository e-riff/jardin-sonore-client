import {NextResponse} from "next/server";
import {PortalApiClient, portalClientIpFromHeaders} from "@/lib/portal/api-client";
import {clearPortalSession, getPortalAccessToken} from "@/lib/portal/session";

export async function GET(request: Request, {params}: {params: Promise<{slug: string}>}): Promise<Response> {
    const token = await getPortalAccessToken();
    if (!token) return new NextResponse(null, {status: 401});
    const {slug} = await params;
    const documentResponse = await new PortalApiClient(token, undefined, portalClientIpFromHeaders(request.headers)).document(slug);
    if (documentResponse.status === 401) {
        await clearPortalSession();
        return new NextResponse(null, {status: 401});
    }
    const headers = new Headers();
    for (const name of ["content-type", "content-disposition"]) {
        const value = documentResponse.headers.get(name);
        if (value) headers.set(name, value);
    }
    return new Response(documentResponse.body, {headers, status: documentResponse.status});
}
