'use client';

import {Bars3Icon, ChevronDownIcon, UserCircleIcon, XMarkIcon} from "@heroicons/react/24/outline";
import {JSX, useEffect, useRef, useState} from "react";
import Link from "next/link";
import BrandLogo from "@/components/BrandLogo";
import Button from "@/components/Button";
import {useTranslations} from "@/i18n/translations-provider";
import {LinkItem} from "@/types/content";

const showDiscoverNavigation = false;

export default function Header(): JSX.Element {
    const [scrolled, setScrolled] = useState<boolean>(false);
    const [discoverOpen, setDiscoverOpen] = useState<boolean>(false);
    const discoverRef = useRef<HTMLDivElement>(null);
    const [menuOpen, setMenuOpen] = useState<boolean>(false);
    const dictionary = useTranslations();
    const content = dictionary.header;

    useEffect(() => {
        const onScroll = (): void => setScrolled(window.scrollY > 24);
        onScroll();
        window.addEventListener("scroll", onScroll);
        return () => window.removeEventListener("scroll", onScroll);
    }, []);

    useEffect(() => {
        if (!menuOpen) {
            return;
        }

        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key === "Escape") {
                setMenuOpen(false);
            }
        };

        window.addEventListener("keydown", onKeyDown);
        return () => window.removeEventListener("keydown", onKeyDown);
    }, [menuOpen]);

    useEffect(() => {
        if (!discoverOpen) return;
        const onPointerDown = (event: PointerEvent): void => {
            if (!discoverRef.current?.contains(event.target as Node)) setDiscoverOpen(false);
        };
        const onKeyDown = (event: KeyboardEvent): void => {
            if (event.key === "Escape") {
                setDiscoverOpen(false);
                discoverRef.current?.querySelector("button")?.focus();
            }
        };
        document.addEventListener("pointerdown", onPointerDown);
        document.addEventListener("keydown", onKeyDown);
        return () => {
            document.removeEventListener("pointerdown", onPointerDown);
            document.removeEventListener("keydown", onKeyDown);
        };
    }, [discoverOpen]);

    return (
        <header className={`fixed left-0 top-0 z-50 w-full border-b transition duration-300 ${scrolled ? "border-outline-variant/40 bg-background/88 py-2 backdrop-blur-xl" : "border-transparent bg-background/72 py-3 backdrop-blur-md"}`}>
            <nav className="mx-auto max-w-7xl px-6 sm:px-margin" aria-label={content.ariaLabel}>
                <div className="flex items-center justify-between">
                    <Link href="/" aria-label={content.homeAriaLabel} onClick={() => setMenuOpen(false)}>
                        <BrandLogo label={dictionary.brand.name} className="text-2xl font-semibold md:text-[1.7rem]" />
                    </Link>

                    <div className="hidden items-center gap-5 xl:flex">
                        {content.navigation.map((item: LinkItem) => (
                            <a className="font-sans text-sm font-semibold tracking-wider text-on-surface-variant transition hover:text-primary" href={`/${item.href}`} key={item.href}>
                                {item.label}
                            </a>
                        ))}
                        {showDiscoverNavigation ? <div className="relative" onBlur={(event) => {
                            if (!event.currentTarget.contains(event.relatedTarget)) setDiscoverOpen(false);
                        }} ref={discoverRef}>
                            <button aria-expanded={discoverOpen} className="inline-flex items-center gap-1 whitespace-nowrap font-sans text-sm font-semibold tracking-wider text-on-surface-variant transition hover:text-primary" onClick={() => setDiscoverOpen((open) => !open)} type="button">
                                {content.discoverLabel} <ChevronDownIcon aria-hidden="true" className="h-3.5 w-3.5" />
                            </button>
                            {discoverOpen ? <div className="absolute right-0 top-full mt-3 w-64 rounded-xl border border-outline-variant/40 bg-background p-2 soft-shadow">
                                {content.discoverLinks.map((item) => <Link className="block rounded-lg px-4 py-3 font-sans text-sm font-semibold text-on-surface-variant hover:bg-primary/10 hover:text-primary" href={item.href} key={item.href} onClick={() => setDiscoverOpen(false)}>{item.label}</Link>)}
                            </div> : null}
                        </div> : null}
                        <Button className="px-5 py-2 xl:px-6" href="/#contact">{content.reserveCta}</Button>
                        <Link
                            aria-label={content.portalLink}
                            className="rounded-full p-2 text-on-surface-variant transition hover:bg-primary/10 hover:text-primary"
                            href="/portail/seances"
                            title={content.portalLink}
                        >
                            <UserCircleIcon aria-hidden="true" className="h-[1.35rem] w-[1.35rem]" />
                        </Link>
                    </div>

                    <button
                        className="rounded-full p-2 text-primary transition hover:bg-primary/10 xl:hidden"
                        type="button"
                        aria-controls="mobile-menu"
                        aria-expanded={menuOpen}
                        aria-label={menuOpen ? "Fermer le menu" : content.menuAriaLabel}
                        onClick={() => setMenuOpen((isOpen) => !isOpen)}
                    >
                        {menuOpen ? <XMarkIcon aria-hidden="true" className="h-6 w-6" /> : <Bars3Icon aria-hidden="true" className="h-6 w-6" />}
                    </button>
                </div>

                <div className={`${menuOpen ? "grid" : "hidden"} gap-2 pb-3 pt-5 xl:hidden`} id="mobile-menu" aria-hidden={!menuOpen}>
                    {content.navigation.map((item: LinkItem) => (
                        <a
                            className="rounded-xl px-4 py-3 font-sans text-sm font-semibold tracking-wider text-on-surface-variant transition hover:bg-primary/10 hover:text-primary"
                            href={`/${item.href}`}
                            key={item.href}
                            onClick={() => setMenuOpen(false)}
                        >
                            {item.label}
                        </a>
                    ))}
                    {showDiscoverNavigation ? content.discoverLinks.map((item) => <Link className="rounded-xl px-4 py-3 font-sans text-sm font-semibold text-on-surface-variant transition hover:bg-primary/10 hover:text-primary" href={item.href} key={item.href} onClick={() => setMenuOpen(false)}>{item.label}</Link>) : null}
                    <Link
                        className="rounded-xl px-4 py-3 font-sans text-sm font-semibold text-on-surface-variant transition hover:bg-primary/10 hover:text-primary"
                        href="/portail/seances"
                        onClick={() => setMenuOpen(false)}
                    >
                        {content.portalLink}
                    </Link>
                    <Link
                        className="mt-2 inline-flex w-full items-center justify-center rounded-full border border-primary bg-primary px-6 py-3 font-sans text-sm font-bold tracking-wider text-on-primary soft-shadow transition duration-200 hover:-translate-y-0.5 hover:bg-primary-container"
                        href="/#contact"
                        onClick={() => setMenuOpen(false)}
                    >
                        {content.reserveCta}
                    </Link>
                </div>
            </nav>
        </header>
    );
}
