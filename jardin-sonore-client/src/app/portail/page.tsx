import {redirect} from "next/navigation";
import {getPortalSession} from "@/lib/portal/session";

export default async function PortalIndexPage(): Promise<never> {
    await getPortalSession();

    redirect("/portail/seances");
}
