import Image from "next/image";
import {JSX} from "react";
import {ExplorationPhoto} from "@/types/content";

interface PhotoTileProps extends ExplorationPhoto {
    featured?: boolean;
    className?: string;
    sizes: string;
}

export default function PhotoTile({title, imageSrc, imageAlt, featured = false, className = "", sizes}: PhotoTileProps): JSX.Element {
    return (
        <figure className={`group relative overflow-hidden rounded-xl border border-outline-variant/30 bg-surface-container-lowest soft-shadow ${className}`}>
            <Image
                alt={imageAlt}
                className="h-full w-full object-cover transition duration-700 group-hover:scale-105"
                fill
                sizes={sizes}
                src={imageSrc}
            />
            {featured ? (
                <>
                    <div className="absolute inset-0 bg-gradient-to-t from-on-surface/70 via-on-surface/10 to-transparent opacity-80 transition-opacity duration-300 group-hover:opacity-95" />
                    <figcaption className="absolute inset-x-0 bottom-0 p-6 text-on-primary sm:p-8">
                        <h3 className="font-serif text-2xl font-semibold">{title}</h3>
                    </figcaption>
                </>
            ) : null}
        </figure>
    );
}
