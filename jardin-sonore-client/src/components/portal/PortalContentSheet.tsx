import type {ReactNode} from "react";

interface PortalContentSheetProps {
    children: ReactNode;
}

export default function PortalContentSheet({children}: PortalContentSheetProps): ReactNode {
    return <article className="portal-content-sheet">{children}</article>;
}
