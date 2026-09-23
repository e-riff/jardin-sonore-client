import {NextResponse} from "next/server";
import {PortalApiClient} from "@/lib/portal/api-client";
import {getPortalAccessToken} from "@/lib/portal/session";

export async function GET(): Promise<Response> {
    const token = await getPortalAccessToken();
    if (!token) return new NextResponse(null, {status: 401});

    const response = await (await PortalApiClient.fromCurrentRequest(token)).avatar();
    if (!response.ok) return new NextResponse(null, {status: response.status});

    return new NextResponse(response.body, {
        headers: {
            "cache-control": "private, no-store",
            "content-type": response.headers.get("content-type") ?? "image/*",
        },
    });
}
