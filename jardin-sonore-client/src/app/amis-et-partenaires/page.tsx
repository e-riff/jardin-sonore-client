import {LinkIcon} from "@heroicons/react/24/outline";
import Image from "next/image";
import Link from "next/link";
import type {Metadata} from "next";
import type {JSX} from "react";
import Breadcrumbs from "@/components/Breadcrumbs";
import EditorialCopy from "@/components/EditorialCopy";
import SectionHeading from "@/components/SectionHeading";
import fr from "@/i18n/dictionaries/fr";

const content = fr.partnersPage;
const partnerLinkClassName = "inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-primary/35 bg-surface/70 px-6 py-3 text-center font-sans text-sm font-bold text-primary-container transition-colors duration-200 hover:border-primary hover:bg-primary-fixed/35 motion-reduce:transition-none";

export const metadata: Metadata = {
    title: content.metadata.title,
    description: content.metadata.description,
    alternates: {canonical: "/amis-et-partenaires"},
    robots: {index: false, follow: true},
    openGraph: {
        type: "website",
        locale: "fr_FR",
        url: "/amis-et-partenaires",
        siteName: fr.brand.name,
        title: content.metadata.title,
        description: content.metadata.description,
        images: [{url: "/images/social/jardin-sonore-og.jpg", width: 1200, height: 630, alt: fr.metadata.socialImageAlt}],
    },
    twitter: {
        card: "summary_large_image",
        title: content.metadata.title,
        description: content.metadata.description,
        images: ["/images/social/jardin-sonore-og.jpg"],
    },
};

