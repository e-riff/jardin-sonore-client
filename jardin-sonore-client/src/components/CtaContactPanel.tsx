'use client';

import {FormEvent, JSX, useEffect, useRef, useState} from "react";
import {PhoneIcon} from "@heroicons/react/24/outline";
import AltchaWidget from "@/components/AltchaWidget";
import {Dictionary} from "@/i18n/types";

interface CtaContactPanelProps {
    content: Dictionary["cta"];
}

interface PhonePayload {
    href: string;
    label: string;
}

type SubmitState = "idle" | "sending" | "success" | "error" | "captcha";

export default function CtaContactPanel({content}: CtaContactPanelProps): JSX.Element {
    const [formOpen, setFormOpen] = useState<boolean>(false);
    const [phoneLoading, setPhoneLoading] = useState<boolean>(false);
    const [submitState, setSubmitState] = useState<SubmitState>("idle");
    const formRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!formOpen) {
            return;
        }

        window.setTimeout(() => {
            formRef.current?.scrollIntoView({behavior: "smooth", block: "center"});
        }, 80);
    }, [formOpen]);

    const onQuoteClick = (): void => {
        setFormOpen((isOpen) => !isOpen);
    };

    const onPhoneClick = async (): Promise<void> => {
        if (phoneLoading) {
            return;
        }

        setPhoneLoading(true);

        try {
            const response = await fetch("/api/contact-phone", {
                method: "POST",
                headers: {"content-type": "application/json"},
                body: JSON.stringify({intent: "reveal-phone"}),
            });

            if (!response.ok) {
                return;
            }

            const phone = await response.json() as PhonePayload;
            window.location.href = phone.href;
        } finally {
            setPhoneLoading(false);
        }
    };

    const onSubmit = async (event: FormEvent<HTMLFormElement>): Promise<void> => {
        event.preventDefault();

        if (submitState === "sending") {
            return;
        }

        setSubmitState("sending");

        const form = event.currentTarget;
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const response = await fetch("/api/contact", {
                method: "POST",
                headers: {"content-type": "application/json"},
                body: JSON.stringify(payload),
            });

            if (response.ok) {
                form.reset();
                setSubmitState("success");
                return;
            }

            setSubmitState(response.status === 403 ? "captcha" : "error");
        } catch {
            setSubmitState("error");
        }
    };

    const submitNotice = {
        captcha: content.form.captchaError,
        error: content.form.submitError,
        idle: "",
        sending: content.form.submitSending,
        success: content.form.submitSuccess,
    }[submitState];

    return (
        <>
            <div className="mt-10 flex flex-col justify-center gap-4 sm:flex-row">
                <button
                    className="cursor-pointer inline-flex items-center justify-center rounded-full border border-primary bg-primary px-10 py-3 font-sans text-sm font-bold tracking-wider text-on-primary soft-shadow transition duration-200 hover:-translate-y-0.5 hover:bg-primary-container sm:px-14 sm:py-4 sm:text-base"
                    type="button"
                    aria-expanded={formOpen}
                    aria-controls="devis-form-container"
                    onClick={onQuoteClick}
                >
                    {content.quoteCta}
                </button>
                <button
                    className="cursor-pointer inline-flex items-center justify-center gap-2 rounded-full border-2 border-primary/20 px-10 py-3 font-sans text-sm font-bold tracking-wider text-primary transition duration-200 hover:bg-primary/5 sm:px-14 sm:py-4 sm:text-base"
                    type="button"
                    onClick={onPhoneClick}
                    disabled={phoneLoading}
                >
                    <PhoneIcon className="h-4 w-4" aria-hidden="true" />
                    {phoneLoading ? content.phone.loadingLabel : content.callCta}
                </button>
            </div>

            <div
                className={`grid transition-[grid-template-rows,opacity,margin] duration-500 ease-out ${formOpen ? "mt-12 grid-rows-[1fr] opacity-100" : "mt-0 grid-rows-[0fr] opacity-0"}`}
                id="devis-form-container"
                ref={formRef}
            >
                <div className="overflow-hidden">
                    <form
                        className="mx-auto max-w-2xl rounded-xl border border-outline-variant/30 bg-surface-container-lowest p-6 text-left soft-shadow md:p-10"
                        onSubmit={onSubmit}
                    >
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <label className="grid gap-2 font-sans text-sm font-semibold text-on-surface-variant">
                                <span>{content.form.fullNameLabel}</span>
                                <input
                                    className="w-full rounded-lg border border-outline-variant/50 bg-surface px-4 py-3 font-sans text-base text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    name="name"
                                    placeholder={content.form.fullNamePlaceholder}
                                    required
                                    type="text"
                                />
                            </label>

                            <label className="grid gap-2 font-sans text-sm font-semibold text-on-surface-variant">
                                <span>{content.form.emailLabel}</span>
                                <input
                                    className="w-full rounded-lg border border-outline-variant/50 bg-surface px-4 py-3 font-sans text-base text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    name="email"
                                    placeholder={content.form.emailPlaceholder}
                                    required
                                    type="email"
                                />
                            </label>
                        </div>

                        <div className="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                            <label className="grid gap-2 font-sans text-sm font-semibold text-on-surface-variant">
                                <span>{content.form.phoneLabel}</span>
                                <input
                                    className="w-full rounded-lg border border-outline-variant/50 bg-surface px-4 py-3 font-sans text-base text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    name="phone"
                                    placeholder={content.form.phonePlaceholder}
                                    type="tel"
                                />
                            </label>

                            <label className="grid gap-2 font-sans text-sm font-semibold text-on-surface-variant">
                                <span>{content.form.organizationLabel}</span>
                                <input
                                    className="w-full rounded-lg border border-outline-variant/50 bg-surface px-4 py-3 font-sans text-base text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                    name="organization"
                                    placeholder={content.form.organizationPlaceholder}
                                    type="text"
                                />
                            </label>
                        </div>

                        <label className="mt-6 grid gap-2 font-sans text-sm font-semibold text-on-surface-variant">
                            <span>{content.form.cityLabel}</span>
                            <input
                                className="w-full rounded-lg border border-outline-variant/50 bg-surface px-4 py-3 font-sans text-base text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                name="city"
                                placeholder={content.form.cityPlaceholder}
                                type="text"
                            />
                        </label>

                        <label className="mt-6 grid gap-2 font-sans text-sm font-semibold text-on-surface-variant">
                            <span>{content.form.messageLabel}</span>
                            <textarea
                                className="min-h-32 w-full resize-y rounded-lg border border-outline-variant/50 bg-surface px-4 py-3 font-sans text-base text-on-surface outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/20"
                                name="message"
                                placeholder={content.form.messagePlaceholder}
                                required
                                rows={4}
                            />
                        </label>

                        <AltchaWidget />

                        <button
                            className="mt-6 w-full rounded-full bg-primary py-4 font-sans text-base font-bold tracking-wider text-on-primary soft-shadow transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-70"
                            type="submit"
                            disabled={submitState === "sending"}
                        >
                            {submitState === "sending" ? content.form.submitSending : content.form.submitLabel}
                        </button>

                        {submitNotice ? (
                            <p className="mt-4 text-center font-sans text-sm leading-6 text-on-surface-variant" role="status">
                                {submitNotice}
                            </p>
                        ) : null}
                    </form>
                </div>
            </div>
        </>
    );
}
