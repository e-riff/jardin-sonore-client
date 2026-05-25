import {JSX, ReactNode} from "react";

interface SectionProps {
    children: ReactNode;
    id?: string;
    className?: string;
}

export default function Section({children, id, className = ""}: SectionProps): JSX.Element {
    return (
        <section className={`px-6 py-xl sm:px-margin ${className}`} id={id}>
            <div className="mx-auto max-w-320">
                {children}
            </div>
        </section>
    );
}
