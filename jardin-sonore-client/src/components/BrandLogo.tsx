import {JSX} from "react";

const letters: {char: string; className: string}[] = [
    {char: "J", className: "text-primary"},
    {char: "a", className: "text-secondary"},
    {char: "r", className: "text-tertiary"},
    {char: "d", className: "text-on-primary-fixed-variant"},
    {char: "i", className: "text-on-secondary-container"},
    {char: "n", className: "text-primary"},
    {char: " ", className: ""},
    {char: "S", className: "text-secondary"},
    {char: "o", className: "text-tertiary"},
    {char: "n", className: "text-on-primary-fixed-variant"},
    {char: "o", className: "text-on-secondary-container"},
    {char: "r", className: "text-primary"},
    {char: "e", className: "text-secondary"},
];

interface BrandLogoProps {
    className?: string;
    colorized?: boolean;
}

export default function BrandLogo({className = "", colorized = true}: BrandLogoProps): JSX.Element {
    if (!colorized) {
        return <span className={`font-serif text-primary ${className}`}>Jardin Sonore</span>;
    }

    return (
        <span aria-label="Jardin Sonore" className={`font-serif tracking-tight ${className}`}>
            {letters.map((letter, index) => letter.char === " "
                ? <span aria-hidden="true" className="inline-block w-[0.28em]" key={index} />
                : <span className={letter.className} key={`${letter.char}-${index}`}>{letter.char}</span>)}
        </span>
    );
}
