import {JSX} from "react";
import ExplorationGallery from "@/components/sections/ExplorationGallery";
import {getTranslations} from "@/i18n/server";

export default async function ExplorationSection(): Promise<JSX.Element> {
    const dictionary = await getTranslations();
    const content = dictionary.exploration;

    return (
        <section className="bg-surface-container-low px-6 py-xl sm:px-margin lg:py-28" id="exploration-sonore">
            <ExplorationGallery content={content} />
        </section>
    );
}
