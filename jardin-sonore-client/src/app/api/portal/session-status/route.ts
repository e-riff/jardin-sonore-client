import {NextResponse} from "next/server";
import {PortalApiClient} from "@/lib/portal/api-client";
import {getPortalAccessToken} from "@/lib/portal/session";

export const dynamic = "force-dynamic";

export async function GET(): Promise<NextResponse> {
    const token = await getPortalAccessToken();
    let authenticated = false;

    if (token) {
        try {
            const result = await (await PortalApiClient.fromCurrentRequest(token)).me();
            authenticated = result.response.ok && result.data !== null;
        } catch {
            authenticated = false;
        }
    }

    return NextResponse.json({authenticated}, {headers: {"cache-control": "no-store"}});
}
