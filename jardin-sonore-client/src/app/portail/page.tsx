import {redirect} from "next/navigation";
import {portalRoutes} from "@/lib/portal/routes";

export default function PortalPage(): never {
    redirect(portalRoutes.sessions);
}
