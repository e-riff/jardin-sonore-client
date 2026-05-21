'use client';

import {Bars3Icon} from "@heroicons/react/24/outline";
import {JSX, useEffect, useState} from "react";
import BrandLogo from "@/components/BrandLogo";
import Button from "@/components/Button";
import {useTranslations} from "@/i18n/translations-provider";
import {LinkItem} from "@/types/content";

export default function Header(): JSX.Element {
    const [scrolled, setScrolled] = useState<boolean>(false);
    const dictionary = useTranslations();
    const content = dictionary.header;

    useEffect(() => {
        const onScroll = (): void => setScrolled(window.scrollY > 24);
        onScroll();
        window.addEventListener("scroll", onScroll);
        return () => window.removeEventListener("scroll", onScroll);
    }, []);

    return (
        <header className={`fixed left-0 top-0 z-50 w-full border-b transition duration-300 ${scrolled ? "border-outline-variant/40 bg-background/88 py-2 backdrop-blur-xl" : "border-transparent bg-background/72 py-3 backdrop-blur-md"}`}>
            <nav className="mx-auto flex max-w-[1280px] items-center justify-between px-6 sm:px-margin" aria-label={content.ariaLabel}>
                <a href="#top" aria-label={content.homeAriaLabel}>
                    <BrandLogo label={dictionary.brand.name} className="text-2xl font-semibold sm:text-2xl" />
                </a>

                <div className="hidden items-center gap-8 md:flex">
                    {content.navigation.map((item: LinkItem) => (
                        <a className="font-sans text-sm font-semibold tracking-[0.05em] text-on-surface-variant transition hover:text-primary" href={item.href} key={item.href}>
                            {item.label}
                        </a>
                    ))}
                    <Button className="px-6 py-2" href="#contact">{content.reserveCta}</Button>
                </div>

                <button className="rounded-full p-2 text-primary md:hidden" type="button" aria-label={content.menuAriaLabel}>
                    <Bars3Icon className="h-6 w-6" />
                </button>
            </nav>
        </header>
    );
}
