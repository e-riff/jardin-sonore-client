import type {Metadata} from "next";
import {JSX, ReactNode} from "react";
import Footer from "@/components/navigation/Footer";
import Header from "@/components/navigation/Header";
import fr from "@/i18n/dictionaries/fr";
import {defaultLocale} from "@/i18n/locales";
import {getTranslations} from "@/i18n/server";
import {TranslationsProvider} from "@/i18n/translations-provider";
import "./globals.css";

export const metadata: Metadata = {
    title: {
        default: fr.brand.name,
        template: fr.metadata.titleTemplate,
    },
    description: fr.metadata.description,
    icons: {
        icon: [
            {url: "/favicon/favicon.ico", sizes: "any", type: "image/x-icon"},
            {url: "/favicon/favicon-96x96.png", sizes: "96x96", type: "image/png"},
            {url: "/favicon/favicon.svg", sizes: "any", type: "image/svg+xml"},
        ],
        shortcut: "/favicon/favicon-96x96.png",
        apple: "/favicon/apple-touch-icon.png",
        other: [
            {rel: "manifest", url: "/favicon/site.webmanifest"},
            {rel: "mask-icon", url: "/favicon/favicon.svg", color: "#87362d"},
        ],
    },
};

interface RootLayoutProps {
    children: ReactNode;
}

export default async function RootLayout({children}: RootLayoutProps): Promise<JSX.Element> {
    const dictionary = await getTranslations();

    return (
        <html lang={defaultLocale}>
            <body className="min-h-screen bg-background text-on-background antialiased">
                <TranslationsProvider dictionary={dictionary}>
                    <Header />
                    <main>{children}</main>
                    <Footer />
                </TranslationsProvider>
            </body>
        </html>
    );
}
