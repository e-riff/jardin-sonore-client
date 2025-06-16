// src/components/Hero.tsx
'use client'

import Image from 'next/image'
import React, {JSX} from 'react'
import Button from "@/components/Button";

interface HeroProps {
    title: string
    subtitle: string
    ctaText: string
    backgroundImage: string
}

export default function Hero({
                                 title,
                                 subtitle,
                                 ctaText,
                                 backgroundImage,
                             }: HeroProps): JSX.Element {
    return (
        <section
            className="relative w-full h-screen sm:h-[100vh] flex flex-col justify-center items-center text-center overflow-hidden">
            <Image
                src={`/${backgroundImage}`}
                alt={title}
                fill
                className="object-cover opacity-70"
                priority
            />

            <div className="relative z-10 px-6 py-4 sm:px-8 sm:py-6 bg-white/30 backdrop-blur-md rounded-md">
                <h1 className="text-3xl sm:text-5xl font-bold text-[#243237]">
                    {title}
                </h1>
                <p className="mt-2 text-base sm:text-xl text-[#243237]/80">
                    {subtitle}
                </p>
                <Button href="#about">{ctaText}</Button>
            </div>
        </section>
    )
}
