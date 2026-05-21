import {EnvelopeIcon, ShareIcon} from "@heroicons/react/24/outline";
import {JSX} from "react";
import BrandLogo from "@/components/BrandLogo";
import {getTranslations} from "@/i18n/server";
import {LinkItem} from "@/types/content";

export default async function Footer(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.footer;

    return (
        <footer className="bg-surface-container-high px-6 py-16 text-center sm:px-margin">
            <div className="mx-auto flex max-w-[1280px] flex-col items-center">
                <BrandLogo label={dictionary.brand.name} className="text-2xl font-semibold" colorized={false} />
                <nav className="mt-9 flex flex-wrap justify-center gap-x-10 gap-y-4" aria-label={content.ariaLabel}>
                    {content.links.map((link: LinkItem) => (
                        <a className="font-sans text-xs font-semibold text-on-surface-variant transition hover:text-primary" href={link.href} key={link.label}>{link.label}</a>
                    ))}
                </nav>
                <div className="mt-9 flex gap-4">
                    <a className="flex h-11 w-11 items-center justify-center rounded-full border border-primary/10 bg-primary/5 text-primary transition hover:bg-primary hover:text-on-primary" href="#top" aria-label={content.shareAriaLabel}>
                        <ShareIcon className="h-5 w-5" />
                    </a>
                    <a className="flex h-11 w-11 items-center justify-center rounded-full border border-primary/10 bg-primary/5 text-primary transition hover:bg-primary hover:text-on-primary" href="mailto:contact@jardin-sonore.fr" aria-label={content.emailAriaLabel}>
                        <EnvelopeIcon className="h-5 w-5" />
                    </a>
                </div>
                <p className="mt-10 w-full max-w-2xl border-t border-outline-variant/30 pt-10 text-sm leading-7 text-on-surface-variant/80">
                    {content.copyright}
                </p>
            </div>
        </footer>
    );
}
