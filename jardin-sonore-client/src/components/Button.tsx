import {JSX, ReactNode} from "react";

type ButtonVariant = "primary" | "secondary" | "light";

interface ButtonProps {
    children: ReactNode;
    href?: string;
    variant?: ButtonVariant;
    className?: string;
}

const variantClasses: Record<ButtonVariant, string> = {
    primary: "bg-primary text-on-primary border-primary hover:-translate-y-0.5 hover:bg-primary-container",
    secondary: "bg-surface/70 text-primary border-primary/35 hover:-translate-y-0.5 hover:border-primary hover:bg-primary-fixed/35",
    light: "bg-surface text-primary border-surface hover:-translate-y-0.5 hover:bg-primary-fixed",
};

export default function Button({children, href, variant = "primary", className = ""}: ButtonProps): JSX.Element {
    const classes = `inline-flex items-center justify-center rounded-full border px-7 py-3 font-sans text-sm font-bold tracking-wider transition duration-200 soft-shadow ${variantClasses[variant]} ${className}`;

    if (href) {
        return <a className={classes} href={href}>{children}</a>;
    }

    return <button className={classes} type="button">{children}</button>;
}
