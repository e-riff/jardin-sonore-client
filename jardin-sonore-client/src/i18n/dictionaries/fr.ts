const fr = {
    brand: {
        name: "Jardin Sonore",
    },
    metadata: {
        titleDefault: "Éveil musical petite enfance à Saint-Étienne, Lyon et dans le Pilat",
        titleTemplate: "%s | Jardin Sonore",
        description: "Jardin Sonore propose des ateliers d'éveil musical bienveillants pour crèches, EAJE, festivals petite enfance et ateliers parents-enfants à Saint-Étienne, Lyon, dans le Pilat, le Forez, le Giers, le Grand Lyon, Roanne, la Haute-Loire, Annonay, Condrieu et Vienne.",
        socialTitle: "Jardin Sonore - Éveil musical petite enfance",
        socialDescription: "Ateliers d'éveil musical autour des comptines, instruments, objets sonores et manipulations libres pour les tout-petits, en crèche, EAJE, festival ou atelier parents-enfants.",
    },
    header: {
        ariaLabel: "Navigation principale",
        homeAriaLabel: "Accueil Jardin Sonore",
        menuAriaLabel: "Ouvrir le menu",
        reserveCta: "Réserver",
        navigation: [
            {label: "Notre approche", href: "#a-propos"},
            {label: "Nos interventions", href: "#prestations"},
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
            {value: "10 ans", label: "d'expérience en petite enfance", tone: "primary"},
            {value: "Bac +5", label: "en musique & pédagogie", tone: "tertiary"},
            {value: "+ de 30", label: "structures qui nous font confiance", tone: "secondary"},
        ],
    },
    about: {
        eyebrow: "Notre approche",
        title: "Un éveil en douceur pour chaque enfant",
        paragraphs: [
            "Notre approche de l'intervention musicale en petite enfance repose sur une pédagogie où la manipulation libre et la découverte sonore sont au cœur de l'apprentissage. Nous créons un environnement sécurisant permettant aux tout-petits d'explorer leur sensibilité musicale.",
            "Loin de la performance, nous privilégions la découverte, l'écoute, le rythme corporel et l'interaction sociale à travers des instruments, des comptines, des objets sonores adaptés et des rituels sonores apaisants.",
        ],
        benefits: [
            "Socialisation et partage",
            "Développement de la motricité",
            "Découverte et exploration sonore",
        ],
        badges: [
            {label: "Exploration", tone: "secondary"},
            {label: "Plaisir", tone: "primary"},
            {label: "Créativité", tone: "tertiary"},
        ],
        imageAlt: "Tambour collectif, baguettes et semoule",
    },
    exploration: {
        eyebrow: "Découverte sensorielle",
        title: "Moments d'exploration",
        description: "En atelier, chaque son est une nouvelle aventure.",
        photoGroups: [
            {
                title: "En séance",
                subtitle: "Des ateliers pensés comme des temps d'exploration, d'écoute et de rencontre.",
                images: [
                    {
                        imageSrc: "/galerie/1-1 - animateur tabla enfants.png",
                        imageAlt: "Atelier d'éveil musical autour d'un tabla",
                    },
                    {
                        imageSrc: "/galerie/1-2 - Animateur gong.png",
                        imageAlt: "Découverte d'un gong pendant un atelier musical avec de jeunes enfants",
                    },
                    {
                        imageSrc: "/galerie/1-3 - animateur tubes hurlant.png",
                        imageAlt: "Exploration sonore avec des tubes auprès d'enfants en crèche",
                    },
                ],
            },
            {
                title: "Matières sonores",
                subtitle: "Bois, métal, peaux, tissus : des objets à écouter, toucher et explorer.",
                images: [
                    {
                        imageSrc: "/galerie/2-1 - Balles en crochet.jpg",
                        imageAlt: "Balles textiles colorées utilisées pendant les ateliers sonores",
                    },
                    {
                        imageSrc: "/galerie/2-2 - boules méditation.jpg",
                        imageAlt: "Billes métalliques dans un bol en bois pour l'exploration sonore",
                    },
                    {
                        imageSrc: "/galerie/2-3 - Sac et matériel.jpg",
                        imageAlt: "Instruments et objets sonores disposés au sol avant un atelier",
                    },
                ],
            },
            {
                title: "Espaces d'exploration",
                subtitle: "Des dispositifs simples et sensibles, adaptés aux lieux et aux tout-petits.",
                images: [
                    {
                        imageSrc: "/galerie/3-1 - instruments plan large.png",
                        imageAlt: "Installation sonore avec instruments répartis au sol",
                    },
                    {
                        imageSrc: "/galerie/3-2 - table.png",
                        imageAlt: "Table d'exploration sonore avec objets en métal et matériaux variés",
                    },
                    {
                        imageSrc: "/galerie/3-3 - rideaux.jpg",
                        imageAlt: "Installation extérieure colorée composée de bouchons suspendus",
                    },
                ],
            },
        ],
    },
    services: {
        title: "Nos interventions",
        description: "Des formats adaptés à tous les environnements d'accueil de la petite enfance.",
        discoverCta: "Découvrir",
        items: [
            {
                title: "Crèches & EAJE",
                description: "Ateliers réguliers ou ponctuels, adaptés aux plus jeunes dès 3 mois, autour de la manipulation libre, de l’écoute et de l’éveil sensoriel. Après chaque séance, un déroulé récapitulatif est fourni, accompagné d’idées simples pour poursuivre l’exploration en notre absence.",
                tone: "primary",
                imageSrc: "/intervention-creche.png",
                imageAlt: "Intervention d'éveil musical en crèche",
                badge: "Structures",
            },
            {
                title: "Fêtes de structures et festivals",
                description: "Interventions à la journée autour d'un thème précis ou pour une manifestation particulière : fête de fin d'année, festival petite enfance, temps fort associatif ou événement local.",
                tone: "tertiary",
                imageSrc: "/galerie/3-3 - rideaux.jpg",
                imageAlt: "Installation de rideaux sonores extérieurs",
                badge: "Événements",
            },
            {
                title: "Parents-enfants",
                description: "Des moments privilégiés de partage en famille pour renforcer le lien à travers la musique, les comptines, les instruments et l'exploration sonore libre.",
                tone: "secondary",
                imageSrc: "/parents-enfants.png",
                imageAlt: "Atelier musical partagé entre parent et enfant",
                badge: "ateliers",
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
        quoteCta: "Demander des renseignements",
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
