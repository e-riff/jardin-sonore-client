import {NextResponse} from "next/server";
import {PortalApiClient, portalClientIpFromHeaders} from "@/lib/portal/api-client";
import {clearPortalSession, getPortalAccessToken} from "@/lib/portal/session";

interface PortalDocumentRouteContext {
    params: Promise<{uuid: string}>;
}

export async function POST(request: Request, {params}: PortalDocumentRouteContext): Promise<Response> {
    const accessToken = await getPortalAccessToken();

    if (!accessToken) {
        return new NextResponse(null, {status: 401});
    }

    const {uuid} = await params;
    const documentResponse = await new PortalApiClient(accessToken, undefined, portalClientIpFromHeaders(request.headers)).document(uuid);

    if (documentResponse.status === 401) {
        await clearPortalSession();
        return new NextResponse(null, {status: 401});
    }

    const responseHeaders = new Headers();
    const contentType = documentResponse.headers.get("content-type");
    const contentDisposition = documentResponse.headers.get("content-disposition");

    if (contentType) {
        responseHeaders.set("content-type", contentType);
    }

    if (contentDisposition) {
        responseHeaders.set("content-disposition", contentDisposition);
    }

    return new Response(documentResponse.body, {
        headers: responseHeaders,
        status: documentResponse.status,
    });
}
