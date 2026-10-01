import {handleNewsletterConfirmation} from "@/lib/newsletter/request-handlers";

export const runtime = "nodejs";

export async function POST(request: Request, context: {params: Promise<{token: string}>}): Promise<Response> {
    return handleNewsletterConfirmation(request, (await context.params).token);
}
