import type {JSX} from "react";

interface EditorialCopyProps {
    className?: string;
    text: string;
    links?: Record<string, string>;
}

export default function EditorialCopy({className = "", text, links = {}}: EditorialCopyProps): JSX.Element {
    return <div className={`space-y-4 ${className}`}>
        {text.split("\n\n").map((paragraph) => <p key={paragraph}>
            {paragraph.split(/(\*\*[^*]+\*\*)/g).map((part, index) => part.startsWith("**") && part.endsWith("**")
                ? links[part.slice(2, -2)]
                    ? <strong className="font-semibold text-on-surface" key={index}><a className="underline underline-offset-4 hover:text-primary" href={links[part.slice(2, -2)]}>{part.slice(2, -2)}</a></strong>
                    : <strong className="font-semibold text-on-surface" key={index}>{part.slice(2, -2)}</strong>
                : part)}
        </p>)}
    </div>;
}
