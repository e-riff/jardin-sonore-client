import {NextResponse} from "next/server";
import {createAltchaChallenge} from "@/lib/altcha";

export const dynamic = "force-dynamic";
export const runtime = "nodejs";

export async function GET(): Promise<NextResponse> {
    const challenge = await createAltchaChallenge();

    return NextResponse.json(challenge, {
        headers: {
            "cache-control": "no-store",
        },
    });
}
