import {NextRequest, NextResponse} from "next/server";

export const dynamic = "force-dynamic";

interface RevealPhoneRequest {
    intent?: string;
}

const normalizePhone = (phone: string): string => phone.replace(/[^\d+]/g, "");

const formatPhoneLabel = (phone: string): string => {
    const normalized = normalizePhone(phone);

    if (normalized.startsWith("+33") && normalized.length === 12) {
        return `0${normalized.slice(3)}`.replace(/(\d{2})(?=\d)/g, "$1 ").trim();
    }

    if (/^0\d{9}$/.test(normalized)) {
        return normalized.replace(/(\d{2})(?=\d)/g, "$1 ").trim();
    }

    return phone;
};

export async function POST(request: NextRequest): Promise<NextResponse> {
    const body = await request.json().catch((): RevealPhoneRequest => ({})) as RevealPhoneRequest;

    if (body.intent !== "reveal-phone") {
        return NextResponse.json({error: "Invalid request"}, {status: 400});
    }

    const phone = process.env.CONTACT_PHONE;

    if (!phone) {
        return NextResponse.json({error: "Phone is not configured"}, {status: 404});
    }

    const normalized = normalizePhone(phone);

    return NextResponse.json(
        {
            href: `tel:${normalized}`,
            label: formatPhoneLabel(phone),
        },
        {
            headers: {
                "cache-control": "no-store",
            },
        },
    );
}
