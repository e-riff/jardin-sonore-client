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
        reserveCta: "Demander des renseignements",
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
                        imageSrc: "/images/galerie/animateur-tabla-enfants.webp",
                        imageAlt: "Atelier d'éveil musical autour d'un tabla",
                    },
                    {
                        imageSrc: "/images/galerie/animateur-gong.webp",
                        imageAlt: "Découverte d'un gong pendant un atelier musical avec de jeunes enfants",
                    },
                    {
                        imageSrc: "/images/galerie/animateur-tubes-hurlants.webp",
                        imageAlt: "Exploration sonore avec des tubes auprès d'enfants en crèche",
                    },
                ],
            },
            {
                title: "Matières sonores",
                subtitle: "Bois, métal, peaux, tissus : des objets à écouter, toucher et explorer.",
                images: [
                    {
                        imageSrc: "/images/galerie/balles-crochet.webp",
                        imageAlt: "Balles textiles colorées utilisées pendant les ateliers sonores",
                    },
                    {
                        imageSrc: "/images/galerie/boules-meditation.webp",
                        imageAlt: "Billes métalliques dans un bol en bois pour l'exploration sonore",
                    },
                    {
                        imageSrc: "/images/galerie/sac-materiel.webp",
                        imageAlt: "Instruments et objets sonores disposés au sol avant un atelier",
                    },
                ],
            },
            {
                title: "Espaces d'exploration",
                subtitle: "Des dispositifs simples et sensibles, adaptés aux lieux et aux tout-petits.",
                images: [
                    {
                        imageSrc: "/images/galerie/instruments-plan-large.webp",
                        imageAlt: "Installation sonore avec instruments répartis au sol",
                    },
                    {
                        imageSrc: "/images/galerie/table-exploration-sonore.webp",
                        imageAlt: "Table d'exploration sonore avec objets en métal et matériaux variés",
                    },
                    {
                        imageSrc: "/images/galerie/rideaux-sonores.webp",
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
        closeModalLabel: "Fermer le détail de la prestation",
        items: [
            {
                title: "Crèches & EAJE",
                description: "Ateliers réguliers ou ponctuels, adaptés aux plus jeunes dès 3 mois, autour de la manipulation libre, de l'écoute et de l'éveil sensoriel. Après chaque séance, un déroulé récapitulatif est fourni, accompagné d'idées simples pour poursuivre l'exploration en notre absence.",
                tone: "primary",
                imageSrc: "/images/intervention-creche.webp",
                imageAlt: "Intervention d'éveil musical en crèche",
                badge: "Structures",
                modal: {
                    eyebrow: "Détails de la prestation",
                    subtitle: "Des séances qui évoluent avec les enfants et la vie de votre structure.",
                    body: [
                        "Les interventions en crèche et en EAJE sont pensées comme un rendez-vous régulier plutôt qu'une animation isolée. Les séances peuvent progresser au fil des semaines, avec des rituels, des thèmes et des formats variés pour accompagner l'écoute, le geste, la voix et la manipulation libre.",
                        "Chaque proposition s'adapte à l'âge des enfants, au rythme du groupe et au projet pédagogique de la structure.",
                    ],
                    practicalTitle: "En pratique",
                    points: [
                        {
                            icon: "group",
                            label: "15 participants max.",
                            text: "Un petit groupe pour garder une qualité d'écoute et de présence.",
                        },
                        {
                            icon: "clock",
                            label: "1 à 2 séances par matinée",
                            text: "Un format pratique (30 minutes à 1h) pour accueillir plusieurs groupes dans de bonnes conditions.",
                        },
                        {
                            icon: "path",
                            label: "Parcours évolutif",
                            text: "Des séances régulières avec des thèmes et approches qui se renouvellent.",
                        },
                        {
                            icon: "clock",
                            label: "Matériel fourni",
                            text: "Instruments, objets sonores, supports adaptés aux tout-petits et récapitulatif final fourni.",
                        },
                    ],
                    resourcesTitle: "Ressource",
                    resources: [
                        {
                            label: "Exemple de déroulé : séance Brésil",
                            description: "Un aperçu concret d'une séance thématique avec comptines, jeux de sons et manipulations.",
                            href: "/documents/seance-bresil.pdf",
                        },
                    ],
                    ctaLabel: "Demander plus d'informations",
                },
            },
            {
                title: "Fêtes de structures et festivals",
                description: "Interventions longues (demi-journée, journée...) autour d'un thème précis ou pour une manifestation particulière : fête de fin d'année, festival petite enfance, temps fort associatif ou événement local.",
                tone: "tertiary",
                imageSrc: "/images/galerie/rideaux-sonores.webp",
                imageAlt: "Installation de rideaux sonores extérieurs",
                badge: "Événements",
                modal: {
                    eyebrow: "Détails de la prestation",
                    subtitle: "Un jardin sonore modulable pour transformer un lieu en espace d'exploration.",
                    body: [
                        "Le jardin sonore peut s'installer sur une demi-journée ou une journée, en intérieur ou en extérieur selon les lieux. Il propose plusieurs espaces à traverser : bulle sonore douce, petits parcours, objets détournés, instruments à manipuler et coins d'écoute.",
                        "Le format s'adapte à l'événement, au nombre de participants et à l'équipe présente. Il peut être animé par un ou plusieurs intervenants, et s'accompagner de temps d'échange ou de sensibilisation pour les professionnels.",
                    ],
                    practicalTitle: "En pratique",
                    points: [
                        {
                            icon: "calendar",
                            label: "Demi-journée ou journée",
                            text: "Une présence ajustée au programme de votre fête ou de votre temps fort.",
                        },
                        {
                            icon: "sparkles",
                            label: "Plusieurs espaces",
                            text: "Bulle sonore, parcours, manipulations libres et matières à écouter.",
                        },
                        {
                            icon: "group",
                            label: "Un ou plusieurs intervenants diplômés et expérimentés",
                            text: "Le dispositif peut grandir selon la taille du lieu et le public attendu.",
                        },
                        {
                            icon: "training",
                            label: "Temps formatifs possibles",
                            text: "Des échanges autour de l'animation avec les équipes peuvent prolonger l'expérience.",
                        },
                    ],
                    resourcesTitle: "Ressource",
                    resources: [
                        {
                            label: "Descriptif du jardin sonore",
                            description: "Une présentation du dispositif, de ses espaces et de ses possibilités d'adaptation.",
                            href: "/documents/jardin-sonore-descriptif.pdf",
                        },
                    ],
                    ctaLabel: "Échanger sur votre projet",
                },
            },
            {
                title: "Parents-enfants",
                description: "Des moments privilégiés de partage en famille pour renforcer le lien à travers la musique, les comptines, les instruments et l'exploration sonore libre.",
                tone: "secondary",
                imageSrc: "/images/parents-enfants.webp",
                imageAlt: "Atelier musical partagé entre parent et enfant",
                badge: "Ateliers",
                modal: {
                    eyebrow: "Détails de la prestation",
                    subtitle: "Un temps musical pour créer du lien entre les familles et la structure.",
                    body: [
                        "Les ateliers parents-enfants offrent un cadre simple et chaleureux pour partager comptines, manipulations sonores et jeux d'écoute en famille. Ils sont souvent organisés par une crèche, une médiathèque ou une collectivité.",
                        "Ces temps favorisent la rencontre entre parents et enfants, donnent des idées faciles à reprendre à la maison et permettent aux familles d'entrer dans l'univers sonore de la structure sans enjeu de résultat.",
                    ],
                    practicalTitle: "En pratique",
                    points: [
                        {
                            icon: "group",
                            label: "Familles accueillies",
                            text: "Un format pensé pour les enfants accompagnés d'un parent ou d'un proche.",
                        },
                        {
                            icon: "music",
                            label: "Comptines et instruments",
                            text: "Des propositions accessibles, sensorielles et faciles à partager.",
                        },
                        {
                            icon: "sparkles",
                            label: "Lien avec la structure",
                            text: "Un moment convivial pour ouvrir un temps fort ou nourrir un projet parentalité.",
                        },
                    ],
                    ctaLabel: "Demander plus d'informations",
                },
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
