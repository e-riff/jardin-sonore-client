# Identité visuelle de la vitrine

État constaté le 7 octobre 2026, après la publication de `/eveil-musical-creche`. Ce document aide à prolonger la vitrine avec les éléments déjà présents. Le code reste la référence pour les valeurs exactes ; la [roadmap centrale](../ROADMAP.md) porte les priorités et l'état de livraison.

## Fondations

Les couleurs, polices, rayons et espacements sont définis dans [`globals.css`](../jardin-sonore-client/src/app/globals.css) avec `@theme`. Utiliser les noms Tailwind du projet plutôt que recopier leurs valeurs dans chaque section.

| Rôle | Jeton | Valeur actuelle | Usage courant |
| --- | --- | --- | --- |
| Fond principal | `background` | `#fdf8f5` | Respiration entre les sections |
| Surface douce | `surface-container-low` | `#f8f3f0` | Bandeaux et sections alternées |
| Surface de carte | `surface-container-lowest` | `#ffffff` | Repères et cartes lisibles |
| Texte principal | `on-surface` | `#1c1b1a` | Titres courts et contenus prioritaires |
| Texte secondaire | `on-surface-variant` | `#554240` | Paragraphes et légendes |
| Accent principal | `primary` | `#be4f41` | Grands titres, liens et action principale |
| Accent végétal | `secondary` | `#47664b` | Sourcils de section, repères et titres secondaires |
| Accent terre | `tertiary` | `#6d4820` | Variation ponctuelle, notamment une icône de repère |
| Séparateur | `outline-variant` | `#dbc1bd` | Bordures légères |

Les titres éditoriaux emploient **Noto Serif** (`font-serif`) ; la navigation, les boutons, les libellés et les petits repères emploient **Plus Jakarta Sans** (`font-sans`). Les grands titres restent courts et respirent ; les paragraphes privilégient une largeur limitée et `leading-7` ou `leading-8`. Les contenus français vivent dans [`fr.ts`](../jardin-sonore-client/src/i18n/dictionaries/fr.ts), pas dans les composants.

## Composants réutilisables

| Composant | Usage | Point d'attention |
| --- | --- | --- |
| [`SectionHeading`](../jardin-sonore-client/src/components/SectionHeading.tsx) | Sourcil, titre `h2` et description, alignés à gauche ou centrés | Fournir un `id` quand la section utilise `aria-labelledby` ; le composant rend une chaîne comme paragraphe et un contenu riche comme bloc. |
| [`EditorialCopy`](../jardin-sonore-client/src/components/EditorialCopy.tsx) | Paragraphes éditoriaux avec séparateurs `\n\n` et quelques passages `**en gras**` | Réserver le gras aux idées clés ; ce parseur n'est pas un moteur Markdown complet. |
| [`Breadcrumbs`](../jardin-sonore-client/src/components/Breadcrumbs.tsx) | Fil d'Ariane d'une page publique et JSON-LD `BreadcrumbList` | Ajouter la route et ses libellés à [`public-breadcrumbs.ts`](../jardin-sonore-client/src/lib/public-breadcrumbs.ts) et au dictionnaire ; ne pas écrire un fil distinct dans chaque page. |
| [`ServiceCard`](../jardin-sonore-client/src/components/ServiceCard.tsx), [`ServicesBrowser`](../jardin-sonore-client/src/components/ServicesBrowser.tsx) et [`ServiceModal`](../jardin-sonore-client/src/components/ServiceModal.tsx) | Cartes de formats de l'accueil et détail ouvrable | La carte est activée par un vrai bouton. Le format sélectionné suit `?format=slug`, ce qui permet un lien direct ; la fermeture rend le focus à la carte. Les données et les libellés sont typés dans [`content.ts`](../jardin-sonore-client/src/types/content.ts) et définis dans `fr.ts`. |

Les sections [`EarlyChildhood*`](../jardin-sonore-client/src/components/sections/) composent **la page crèche**. Elles servent de références visuelles pour une future page éditoriale, mais ne forment pas une bibliothèque générique à paramétrer d'avance. Réutiliser les composants ci-dessus et extraire un nouveau motif seulement après un second usage réel.

## Motifs à reprendre

