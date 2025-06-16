import React, {JSX} from "react";

interface ButtonProps extends React.HTMLAttributes<HTMLDivElement> {
    children: React.ReactNode
    href?: string
    className?: string
}

export default function Button({children, href, className = ''}: ButtonProps): JSX.Element {
    const baseStyles =
        'inline-block mt-6 px-6 py-3 bg-white rounded-2xl shadow hover:shadow-lg transition text-[#243237]'
    const combined = `${baseStyles} ${className}`

    if (href) {
        return (
            <a href={href} className={combined}>
                {children}
            </a>
        )
    }
    return (
        <button className={combined}>
            {children}
        </button>
    )
}