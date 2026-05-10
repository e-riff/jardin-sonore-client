import {JSX, ReactNode} from "react";

interface CardProps {
    children: ReactNode;
    className?: string;
}

export default function Card({children, className = ""}: CardProps): JSX.Element {
    return (
        <div className={`rounded-2xl bg-surface-container-lowest p-8 ambient-shadow ${className}`}>
            {children}
        </div>
    );
}
