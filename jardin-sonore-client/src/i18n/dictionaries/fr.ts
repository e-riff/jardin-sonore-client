const fr = {
    brand: {
        name: "Jardin Sonore",
    },
    metadata: {
        titleTemplate: "%s | Jardin Sonore",
        description: "Éveil musical bienveillant pour la petite enfance.",
    },
    header: {
        ariaLabel: "Navigation principale",
        homeAriaLabel: "Accueil Jardin Sonore",
        menuAriaLabel: "Ouvrir le menu",
        reserveCta: "Réserver",
        navigation: [
            {label: "À propos", href: "#about"},
            {label: "Services", href: "#services"},
            {label: "Contact", href: "#contact"},
        ],
    },
    hero: {
        imageAlt: "Enfant explorant des instruments d'éveil musical",
        tagline: "Éveil musical bienveillant pour la petite enfance",
        primaryCta: "En savoir plus",
        secondaryCta: "Nous contacter",
    },
    stats: {
        ariaLabel: "Chiffres clés",
        items: [
            {value: "10 ans", label: "d'expertise reconnue", tone: "primary"},
            {value: "Bac +5", label: "musique & pédagogie", tone: "tertiary"},
            {value: "30+", label: "structures partenaires", tone: "secondary"},
        ],
    },
    about: {
        eyebrow: "Approche",
        title: "Un éveil en douceur pour chaque enfant",
        paragraphs: [
            "Notre approche Jardin Sonore repose sur une pédagogie bienveillante où le jeu et la découverte sonore sont au cœur de l'apprentissage. Nous créons un environnement sécurisant permettant aux tout-petits d'explorer leur sensibilité musicale.",
            "Loin de la performance, nous privilégions l'écoute, le rythme corporel et l'interaction sociale à travers des instruments adaptés et des rituels sonores apaisants.",
        ],
        benefits: [
            "Développement de la motricité fine",
            "Stimulation du langage",
            "Socialisation et partage",
        ],
        badges: [
            {label: "Exploration", tone: "secondary"},
            {label: "Créativité", tone: "tertiary"},
        ],
        imageAlt: "Atelier musical avec instruments colorés pour enfants",
    },
    services: {
        title: "Nos Interventions",
        description: "Des formats adaptés à tous les environnements d'accueil de la petite enfance.",
        discoverCta: "Découvrir",
        items: [
            {
                title: "Crèches & EAMU",
                description: "Ateliers hebdomadaires adaptés aux tout-petits, centrés sur la manipulation et l'éveil sensoriel.",
                tone: "primary",
            },
            {
                title: "Écoles",
                description: "Projets pédagogiques sur-mesure pour les classes maternelles, exploration des instruments du monde.",
                tone: "tertiary",
            },
            {
                title: "Parents-Enfants",
                description: "Des moments privilégiés de partage en famille pour renforcer le lien à travers la vibration sonore.",
                tone: "secondary",
            },
        ],
    },
    testimonials: {
        title: "Ils nous font confiance",
        description: "Ce que disent les directrices et directeurs d'établissements partenaires de nos interventions sonores.",
        quoteMark: "“",
        items: [
            {
                quote: "Une approche d'une rare finesse qui a transformé le climat sonore de notre crèche. Les enfants attendent ce moment avec impatience chaque semaine.",
                author: "Marie-Claire R.",
                role: "Directrice Crèche Arc-en-Ciel",
                initials: "MC",
                tone: "primary",
            },
            {
                quote: "L'intervenant fait preuve d'une pédagogie exemplaire. Les ateliers sont riches, variés et parfaitement adaptés au rythme des plus petits.",
                author: "Jean-Pierre D.",
                role: "Coordinateur Petite Enfance",
                initials: "JP",
                tone: "secondary",
            },
        ],
    },
    cta: {
        title: "Prêt à faire entrer la musique dans votre structure ?",
        description: "Contactez-nous pour une présentation personnalisée de nos programmes et un devis adapté à vos besoins.",
        quoteCta: "Demander un devis",
        callCta: "Nous appeler",
    },
    footer: {
        ariaLabel: "Navigation de pied de page",
        shareAriaLabel: "Partager",
        emailAriaLabel: "Envoyer un email",
        copyright: "© 2026 Jardin Sonore. Éveil musical bienveillant pour la petite enfance.",
        links: [
            {label: "Mentions Légales", href: "#"},
            {label: "Confidentialité", href: "#"},
            {label: "Contact", href: "#contact"},
        ],
    },
} as const;

export default fr;
