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
            {label: "Nos interventions", href: "#prestations"},
            {label: "Photos", href: "#exploration"},
            {label: "Contact", href: "#contact"},
        ],
    },
    hero: {
        imageAlt: "Enfant explorant des instruments d'éveil musical",
        tagline: "Éveil musical bienveillant pour la petite enfance",
        serviceArea: "Forez — Giers — Pilat — Lyonnais",
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
            "Notre approche Jardin Sonore repose sur une pédagogie où le jeu libre et la découverte sonore sont au cœur de l'apprentissage. Nous créons un environnement sécurisant permettant aux tout-petits d'explorer leur sensibilité musicale.",
            "Loin de la performance, nous privilégions la dévouverte, l'écoute, le rythme corporel et l'interaction sociale à travers des instruments et d'objets sonores adaptés et des rituels sonores apaisants.",
        ],
        benefits: [
            "Développement de la motricité",
            "Découverte et exploration sonore",
            "Socialisation et partage",
        ],
        badges: [
            {label: "Exploration", tone: "secondary"},
            {label: "Créativité", tone: "tertiary"},
        ],
        imageAlt: "Atelier musical avec instruments colorés pour enfants",
    },
    exploration: {
        eyebrow: "Découverte sensorielle",
        title: "Moments d'exploration",
        description: "En atelier, chaque son est une nouvelle aventure.",
        photos: [
            {
                title: "Immersion sonore",
                description: "Une approche intuitive pour permettre aux enfants de s'approprier le rythme naturellement.",
                imageSrc: "/Hero2.jpg",
                imageAlt: "Atelier d'éveil musical avec instruments disposés pour les enfants",
            },
            {
                title: "Instruments à manipuler",
                imageSrc: "/Hero.jpg",
                imageAlt: "Instruments d'éveil sonore prêts à être explorés",
            },
            {
                title: "Gestes et écoute",
                imageSrc: "/Hero2.jpg",
                imageAlt: "Moment d'exploration sonore pendant un atelier petite enfance",
            },
            {
                title: "Rythmes partagés",
                imageSrc: "/Hero.jpg",
                imageAlt: "Placeholder pour une future photo de rythme partagé en atelier",
            },
            {
                title: "Textures sonores",
                imageSrc: "/Hero2.jpg",
                imageAlt: "Placeholder pour une future photo de découverte des textures sonores",
            },
            {
                title: "Écoute collective",
                imageSrc: "/Hero.jpg",
                imageAlt: "Placeholder pour une future photo de moment d'écoute collective",
            },
        ],
    },
    services: {
        title: "Nos Interventions",
        description: "Des formats adaptés à tous les environnements d'accueil de la petite enfance.",
        discoverCta: "Découvrir",
        items: [
            {
                title: "Crèches & EAJE",
                description: "Ateliers réguliers adaptés pour les plus jeunes (dès 3 mois) et centrés sur la manipulation libre et l'éveil sensoriel.",
                tone: "primary",
                imageSrc: "/Hero.jpg",
                imageAlt: "Intervention d'éveil musical en crèche",
                badge: "Crèches",
            },
            {
                title: "Fêtes et festivals",
                description: "Projets d'intervention à la journée, autour d'un thème précis ou pour une manifestation particulière : fin d'année, festival petite enfance...",
                tone: "tertiary",
                imageSrc: "/Hero2.jpg",
                imageAlt: "Animation sonore pour un événement petite enfance",
                badge: "Événements",
            },
            {
                title: "Parents-Enfants",
                description: "Des moments privilégiés de partage en famille pour renforcer le lien à travers la musique",
                tone: "secondary",
                imageSrc: "/Hero.jpg",
                imageAlt: "Atelier musical partagé entre parent et enfant",
                badge: "Binôme",
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
        description: "Contactez-nous pour une présentation de nos prestations et un devis adapté à vos besoins.",
        quoteCta: "Demander un devis",
        callCta: "Nous appeler",
        phone: {
            loadingLabel: "Ouverture...",
        },
        form: {
            fullNameLabel: "Nom complet",
            fullNamePlaceholder: "Votre nom",
            emailLabel: "Email",
            emailPlaceholder: "votre@email.com",
            phoneLabel: "Téléphone",
            phonePlaceholder: "06 00 00 00 00",
            organizationLabel: "Structure",
            organizationPlaceholder: "Nom de votre établissement",
            cityLabel: "Ville",
            cityPlaceholder: "Votre ville",
            messageLabel: "Message",
            messagePlaceholder: "Décrivez votre projet...",
            submitLabel: "Envoyer la demande",
            submitSending: "Envoi en cours...",
            submitSuccess: "Votre demande a bien été envoyée.",
            submitError: "Impossible d'envoyer la demande pour le moment.",
            captchaError: "La vérification anti-robot a échoué. Merci de réessayer.",
        },
    },
    footer: {
        ariaLabel: "Navigation de pied de page",
        shareAriaLabel: "Partager",
        emailAriaLabel: "Envoyer un email",
        copyright: "© 2026 Jardin Sonore",
        links: [
            {label: "Contact", href: "#contact"},
        ],
    },
} as const;

export default fr;
