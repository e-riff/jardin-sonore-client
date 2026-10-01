import {handleNewsletterSubscription} from "@/lib/newsletter/request-handlers";

export const runtime = "nodejs";
export const POST = handleNewsletterSubscription;
