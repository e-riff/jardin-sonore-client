import type {JSX} from "react";

interface EditorialCopyProps {
    className?: string;
    text: string;
}

export default function EditorialCopy({className = "", text}: EditorialCopyProps): JSX.Element {
    return <div className={`space-y-4 ${className}`}>
        {text.split("\n\n").map((paragraph) => <p key={paragraph}>
            {paragraph.split(/(\*\*[^*]+\*\*)/g).map((part, index) => part.startsWith("**") && part.endsWith("**")
                ? <strong className="font-semibold text-on-surface" key={index}>{part.slice(2, -2)}</strong>
                : part)}
        </p>)}
    </div>;
}
