import {JSX} from "react";
import Button from "@/components/Button";

export default function CtaSection(): JSX.Element {
    return (
        <section className="px-6 pb-xl sm:px-margin" id="contact">
            <div className="relative mx-auto max-w-[1280px] overflow-hidden rounded-2xl bg-primary px-8 py-16 text-center text-on-primary ambient-shadow sm:px-16 md:py-24">
                <div className="absolute -left-24 -top-28 h-80 w-80 rounded-full bg-primary-fixed-dim/20 blur-3xl" />
                <div className="absolute -bottom-28 -right-24 h-80 w-80 rounded-full bg-secondary-container/15 blur-3xl" />
                <div className="relative z-10">
                    <h2 className="mx-auto max-w-3xl font-serif text-3xl font-semibold leading-tight sm:text-4xl">Prêt à faire entrer la musique dans votre structure ?</h2>
                    <p className="mx-auto mt-6 max-w-2xl text-lg leading-8 text-on-primary/90">Contactez-nous pour une présentation personnalisée de nos programmes et un devis adapté à vos besoins.</p>
                    <div className="mt-10 flex flex-col justify-center gap-4 sm:flex-row">
                        <Button href="mailto:contact@jardin-sonore.fr" variant="light">Demander un devis</Button>
                        <Button href="tel:+33000000000" variant="secondary" className="border-white/35 text-on-primary hover:bg-white/10">Nous appeler</Button>
                    </div>
                </div>
            </div>
        </section>
    );
}
