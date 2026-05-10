import type {Metadata} from "next";
import {JSX, ReactNode} from "react";
import Footer from "@/components/Footer";
import Header from "@/components/Header";
import "./globals.css";

export const metadata: Metadata = {
    title: {
        default: "Jardin Sonore",
        template: "%s | Jardin Sonore",
    },
    description: "Éveil musical bienveillant pour la petite enfance.",
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

export default function RootLayout({children}: RootLayoutProps): JSX.Element {
    return (
        <html lang="fr">
            <body className="min-h-screen bg-background text-on-background antialiased">
                <Header />
                <main>{children}</main>
                <Footer />
            </body>
        </html>
    );
}
