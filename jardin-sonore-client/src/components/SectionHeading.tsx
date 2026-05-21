import {JSX} from "react";

interface SectionHeadingProps {
    eyebrow?: string;
    title: string;
    description?: string;
    centered?: boolean;
}

export default function SectionHeading({eyebrow, title, description, centered = false}: SectionHeadingProps): JSX.Element {
    return (
        <div className={`max-w-2xl ${centered ? "mx-auto text-center" : ""}`}>
            {eyebrow ? <p className="mb-3 font-sans text-xs font-bold uppercase tracking-[0.22em] text-secondary">{eyebrow}</p> : null}
            <h2 className="font-serif text-3xl font-semibold leading-tight text-primary sm:text-4xl">{title}</h2>
            {description ? <p className="mt-4 text-base leading-7 text-on-surface-variant sm:text-lg">{description}</p> : null}
        </div>
    );
}
