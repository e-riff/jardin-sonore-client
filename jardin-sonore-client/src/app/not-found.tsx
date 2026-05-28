import Link from "next/link";
import {JSX} from "react";

export default function NotFound(): JSX.Element {
    return (
        <section className="px-6 py-32 sm:px-margin lg:py-40">
            <div className="mx-auto max-w-3xl text-center">
                <p className="font-sans text-sm font-bold uppercase tracking-[0.22em] text-secondary">Page introuvable</p>
                <h1 className="mt-4 font-serif text-4xl font-semibold leading-tight text-primary sm:text-5xl">
                    Cette page n&apos;existe pas
                </h1>
                <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-on-surface-variant">
                    Le lien suivi ne correspond à aucune page du site Jardin Sonore. Vous pouvez revenir à l&apos;accueil ou nous contacter pour une demande d&apos;intervention.
                </p>

                <div className="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    <Link
                        className="inline-flex min-w-52 items-center justify-center rounded-full border border-primary bg-primary px-8 py-3 font-sans text-sm font-bold tracking-wider text-on-primary soft-shadow transition duration-200 hover:-translate-y-0.5 hover:bg-primary-container"
                        href="/"
                    >
                        Retour à l&apos;accueil
                    </Link>
                    <Link
                        className="inline-flex min-w-52 items-center justify-center rounded-full border border-primary/35 bg-surface/70 px-8 py-3 font-sans text-sm font-bold tracking-wider text-primary soft-shadow transition duration-200 hover:-translate-y-0.5 hover:border-primary hover:bg-primary-fixed/35"
                        href="/#contact"
                    >
                        Nous contacter
                    </Link>
                </div>
            </div>
        </section>
    );
}
