"use client";

import {useEffect, useRef, useState, type JSX} from "react";
import {Bars3Icon, UserCircleIcon, XMarkIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import Link from "next/link";
import {usePathname} from "next/navigation";
import BrandLogo from "@/components/BrandLogo";
import {portalAccountDisplayName, portalAvatarUrl} from "@/lib/portal/types";
import {portalRoutes} from "@/lib/portal/routes";
import {useTranslations} from "@/i18n/translations-provider";
import {usePortalAccount} from "@/components/portal/PortalAccountProvider";

interface PortalAccountHeaderProps {
    onLogout: () => Promise<void>;
}

export default function PortalAccountHeader({onLogout}: PortalAccountHeaderProps): JSX.Element {
    const {account} = usePortalAccount();
    const content = useTranslations().portal.shell;
    const accountMenuRef = useRef<HTMLDetailsElement>(null);
    const pathname = usePathname();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const accountInitials = `${account.firstName?.[0] ?? ""}${account.lastName?.[0] ?? ""}`.toUpperCase();
    const accountDisplayName = portalAccountDisplayName(account);
    const closeAccountMenu = (): void => accountMenuRef.current?.removeAttribute("open");
    const isSessionsActive = pathname.startsWith("/portail/seances");
    const isProfileActive = pathname.startsWith("/portail/compte");
    const navigationLinkClassName = (isActive: boolean): string => `inline-flex items-center gap-1.5 hover:text-primary ${isActive ? "text-primary" : ""}`;
    const mobileNavigationLinkClassName = (isActive: boolean): string => `flex items-center gap-2 rounded-md px-3 py-2 font-semibold text-primary hover:bg-primary/10 ${isActive ? "bg-primary/10" : ""}`;
    const closeMobileMenu = (): void => setMobileMenuOpen(false);

    useEffect(() => {
        const closeOnOutsidePointerDown = (event: PointerEvent): void => {
            if (!accountMenuRef.current?.contains(event.target as Node)) closeAccountMenu();
        };
        const closeOnEscape = (event: KeyboardEvent): void => {
            if (event.key === "Escape") {
                closeAccountMenu();
                closeMobileMenu();
            }
        };

        document.addEventListener("pointerdown", closeOnOutsidePointerDown);
        document.addEventListener("keydown", closeOnEscape);
        return (): void => {
            document.removeEventListener("pointerdown", closeOnOutsidePointerDown);
            document.removeEventListener("keydown", closeOnEscape);
        };
    }, []);

    return <header className="border-b border-primary/20 border-t-4 border-primary bg-surface-container-lowest px-4 py-4 sm:px-6">
        <div className="mx-auto max-w-6xl">
            <div className="flex items-center justify-between gap-4">
                <div className="flex flex-col items-start">
                    <Link aria-label={content.homeAriaLabel} href={portalRoutes.sessions} onClick={() => { closeAccountMenu(); closeMobileMenu(); }}><BrandLogo className="text-xl font-semibold sm:text-2xl" label="Jardin Sonore" /></Link>
                    <Link className="mt-0.5 text-[0.65rem] font-semibold uppercase tracking-[0.12em] text-on-surface-variant hover:text-primary" href="/" onClick={() => { closeAccountMenu(); closeMobileMenu(); }}>{content.publicSiteReturnLabel}</Link>
                </div>
                <div className="hidden items-center gap-3 lg:flex">
                    <nav aria-label={content.navigationLabel} className="flex items-center gap-5 text-sm font-semibold text-on-surface-variant">
                        <Link aria-current={isSessionsActive ? "page" : undefined} className={navigationLinkClassName(isSessionsActive)} href="/portail/seances" onClick={closeAccountMenu}>{isSessionsActive ? <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-primary" /> : null}{content.sessionsLink}</Link>
                        <button className="flex cursor-not-allowed flex-col items-start leading-none text-on-surface-variant/60" disabled type="button"><span>{content.nurseryRhymesLink}</span><span className="mt-1 text-[0.6rem] font-medium uppercase tracking-[0.12em]">{content.comingSoon}</span></button>
                        <button className="flex cursor-not-allowed flex-col items-start leading-none text-on-surface-variant/60" disabled type="button"><span>{content.activitiesLink}</span><span className="mt-1 text-[0.6rem] font-medium uppercase tracking-[0.12em]">{content.comingSoon}</span></button>
                    </nav>
                    <details className="group relative" ref={accountMenuRef}>
                    <summary aria-label={content.accountMenu} className="flex h-10 cursor-pointer list-none items-center gap-2 rounded-full border border-primary/25 bg-surface-container-low px-1.5 pr-3 text-primary" title={account.email}>
                        <span className="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-container-lowest">
                            {account.avatarPath ? <Image alt="" className="h-full w-full object-cover" height={28} src={portalAvatarUrl(account.avatarPath)} unoptimized width={28} /> : accountInitials ? <span aria-hidden="true" className="text-[0.65rem] font-bold">{accountInitials}</span> : <UserCircleIcon aria-hidden="true" className="h-5 w-5" />}
                        </span>
                        <span className="hidden max-w-44 truncate text-sm font-semibold text-on-surface-variant md:block">{accountDisplayName}</span>
                    </summary>
                    <div className="absolute right-0 top-10 z-10 w-56 rounded-lg border border-outline-variant bg-white p-2 text-sm shadow-lg">
                        <p className="truncate px-3 pt-2 text-xs font-semibold text-on-surface-variant">{accountDisplayName}</p>
                        <p className="truncate px-3 pb-2 text-xs text-on-surface-variant">{account.email}</p>
                        <Link aria-current={isProfileActive ? "page" : undefined} className={mobileNavigationLinkClassName(isProfileActive)} href="/portail/compte" onClick={closeAccountMenu}>{isProfileActive ? <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-primary" /> : null}{content.profileLink}</Link>
                        <Link className="block rounded-md px-3 py-2 font-semibold text-primary hover:bg-primary/10" href="/portail/reinitialiser-mot-de-passe" onClick={closeAccountMenu}>{content.resetPassword}</Link>
                        <form action={onLogout} onSubmit={closeAccountMenu}><button className="w-full cursor-pointer rounded-md px-3 py-2 text-left font-semibold text-primary hover:bg-primary/10" type="submit">{content.logout}</button></form>
                    </div>
                    </details>
                </div>
                <div className="flex items-center gap-2 lg:hidden">
                    <Link aria-label={content.profileLink} className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-primary/25 bg-surface-container-low text-primary" href="/portail/compte" onClick={closeMobileMenu} title={accountDisplayName}>
                        {account.avatarPath ? <Image alt="" className="h-full w-full object-cover" height={40} src={portalAvatarUrl(account.avatarPath)} unoptimized width={40} /> : accountInitials ? <span aria-hidden="true" className="text-xs font-bold">{accountInitials}</span> : <UserCircleIcon aria-hidden="true" className="h-5 w-5" />}
                    </Link>
                    <button aria-controls="portal-mobile-menu" aria-expanded={mobileMenuOpen} aria-label={mobileMenuOpen ? "Fermer le menu" : content.navigationLabel} className="rounded-full p-2 text-primary transition hover:bg-primary/10" onClick={() => setMobileMenuOpen((isOpen) => !isOpen)} type="button">
                        {mobileMenuOpen ? <XMarkIcon aria-hidden="true" className="h-6 w-6" /> : <Bars3Icon aria-hidden="true" className="h-6 w-6" />}
                    </button>
                </div>
            </div>
            <div aria-hidden={!mobileMenuOpen} className={`${mobileMenuOpen ? "grid" : "hidden"} gap-2 pb-2 pt-5 lg:hidden`} id="portal-mobile-menu">
                <nav aria-label={content.navigationLabel} className="grid gap-1 border-b border-outline-variant pb-3">
                    <Link aria-current={isSessionsActive ? "page" : undefined} className={mobileNavigationLinkClassName(isSessionsActive)} href="/portail/seances" onClick={closeMobileMenu}>{isSessionsActive ? <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-primary" /> : null}{content.sessionsLink}</Link>
                    <button className="flex cursor-not-allowed flex-col items-start rounded-md px-3 py-2 text-left font-semibold text-on-surface-variant/60" disabled type="button"><span>{content.nurseryRhymesLink}</span><span className="mt-1 text-[0.6rem] font-medium uppercase tracking-[0.12em]">{content.comingSoon}</span></button>
                    <button className="flex cursor-not-allowed flex-col items-start rounded-md px-3 py-2 text-left font-semibold text-on-surface-variant/60" disabled type="button"><span>{content.activitiesLink}</span><span className="mt-1 text-[0.6rem] font-medium uppercase tracking-[0.12em]">{content.comingSoon}</span></button>
                </nav>
                <div className="px-3 pt-2">
                    <p className="truncate text-xs font-semibold text-on-surface-variant">{accountDisplayName}</p>
                    <p className="mt-0.5 truncate text-xs text-on-surface-variant">{account.email}</p>
                </div>
                <Link aria-current={isProfileActive ? "page" : undefined} className={mobileNavigationLinkClassName(isProfileActive)} href="/portail/compte" onClick={closeMobileMenu}>{isProfileActive ? <span aria-hidden="true" className="h-1.5 w-1.5 rounded-full bg-primary" /> : null}{content.profileLink}</Link>
                <Link className="rounded-md px-3 py-2 font-semibold text-primary hover:bg-primary/10" href="/portail/reinitialiser-mot-de-passe" onClick={closeMobileMenu}>{content.resetPassword}</Link>
                <form action={onLogout} onSubmit={closeMobileMenu}><button className="w-full cursor-pointer rounded-md px-3 py-2 text-left font-semibold text-primary hover:bg-primary/10" type="submit">{content.logout}</button></form>
            </div>
        </div>
    </header>;
}
