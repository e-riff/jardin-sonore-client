import {JSX} from "react";
import Image from "next/image";
import BrandLogo from "@/components/BrandLogo";
import {getTranslations} from "@/i18n/server";

export default async function Footer(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.footer;

    return (
        <footer className="bg-surface-container-high px-6 py-16 text-center sm:px-margin">
            <div className="mx-auto flex max-w-[1280px] flex-col items-center">
                <BrandLogo label={dictionary.brand.name} className="text-2xl font-semibold" colorized={false} />
{/*                <nav className="mt-9 flex flex-wrap justify-center gap-x-10 gap-y-4" aria-label={content.ariaLabel}>
                    {content.links.map((link: LinkItem) => (
                        <a className="font-sans text-xs font-semibold text-on-surface-variant transition hover:text-primary" href={link.href} key={link.label}>{link.label}</a>
                    ))}
                </nav>*/}
                <div className="mt-9 flex gap-4">
                    <a className="flex h-11 w-11 items-center justify-center rounded-full border border-primary/10 bg-primary/5 text-primary transition hover:bg-primary hover:text-on-primary"
                       href="https://www.instagram.com/jardin.sonore/"
                       aria-label="instagram"
                       target={"_blank"}
                    >
                        <Image
                            src="/instagram.svg"
                            width={20}
                            height={20}
                            alt="Instagram du Jardin Sonore"
                            className="h-5 w-5"
                        />
                    </a>
                </div>
                <p className="mt-10 w-full max-w-2xl border-t border-outline-variant/30 pt-10 text-sm leading-7 text-on-surface-variant/80">
                    {content.copyright}
                </p>
            </div>
        </footer>
    );
}
