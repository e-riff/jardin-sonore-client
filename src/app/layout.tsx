import React from "react";
import type { Metadata } from 'next';
import "./globals.css";
import {NextIntlClientProvider} from 'next-intl';
import {getLocale} from 'next-intl/server';
import Header from "@/components/Header";



export const metadata: Metadata = {
    title: {
        default:"Jardin Sonore",
        template: " | Jardin sonore"
    },
    icons: {
        icon: [
            { url: '/favicon/favicon.ico', sizes: 'any', type: 'image/x-icon' },
            { url: '/favicon/favicon-96x96.png', sizes: '96x96', type: 'image/png' },
            { url: '/favicon/favicon.svg', sizes: 'any', type: 'image/svg+xml' }
        ],
        shortcut: '/favicon/favicon-96x96.png',
        apple: '/favicon/apple-touch-icon.png',
        other: [
            { rel: 'manifest', url: '/favicon/site.webmanifest' },
            { rel: 'mask-icon', url: '/favicon/favicon.svg', color: '#5bbad5' }
        ]
    },
    description: "Bienvenue sur le site du Jardin Sonore..."
};

export default async function RootLayout({ children }: { children: React.ReactNode }) {
    const locale = await getLocale();


    return (
        <html lang={locale}>
        <body className="bg-bgLight text-primary antialiased">
        <NextIntlClientProvider>
            <header className="fixed top-0 w-full z-20 h-16">
                <Header/>
            </header>
            <main className="pt-16 min-h-screen">
                {children}
            </main>
            <footer className="p-4 text-center text-sm text-[#6C7A89]">
                © {new Date().getFullYear()} Jardin Sonore
            </footer>
        </NextIntlClientProvider>
        </body>
        </html>
    )
}
