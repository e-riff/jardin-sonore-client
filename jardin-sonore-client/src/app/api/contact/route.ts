import {NextRequest, NextResponse} from "next/server";
import nodemailer from "nodemailer";
import {verifyAltchaPayload} from "@/lib/altcha";

export const dynamic = "force-dynamic";
export const runtime = "nodejs";

interface ContactRequest {
    altcha?: string;
    city?: string;
    email?: string;
    message?: string;
    name?: string;
    organization?: string;
    phone?: string;
}

const trimValue = (value: unknown): string => typeof value === "string" ? value.trim() : "";

const isValidEmail = (value: string): boolean => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);

const buildText = (data: Required<Omit<ContactRequest, "altcha">>): string => [
    "Nouvelle demande de devis Jardin Sonore",
    "",
    `Nom: ${data.name}`,
    `Email: ${data.email}`,
    `Telephone: ${data.phone || "Non renseigne"}`,
    `Structure: ${data.organization || "Non renseignee"}`,
    `Ville: ${data.city || "Non renseignee"}`,
    "",
    "Message:",
    data.message,
].join("\n");

export async function POST(request: NextRequest): Promise<NextResponse> {
    const body = await request.json().catch((): ContactRequest => ({})) as ContactRequest;
    const name = trimValue(body.name);
    const email = trimValue(body.email);
    const phone = trimValue(body.phone);
    const organization = trimValue(body.organization);
    const city = trimValue(body.city);
    const message = trimValue(body.message);

    if (!name || !isValidEmail(email)) {
        return NextResponse.json({error: "Invalid contact data"}, {status: 400});
    }

    const altchaVerified = await verifyAltchaPayload(body.altcha ?? null);

    if (!altchaVerified) {
        return NextResponse.json({error: "Invalid captcha"}, {status: 403});
    }

    const smtpPort = Number.parseInt(process.env.SMTP_PORT ?? "587", 10);
    const transporter = nodemailer.createTransport({
        host: process.env.SMTP_HOST,
        port: Number.isNaN(smtpPort) ? 587 : smtpPort,
        secure: process.env.SMTP_SECURE === "true",
        auth: {
            user: process.env.SMTP_USER,
            pass: process.env.SMTP_PASSWORD,
        },
    });

    const to = process.env.CONTACT_EMAIL
    const from = process.env.SMTP_FROM;
    const contact = {city, email, message, name, organization, phone};

    try {
        await transporter.sendMail({
            from,
            replyTo: {
                name,
                address: email,
            },
            subject: `CONTACT JARDIN SONORE - Demande de devis - ${name}`,
            text: buildText(contact),
            to,
        });

        return NextResponse.json({ok: true});
    } catch {
        return NextResponse.json({error: "Unable to send email"}, {status: 502});
    }
}
