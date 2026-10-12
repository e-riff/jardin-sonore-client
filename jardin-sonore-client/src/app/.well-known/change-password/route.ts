import {portalRoutes} from "@/lib/portal/routes";

export function GET(): Response {
    return new Response(null, {
        status: 302,
        headers: {"Cache-Control": "no-store", Location: portalRoutes.passwordChange},
    });
}
