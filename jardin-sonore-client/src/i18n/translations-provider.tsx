'use client';

import {createContext, JSX, ReactNode, useContext} from "react";
import {Dictionary} from "@/i18n/types";

const TranslationsContext = createContext<Dictionary | null>(null);

interface TranslationsProviderProps {
    children: ReactNode;
    dictionary: Dictionary;
}

export function TranslationsProvider({children, dictionary}: TranslationsProviderProps): JSX.Element {
    return (
        <TranslationsContext.Provider value={dictionary}>
            {children}
        </TranslationsContext.Provider>
    );
}

export function useTranslations(): Dictionary {
    const dictionary = useContext(TranslationsContext);

    if (!dictionary) {
        throw new Error("useTranslations must be used inside TranslationsProvider.");
    }

    return dictionary;
}
