'use client'

import React, {useState, useEffect, JSX} from 'react'


export default function Header(): JSX.Element {
    const [scrolled, setScrolled] = useState(false)

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 50)
        window.addEventListener('scroll', onScroll)
        return () => window.removeEventListener('scroll', onScroll)
    }, [])

    return (
        <header
            className={`
        fixed top-0 left-0 w-full z-20 transition-all
        ${scrolled ? 'backdrop-blur-md bg-white/40 py-2 shadow-md' : 'bg-transparent py-4'}
      `}
        >
            <div className="container mx-auto flex items-center justify-between px-4">
                <div className="text-2xl font-bold text-primary">Jardin Sonore</div>
                <nav className="space-x-4 hidden md:flex text-secondary">
                    <a href="#about" className="hover:text-primary transition">À propos</a>
                    <a href="#statsSection" className="hover:text-primary transition">Stats</a>
                    <a href="#contact" className="hover:text-primary transition">Contact</a>
                </nav>
                <button className="md:hidden p-2">
                    ☰
                </button>
            </div>
        </header>
    )
}
