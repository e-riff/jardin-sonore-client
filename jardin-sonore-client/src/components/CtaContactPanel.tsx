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
    const [successVisible, setSuccessVisible] = useState<boolean>(false);
    const formRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!formOpen) {
            return;
        }

        window.setTimeout(() => {
            formRef.current?.scrollIntoView({behavior: "smooth", block: "center"});
        }, 80);
    }, [formOpen]);

    useEffect(() => {
        if (!["captcha", "error"].includes(submitState)) {
            return;
        }

        const timeoutId = window.setTimeout(() => {
            setSubmitState("idle");
        }, 6000);

        return () => window.clearTimeout(timeoutId);
    }, [submitState]);

    useEffect(() => {
        if (submitState !== "success") {
            return;
        }

        const hideTimeoutId = window.setTimeout(() => {
            setSuccessVisible(false);
        }, 5000);
        const resetTimeoutId = window.setTimeout(() => {
            setSubmitState("idle");
        }, 5600);

        return () => {
            window.clearTimeout(hideTimeoutId);
            window.clearTimeout(resetTimeoutId);
        };
    }, [submitState]);

    const onQuoteClick = (): void => {
        if (submitState !== "sending") {
            setSubmitState("idle");
        }

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
                setSuccessVisible(true);
                setSubmitState("success");
                setFormOpen(false);
                return;
            }

            setSubmitState(response.status === 403 ? "captcha" : "error");
        } catch {
            setSubmitState("error");
        }
    };

    const isSending = submitState === "sending";
    const submitErrorNotice = submitState === "captcha"
        ? content.form.captchaError
        : submitState === "error"
            ? content.form.submitError
            : "";
    const submitSuccessNotice = submitState === "success" ? content.form.submitSuccess : "";

    return (
        <>
            <div className="mt-10 flex flex-col justify-center gap-4 sm:flex-row">
                <button
                    className="inline-flex cursor-pointer items-center justify-center rounded-full border border-surface bg-surface px-10 py-3 font-sans text-sm font-bold tracking-wider text-primary soft-shadow transition duration-200 hover:-translate-y-0.5 hover:bg-primary-fixed sm:px-14 sm:py-4 sm:text-base"
                    type="button"
                    aria-expanded={formOpen}
                    aria-controls="devis-form-container"
                    onClick={onQuoteClick}
                >
                    {content.quoteCta}
                </button>
                <button
                    className="inline-flex cursor-pointer items-center justify-center gap-2 rounded-full border-2 border-on-primary/35 px-10 py-3 font-sans text-sm font-bold tracking-wider text-on-primary transition duration-200 hover:bg-on-primary/10 disabled:cursor-not-allowed disabled:opacity-70 sm:px-14 sm:py-4 sm:text-base"
                    type="button"
                    onClick={onPhoneClick}
                    disabled={phoneLoading}
                >
                    <PhoneIcon className="h-4 w-4" aria-hidden="true" />
                    {phoneLoading ? content.phone.loadingLabel : content.callCta}
                </button>
            </div>

            {submitSuccessNotice ? (
                <p
                    className={`mx-auto mt-6 w-full max-w-2xl rounded-lg border border-on-primary/25 bg-on-primary/12 px-5 py-4 text-center font-sans text-base font-semibold leading-7 text-on-primary shadow-sm backdrop-blur-sm transition-all duration-500 ease-out sm:w-fit sm:min-w-[32rem] ${successVisible ? "translate-y-0 opacity-100" : "-translate-y-1 opacity-0"}`}
                    role="status"
                >
                    {submitSuccessNotice}
                </p>
            ) : null}

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

                        <div className="mt-6 flex flex-col items-center gap-3">
                            <button
                                className="inline-flex w-full items-center justify-center gap-3 rounded-full bg-primary px-8 py-4 font-sans text-base font-bold tracking-wider text-on-primary soft-shadow transition hover:bg-primary-container disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto sm:min-w-72"
                                type="submit"
                                disabled={isSending}
                            >
                                {isSending ? (
                                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-on-primary/35 border-t-on-primary" aria-hidden="true" />
                                ) : null}
                                {isSending ? content.form.submitSending : content.form.submitLabel}
                            </button>

                            {submitErrorNotice ? (
                                <p
                                    className="w-full rounded-lg border border-primary/20 bg-primary-fixed/45 px-5 py-4 text-center font-sans text-base font-semibold leading-7 text-primary shadow-sm"
                                    role="alert"
                                >
                                    {submitErrorNotice}
                                </p>
                            ) : null}
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
