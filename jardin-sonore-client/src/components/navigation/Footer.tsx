"use client";

import {JSX} from "react";
import Image from "next/image";
import BrandLogo from "@/components/BrandLogo";
import {getCurrentYear} from "@/lib/dates";
import {LinkItem} from "@/types/content";
import ShareButton from "@/components/navigation/ShareButton";
import {useTranslations} from "@/i18n/translations-provider";

export default function Footer(): JSX.Element {
    const dictionary = useTranslations();
    const content = dictionary.footer;
    return (
        <footer className="bg-surface-container-high px-6 py-9 text-center sm:px-margin lg:py-8 lg:text-left">
            <div className="mx-auto max-w-7xl">
                <div className="grid gap-8 lg:grid-cols-[minmax(0,1.35fr)_auto_auto] lg:items-start lg:gap-12">
                    <div className="mx-auto max-w-130 lg:mx-0">
                        <BrandLogo label={dictionary.brand.name} className="text-2xl font-semibold" colorized={false} />
                        <p className="mt-4 font-serif text-xl italic leading-8 text-on-surface-variant">
                            {dictionary.hero.tagline}
                        </p>
                        <p className="mt-3 font-sans text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant/70">
                            {dictionary.hero.serviceArea}
                        </p>
                    </div>

                    <div className="flex flex-col items-center gap-6 lg:flex-row lg:items-start lg:gap-8">
                        <div role="group" aria-labelledby="footer-social-title">
                            <p className="mb-3 font-sans text-xs font-bold uppercase tracking-[0.18em] text-primary" id="footer-social-title">{content.socialTitle}</p>
                            <div className="flex justify-center gap-3 lg:justify-start" aria-label={content.socialAriaLabel}>
                                <a className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-primary/10 bg-background text-primary transition hover:bg-primary hover:text-on-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-container-high"
                                   href="https://www.instagram.com/jardin.sonore/"
                                   aria-label="Instagram"
                                   target="_blank"
                                   rel="noreferrer"
                                >
                                    <Image
                                        src="/instagram.svg"
                                        width={20}
                                        height={20}
                                        alt=""
                                        className="h-5 w-5"
                                    />
                                </a>
                                <a className="inline-flex h-11 w-11 items-center justify-center rounded-full border border-primary/10 bg-background font-serif text-xl font-bold text-primary transition hover:bg-primary hover:text-on-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2 focus-visible:ring-offset-surface-container-high"
                                   href="https://www.facebook.com/Jardin.sonore"
                                   aria-label="Facebook"
                                   target="_blank"
                                   rel="noreferrer"
                                >
                                    <Image
                                        src="/facebook.svg"
                                        width={20}
                                        height={20}
                                        alt=""
                                        className="h-5 w-5"
                                    />
                                </a>
                            </div>
                        </div>

                        <div role="group" aria-labelledby="footer-share-title">
                            <p className="mb-3 font-sans text-xs font-bold uppercase tracking-[0.18em] text-primary" id="footer-share-title">{content.shareTitle}</p>
                            <ShareButton
                                label={content.shareAriaLabel}
                                copiedLabel={content.shareCopiedLabel}
                                title={dictionary.brand.name}
                                text={dictionary.metadata.socialDescription}
                            />
                        </div>
                    </div>

                    <nav className="hidden lg:block" aria-label={content.ariaLabel}>
                        <p className="mb-4 font-sans text-xs font-bold uppercase tracking-[0.18em] text-primary">{content.navigationTitle}</p>
                        <div className="grid grid-cols-2 gap-x-8 gap-y-3">
                            {content.links.map((link: LinkItem) => (
                                <a className="font-sans text-sm font-semibold text-on-surface-variant transition hover:text-primary" href={link.href} key={link.label}>
                                    {link.label}
                                </a>
                            ))}
                        </div>
                    </nav>
                </div>

                <div className="mt-7 border-t border-outline-variant/30 pt-5 text-center">
                    <p className="text-sm leading-6 text-on-surface-variant/80">
                        © {getCurrentYear()} {content.copyrightHolder}. {content.copyrightDescription}
                    </p>
                </div>
            </div>
        </footer>
    );
}