- **Héros éditorial** : texte et photo en deux colonnes sur grand écran, empilés sur mobile ; sourcil vert, `h1` serif rouge, introduction aérée, bouton plein puis bouton contour. Voir [`EarlyChildhoodHeroSection`](../jardin-sonore-client/src/components/sections/EarlyChildhoodHeroSection.tsx).
- **Récit alterné** : fond principal et surface douce se succèdent ; les photos changent de côté selon le sujet. Les sections « séance », « approche », « objets » et « portail » ont chacune un rôle éditorial distinct. Voir la composition dans [`page.tsx`](../jardin-sonore-client/src/app/eveil-musical-creche/page.tsx).
- **Quatre repères pratiques** : petites cartes blanches, valeur serif dominante, précision en sans, icône dans un cercle de 46 px débordant légèrement au-dessus. Le libellé complet reste dans le `dt` masqué visuellement. Voir [`EarlyChildhoodPracticalSection`](../jardin-sonore-client/src/components/sections/EarlyChildhoodPracticalSection.tsx).
- **Proposition secondaire** : encart léger avec une seule icône « idée » pour la co-construction, sans rivaliser avec l'offre principale. Voir [`EarlyChildhoodApproachSection`](../jardin-sonore-client/src/components/sections/EarlyChildhoodApproachSection.tsx).
- **Photos de pratique** : mosaïque de deux images carrées et d'un portrait, avec cadrages ajustés et textes alternatifs descriptifs. Voir [`EarlyChildhoodEvidenceSection`](../jardin-sonore-client/src/components/sections/EarlyChildhoodEvidenceSection.tsx).
- **Témoignage comme pause** : une citation centrée, largeur limitée, signe typographique en filigrane, attribution discrète et lien vers les autres avis ; pas de carrousel. Voir [`EarlyChildhoodTestimonialSection`](../jardin-sonore-client/src/components/sections/EarlyChildhoodTestimonialSection.tsx).
- **Autres formats** : cartes liées sur fond doux, titre, courte description et appel à découvrir. Le bloc indique les zones d'intervention sans répéter les mêmes liens sous la grille. Voir [`EarlyChildhoodExtrasSection`](../jardin-sonore-client/src/components/sections/EarlyChildhoodExtrasSection.tsx).

## Mouvement et interaction

Le mouvement sert à confirmer une interaction, sans empêcher la lecture. Les boutons du héros changent de couleur en **200 ms**. Les cartes de formats de l'accueil se soulèvent légèrement (`-translate-y-1`, **300 ms**) et leur photo s'agrandit doucement (**700 ms**) ; les cartes « autres formats » se déplacent de `-translate-y-0.5` en **200 ms**. Les quatre repères pratiques ne bougent pas : seul leur contour et leur ombre varient en **200 ms**.

Ces transitions de surface utilisent `motion-reduce:transition-none` là où elles ont été ajoutées. Avant de copier une animation d'icône ou une nouvelle translation, vérifier aussi `prefers-reduced-motion`. Le focus reste visible, même sans survol ; les zones d'action principales ont au moins 48 px de hauteur. Pour une interaction ouvrable, garder le bouton, le dialogue nommé, la fermeture par Échap et le retour du focus plutôt que reproduire seulement l'effet visuel.

## Images et contenu

- Utiliser `next/image`, les fichiers de `public/`, un `alt` adapté à l'information apportée et `sizes` cohérent avec la grille. Les photos de la page crèche sont dans [`public/images/ateliers/`](../jardin-sonore-client/public/images/ateliers/).
- Préparer les nouvelles photos en WebP et retirer EXIF/XMP avant de les placer dans `public/`. Vérifier le cadrage sur mobile et sur ordinateur ; un portrait ne doit pas être forcé dans une vignette trop large.
- Garder la progression éditoriale de la page d'offre : **promesse → séance concrète → approche artistique → objets → portail → autres formats**. Le portail est annoncé dès le haut puis détaillé après la séance.
- Pour une nouvelle page publique, compléter les métadonnées, le sitemap, les liens de navigation et les données structurées utiles à son contenu ; le fil d'Ariane réutilise le composant commun.

## Suite possible

Ce catalogue est documentaire. Si plusieurs nouvelles pages reprennent réellement le même héros, les mêmes cartes ou les mêmes boutons, extraire alors un composant commun avec des variantes limitées. La mise à jour autonome des photos de la vitrine et des liens vers les publications récentes reste une piste de la [roadmap](../ROADMAP.md), distincte du présent catalogue.
