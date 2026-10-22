# Séances, MCP et page de collaborations — plan de reprise

## Ordre convenu

1. Simplifier le compositeur et les formulaires de séance dans le backoffice.
2. Ouvrir au MCP la lecture et la création de séances, en réutilisant le répertoire.
3. Faire ensuite la page front de collaborations, structures et liens partenaires avec un objectif SEO.

## 1. Formulaires et compositeur de séances

- Parcourir les formulaires et scripts JavaScript existants avant de modifier le code.
- Retirer les champs, commandes ou étapes réellement inutiles et réduire les répétitions d'interface.
- Reproduire et corriger les dysfonctionnements JavaScript constatés dans l'usage.
- Garder le changement ciblé : pas de refonte générale ni de réorganisation des frontières applicatives.
- Vérifier que l'ajout d'un élément du répertoire à une séance ne modifie pas sa fiche source ; les consignes « en plus » restent propres à la séance.
- Vérifier que l'aperçu HTML et le PDF continuent à refléter la séance après simplification.

## 2. Création de séances via MCP

- S'appuyer sur les éléments existants du répertoire au lieu de les recopier ou de les modifier.
- Exposer la lecture et la création d'une séance ; inclure l'édition si le modèle et les cas d'usage existants le permettent sans élargir le chantier.
- Préserver les listes d'éléments et les consignes propres à la séance.
- Commencer par créer des séances en brouillon. La publication, la programmation et les notifications restent hors des outils de ce lot.
- Utiliser une confirmation explicite par écriture et un verrou serveur dédié, fermé jusqu'à ce que les outils soient configurés sur « Demander » dans Vibes.

## 3. Page front SEO de collaborations

- Créer une page publique dédiée aux collaborations, personnes et structures partenaires, avec un contenu descriptif original plutôt qu'une simple collection de logos.
- Donner aux liens sortants un contexte utile et une destination vérifiée ; prévoir des liens entrants à proposer aux partenaires vers la page pertinente.
- Préparer les titres, description, URL canonique, données structurées si justifiées, maillage interne et présence dans le sitemap selon les conventions Next.js du projet.
- Obtenir l'accord adapté avant de publier noms, citations, logos ou photos ; ne pas présenter une relation ou un témoignage sans validation.
