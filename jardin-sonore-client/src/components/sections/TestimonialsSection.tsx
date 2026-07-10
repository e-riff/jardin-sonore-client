import {JSX} from "react";
import TestimonialsCarousel from "@/components/sections/TestimonialsCarousel";
import {getTranslations} from "@/i18n/server";

export default async function TestimonialsSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.testimonials;

    return <TestimonialsCarousel items={content.items} quoteMark={content.quoteMark} />;
}
