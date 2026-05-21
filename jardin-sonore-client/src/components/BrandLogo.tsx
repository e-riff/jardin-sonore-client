import {JSX} from "react";

const letterClasses: string[] = [
    "text-primary",
    "text-secondary",
    "text-tertiary",
    "text-on-primary-fixed-variant",
    "text-on-secondary-container",
];

interface BrandLogoProps {
    label: string;
    className?: string;
    colorized?: boolean;
}

export default function BrandLogo({label, className = "", colorized = true}: BrandLogoProps): JSX.Element {
    if (!colorized) {
        return <span className={`font-serif text-primary ${className}`}>{label}</span>;
    }

    return (
        <span aria-label={label} className={`font-serif tracking-tight ${className}`}>
            {[...label].map((char, index) => char === " "
                ? <span aria-hidden="true" className="inline-block w-[0.28em]" key={index} />
                : <span className={letterClasses[index % letterClasses.length]} key={`${char}-${index}`}>{char}</span>)}
        </span>
    );
}
