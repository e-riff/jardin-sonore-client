'use client';

import {Bars3Icon, XMarkIcon} from "@heroicons/react/24/outline";
import {JSX, useEffect, useState} from "react";
import BrandLogo from "@/components/BrandLogo";
import Button from "@/components/Button";
import {useTranslations} from "@/i18n/translations-provider";
import {LinkItem} from "@/types/content";

export default function Header(): JSX.Element {
    const [scrolled, setScrolled] = useState<boolean>(false);
    const [menuOpen, setMenuOpen] = useState<boolean>(false);
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
            <nav className="mx-auto max-w-7xl px-6 sm:px-margin" aria-label={content.ariaLabel}>
                <div className="flex items-center justify-between">
                    <a href="#" aria-label={content.homeAriaLabel} onClick={() => setMenuOpen(false)}>
                        <BrandLogo label={dictionary.brand.name} className="text-2xl font-semibold md:text-[1.7rem]" />
                    </a>

                    <div className="hidden items-center gap-8 md:flex">
                        {content.navigation.map((item: LinkItem) => (
                            <a className="font-sans text-sm font-semibold tracking-wider text-on-surface-variant transition hover:text-primary" href={item.href} key={item.href}>
                                {item.label}
                            </a>
                        ))}
                        <Button className="px-6 py-2" href="#contact">{content.reserveCta}</Button>
                    </div>

                    <button
                        className="rounded-full p-2 text-primary transition hover:bg-primary/10 md:hidden"
                        type="button"
                        aria-controls="mobile-menu"
                        aria-expanded={menuOpen}
                        aria-label={content.menuAriaLabel}
                        onClick={() => setMenuOpen((isOpen) => !isOpen)}
                    >
                        {menuOpen ? <XMarkIcon className="h-6 w-6" /> : <Bars3Icon className="h-6 w-6" />}
                    </button>
                </div>

                <div className={`${menuOpen ? "grid" : "hidden"} gap-2 pb-3 pt-5 md:hidden`} id="mobile-menu">
                    {content.navigation.map((item: LinkItem) => (
                        <a
                            className="rounded-xl px-4 py-3 font-sans text-sm font-semibold tracking-wider text-on-surface-variant transition hover:bg-primary/10 hover:text-primary"
                            href={item.href}
                            key={item.href}
                            onClick={() => setMenuOpen(false)}
                        >
                            {item.label}
                        </a>
                    ))}
                    <a
                        className="mt-2 inline-flex w-full items-center justify-center rounded-full border border-primary bg-primary px-6 py-3 font-sans text-sm font-bold tracking-wider text-on-primary soft-shadow transition duration-200 hover:-translate-y-0.5 hover:bg-primary-container"
                        href="#contact"
                        onClick={() => setMenuOpen(false)}
                    >
                        {content.reserveCta}
                    </a>
                </div>
            </nav>
        </header>
    );
}