export default function PartnersPage(): JSX.Element {
    return <>
        <section className="bg-surface-container-low px-6 pb-16 pt-24 sm:px-margin lg:pb-24 lg:pt-28">
            <div className="mx-auto max-w-7xl">
                <Breadcrumbs pathname="/amis-et-partenaires" />
                <div className="mt-6 grid items-center gap-x-10 gap-y-8 lg:grid-cols-2 lg:gap-x-16">
                    <div>
                        <p className="font-sans text-xs font-bold uppercase tracking-[0.18em] text-secondary">{content.eyebrow}</p>
                        <h1 className="mt-5 font-serif text-4xl font-semibold leading-tight text-primary sm:text-5xl lg:text-6xl">{content.title}</h1>
                        <EditorialCopy className="mt-7 max-w-2xl text-lg leading-8 text-on-surface-variant [&_strong]:text-primary-container" text={content.introduction} />

                    </div>
                    <div className="relative aspect-[16/10] overflow-hidden rounded-2xl shadow-sm lg:col-start-2 lg:row-span-2 lg:row-start-1">
                        <Image alt={content.heroImageAlt} className="object-cover" fill priority sizes="(min-width: 1024px) 48vw, 100vw" src="/images/partenaires/rencontres-musicales.webp" />
                    </div>
                    <nav aria-label={content.heroNavigationLabel} className="flex flex-wrap gap-x-6 gap-y-2 border-t border-outline-variant/60 pt-5 lg:col-start-1 font-sans text-sm font-semibold">
                            {[{href: "#partners-people", label: content.heroPeopleLink}, {href: "#partners-companies", label: content.heroCompaniesLink}, {href: "#partners-resources", label: content.heroResourcesLink}].map((item) => <Link className="group inline-flex min-h-11 items-center gap-2 text-secondary transition-colors duration-200 hover:text-primary motion-reduce:transition-none" href={item.href} key={item.href}>{item.label} <span aria-hidden="true" className="transition-transform duration-200 group-hover:translate-y-1 motion-reduce:transition-none motion-reduce:group-hover:translate-y-0">↓</span></Link>)}
                    </nav>
                </div>
            </div>
        </section>

        <section aria-labelledby="partners-people" className="px-6 py-14 sm:px-margin lg:py-16">
            <div className="mx-auto max-w-7xl">
                <SectionHeading eyebrow={content.peopleEyebrow} id="partners-people" title={content.peopleTitle} />
                <div className="mt-36 grid gap-x-6 gap-y-36 md:grid-cols-2">
                    {content.people.map((person) => <article className="partner-reveal group rounded-2xl bg-surface-container-low px-7 pb-9 transition-shadow duration-300 hover:shadow-lg motion-reduce:transition-none sm:px-9" key={person.name}>
                        <div className="relative mx-auto -mt-28 aspect-square w-56 overflow-hidden rounded-full border-4 border-background shadow-md sm:-mt-32 sm:w-64 lg:-mt-36 lg:w-72">
                            <Image alt={person.imageAlt} className="object-cover transition-transform duration-700 group-hover:scale-105 motion-reduce:transition-none motion-reduce:group-hover:scale-100" fill sizes="(min-width: 1024px) 288px, (min-width: 640px) 256px, 224px" src={person.image} />
                        </div>
                        <h3 className="mt-6 text-center font-serif text-3xl font-semibold text-primary">{person.name}</h3>
                        <EditorialCopy className="mt-4 leading-7 text-on-surface-variant [&_strong]:text-primary-container" text={person.description} />
                        {person.linkHref ? <div className="mt-6"><Link aria-label={`${person.linkLabel} — ${person.name}`} className={partnerLinkClassName} href={person.linkHref}><LinkIcon aria-hidden="true" className="h-5 w-5 shrink-0" />{person.linkLabel}</Link></div> : null}
                    </article>)}
                </div>
            </div>
        </section>

        <section aria-labelledby="partners-companies" className="bg-surface-container-low px-6 py-14 sm:px-margin lg:py-16">
            <div className="mx-auto max-w-7xl">
                <SectionHeading eyebrow={content.companiesEyebrow} id="partners-companies" title={content.companiesTitle} />
                <div className="mt-10 divide-y divide-outline-variant/50">
                    {content.companies.map((company, index) => <article className="partner-reveal group grid items-center gap-7 py-8 first:pt-0 last:pb-0 lg:grid-cols-2 lg:gap-14" id={company.id} key={company.name}>
                        <div className={`relative flex aspect-[16/10] items-center justify-center overflow-hidden rounded-2xl bg-secondary/10 ${index % 2 === 1 ? "lg:order-2" : ""}`}>
                            <Image alt={company.imageAlt} className={`${company.imageFit === "logo" ? "bg-white object-contain" : "object-cover"} transition-transform duration-700 group-hover:scale-[1.03] group-focus-within:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-focus-within:scale-100`} fill sizes="(min-width: 1024px) 48vw, 100vw" src={company.image} />
                        </div>
                        <div>
                            <div className="flex items-center gap-4">
                                {"logo" in company ? <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-outline-variant/30 bg-white p-2 shadow-sm"><Image alt="" className="object-contain p-2" fill sizes="80px" src={company.logo} /></div> : null}
                                <h3 className="font-serif text-2xl font-semibold text-primary-container sm:text-3xl">{company.name}</h3>
                            </div>
                            <EditorialCopy className="mt-4 max-w-2xl text-lg leading-8 text-on-surface-variant [&_strong]:text-primary-container" links={"descriptionLinks" in company ? company.descriptionLinks : undefined} text={company.description} />
                            <div className="mt-6 flex flex-wrap gap-3">
                                <a aria-label={`${company.linkLabel} — ${company.name}`} className={partnerLinkClassName} href={company.href}><LinkIcon aria-hidden="true" className="h-5 w-5 shrink-0" />{company.linkLabel}</a>
                                {"additionalLinkHref" in company ? <a aria-label={`${company.additionalLinkLabel} — ${company.name}`} className={partnerLinkClassName} href={company.additionalLinkHref}>
                                    {company.additionalLinkHref.includes("instagram.com") ? <Image alt="" height={20} src="/instagram.svg" width={20} /> : <Image alt="" height={20} src="/facebook.svg" width={20} />}
                                    {company.additionalLinkLabel}
                                </a> : null}
                            </div>
                        </div>
                    </article>)}
                </div>
            </div>
        </section>

        <section aria-labelledby="partners-resources" className="bg-surface-container-low px-6 py-14 sm:px-margin lg:py-16">
            <div className="mx-auto max-w-7xl">
                <SectionHeading id="partners-resources" title={content.resourcesTitle} />
                <article className="partner-reveal group mt-8 grid items-center gap-7 lg:grid-cols-2 lg:gap-14">
                    <div className="relative aspect-[16/10] overflow-hidden rounded-2xl">
                        <Image alt={content.resourceImageAlt} className="object-cover transition-transform duration-700 group-hover:scale-[1.03] group-focus-within:scale-[1.03] motion-reduce:transition-none motion-reduce:group-hover:scale-100 motion-reduce:group-focus-within:scale-100" fill sizes="(min-width: 1024px) 48vw, 100vw" src="/images/partenaires/cfmi.webp" />
                    </div>
                    <div>
                    <div className="flex items-center gap-4">
                        <div className="relative h-20 w-20 shrink-0 overflow-hidden rounded-2xl border border-outline-variant/30 bg-white shadow-sm"><Image alt="" className="object-contain p-2" fill sizes="80px" src="/images/partenaires/logo-cfmi.webp" /></div>
                        <h3 className="font-serif text-2xl font-semibold text-primary-container sm:text-3xl">{content.resourceName}</h3>
                    </div>
                    <EditorialCopy className="mt-4 max-w-3xl text-lg leading-8 text-on-surface-variant [&_strong]:text-primary-container" text={content.resourceDescription} />
                    <div className="mt-6"><a aria-label={`${content.resourceLinkLabel} — ${content.resourceName}`} className={partnerLinkClassName} href={content.resourceHref}><LinkIcon aria-hidden="true" className="h-5 w-5 shrink-0" />{content.resourceLinkLabel}</a></div>
                    </div>
                </article>
            </div>
        </section>
    </>;
}
