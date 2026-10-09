import {NextRequest} from "next/server";
import {handleContactRequest} from "@/lib/contact/request-handler";

export const dynamic = "force-dynamic";
export const runtime = "nodejs";

export async function POST(request: NextRequest): Promise<Response> {
    return handleContactRequest(request);
}
