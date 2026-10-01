import {handleNewsletterConfirmation} from "@/lib/newsletter/request-handlers";

export const runtime = "nodejs";

export async function POST(request: Request): Promise<Response> {
    return handleNewsletterConfirmation(request);
}
