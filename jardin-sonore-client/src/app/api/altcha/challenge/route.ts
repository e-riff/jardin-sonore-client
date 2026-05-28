import {NextRequest, NextResponse} from "next/server";
import {createAltchaChallenge} from "@/lib/altcha";
import {isAllowedRequestOrigin} from "@/lib/request-origin";

export const dynamic = "force-dynamic";
export const runtime = "nodejs";

export async function GET(request: NextRequest): Promise<NextResponse> {
    if (!isAllowedRequestOrigin(request)) {
        return NextResponse.json({error: "Forbidden"}, {status: 403});
    }

    const challenge = await createAltchaChallenge();

    return NextResponse.json(challenge, {
        headers: {
            "cache-control": "no-store",
        },
    });
}
