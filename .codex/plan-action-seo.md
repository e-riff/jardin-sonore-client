# Plan d'action SEO - Jardin Sonore

Source : `🌿 Audit SEO - jardin-sonore.pdf`
Créé le : 2026-05-29
Mode d'utilisation : document de contexte SEO ; [ROADMAP.md](../ROADMAP.md) définit les priorités actives et le point de reprise. L'ordre ci-dessous est celui de l'audit initial, pas celui du chantier courant. Cocher `[x]` quand une action est faite et validée.

## Légende

- `[ ]` À faire
- `[~]` En cours
- `[x]` Fait et validé

## Priorité 1 - Quick wins

Objectif : corriger les signaux SEO les plus simples et les plus rentables, sans refonte du site.

### 1. Humaniser le site avec l'identité de l'intervenant

- Statut : `[x]`
- Impact : fort
- Effort : faible
- Délai cible : cette semaine
- Pourquoi : Google et les visiteurs ont besoin d'identifier la personne réelle derrière le service. C'est le levier E-E-A-T le plus simple.

À faire :

- Ajouter le prénom et le nom de l'intervenant dans la section `#intervenant-musical`.
- Ajouter une bio courte de 2-3 lignes.
- Identifier clairement la photo de l'intervenant si elle est déjà présente.
- Mentionner naturellement : intervenant musical petite enfance, Bac+5 musique et pédagogie, 10 ans d'expérience, +30 structures.

Validation :

- [x] Le prénom et le nom sont visibles sur la page.
- [x] La bio est naturelle et rassurante.
- [x] La photo ou la section associée identifie clairement la personne.

Note de suivi :

- 2026-05-29 : ajout de `Emeric RIFF` une seule fois dans la bio, photo associée à Emeric via le texte alternatif, renommage des ancres principales (`#intervenant-musical`, `#approche`, `#formats`, `#exploration-sonore`). Validé.

### 2. Optimiser le title tag

- Statut : `[x]`
- Impact : fort
- Effort : faible
- Délai cible : cette semaine
- Pourquoi : le title actuel est un peu long et dilue le signal avec une liste de villes.

À faire :

- Remplacer le title actuel par une version plus courte, orientée métier et conversion.

Option retenue :

```text
Éveil musical crèches & EAJE | Saint-Étienne · Lyon
```

Alternative :

```text
Éveil musical en crèche - Lyon & Saint-Étienne | Jardin Sonore
```

Validation :

- [x] Le title fait environ 60 caractères ou moins.
- [x] Il contient un mot-clé métier fort : `éveil musical`, `crèche`, `intervenant`.
- [x] Il évite une longue liste de zones géographiques.

Note de suivi :

- 2026-05-29 : title passé à `Éveil musical crèches & EAJE | Saint-Étienne · Lyon` (51 caractères). Validé.

### 3. Réécrire la meta description

- Statut : `[x]`
- Impact : moyen
- Effort : faible
- Délai cible : cette semaine
- Pourquoi : la description actuelle est trop longue et sera tronquée dans les résultats Google.

À faire :

- Remplacer la meta description par un texte plus court, plus lisible et orienté action.

Version retenue :

```text
Ateliers d'éveil musical pour crèches et EAJE à Saint-Étienne, Lyon et dans le Pilat. 10 ans d'expérience, diplôme petite enfance, devis sur demande.
```

Validation :

- [x] La meta description est proche de 155 caractères.
- [x] Elle mentionne clairement les crèches / EAJE.
- [x] Elle contient un appel à l'action.

Note de suivi :

- 2026-05-29 : meta description passée à 149 caractères, avec Saint-Étienne avant Lyon et une formulation plus naturelle autour du diplôme petite enfance. Validé.

### 4. Ajouter un schema `Person` dans le JSON-LD

- Statut : `[x]`
- Impact : moyen
- Effort : faible
- Délai cible : cette semaine
- Pourquoi : le site déclare l'activité, mais pas encore la personne qui porte le service.

À faire :

- Ajouter une entité `Person` au JSON-LD.
- Relier cette personne à l'organisation ou au service existant.

Base proposée :

```json
{
  "@type": "Person",
  "name": "[Prénom Nom]",
  "jobTitle": "Intervenant musical petite enfance",
  "hasCredential": {
    "@type": "EducationalOccupationalCredential",
    "name": "Diplôme dans le champ de la formation et de la petite enfance"
  },
  "worksFor": {
    "@id": "https://jardin-sonore.fr/#organization"
  }
}
```

Validation :

- [x] Le JSON-LD reste valide.
- [x] Le nom réel est renseigné.
- [x] La personne est reliée à l'entité existante.

Note de suivi :

- 2026-05-29 : ajout d'une entité `Person` avec `@id: #person`, reliée à `#organization` via `worksFor`; l'organisation référence aussi la personne via `founder`. Validé.

### 5. Corriger la duplication des services dans le JSON-LD

- Statut : `[x]`
- Impact : moyen
- Effort : faible
- Délai cible : cette semaine
- Pourquoi : les 3 services semblent déclarés deux fois, à la fois dans `makesOffer` et comme entités autonomes.

À faire :

- Inspecter le JSON-LD existant.
- Supprimer la duplication inutile si les services sont bien déjà déclarés dans `makesOffer`.
- Garder une structure propre et non redondante.

Validation :

- [x] Chaque service n'est déclaré qu'une seule fois.
- [x] Le JSON-LD reste valide.
- [x] Les offres principales restent visibles dans les données structurées.

Note de suivi :

- 2026-05-29 : suppression des services autonomes en fin de `@graph`; les services restent déclarés dans `makesOffer.itemOffered`. Validé.

### 6. Rendre le numéro de téléphone visible

- Statut : `[x]` - non retenu
- Impact : moyen
- Effort : faible
- Délai cible : cette semaine
- Pourquoi : le bouton d'appel existe, mais le numéro n'est pas visible dans le HTML. C'est un signal de confiance et de SEO local.

À faire :

- Ne pas afficher le numéro de téléphone en clair.
- Ne pas ajouter le numéro au JSON-LD.
- Conserver le bouton d'appel existant sans exposer le numéro directement dans la page.

Validation :

- [x] Décision validée : le numéro ne doit pas être visible publiquement.
- [x] Le bouton d'appel existant reste disponible.
- [x] Le JSON-LD ne contient pas le numéro.

Note de suivi :

- 2026-05-29 : action volontairement non réalisée à la demande d'Emeric, pour éviter d'exposer le numéro publiquement.

## Priorité 2 - Pages et preuves de confiance

Objectif : créer des entrées SEO plus ciblées que la homepage et renforcer la conversion.

### 7. Créer une page dédiée `/eveil-musical-lyon`

- Statut : `[ ]`
- Impact : fort
- Effort : moyen
- Délai cible : mois 1-2
- Pourquoi : `éveil musical lyon` est l'opportunité géolocalisée principale du rapport.

À faire :

- Créer une page dédiée à l'éveil musical à Lyon.
- Mettre le mot-clé dans l'URL, le title, le H1 et le contenu.
- Ajouter une description claire de l'offre pour Lyon et alentours.
- Ajouter un schema `Service` avec `areaServed: Lyon`.

Validation :

- [ ] La page existe à `/eveil-musical-lyon`.
- [ ] Le H1 cible clairement `éveil musical Lyon`.
- [ ] La page n'est pas une simple copie de la homepage.

### 8. Créer une page dédiée `/eveil-musical-creche`

- Statut : `[x]`
- Impact : fort
- Effort : moyen
- Délai cible : mois 1-2
- Pourquoi : les requêtes `éveil musical crèche` et `éveil musical en crèche` sont très qualifiées.

À faire :

- Créer une page orientée crèches et EAJE.
- Décrire le déroulement d'une séance.
- Expliquer les bénéfices pour les enfants et les équipes.
- Ajouter les modalités pratiques : durée, fréquence, adaptation, devis.

Validation :

- [x] La page existe à `/eveil-musical-creche`.
- [x] Elle cible explicitement les crèches / EAJE.
- [x] Elle contient un appel à l'action clair.

Note de suivi :

- 2026-10-07 : page publiée (commit `af29dc7`, tag `deploy-eveil-musical-creche-20261007-01`). Elle présente les ateliers avec les enfants et les professionnel·les, l'approche, les objets, les repères pratiques, le portail et les autres formats. Métadonnées canoniques/Open Graph, `Service` et `BreadcrumbList` en JSON-LD, entrée sitemap, liens depuis l'accueil et le footer ; HTTP 200 confirmé pour la page, le sitemap et le PDF d'exemple. Le suivi d'indexation et de performances attend des données postérieures à cette publication.

### 9. Ajouter 2-3 témoignages de structures partenaires

- Statut : `[ ]`
- Impact : fort
- Effort : moyen
- Délai cible : mois 1-2
- Pourquoi : les témoignages renforcent la confiance, l'E-E-A-T et la conversion.

À faire :

- Collecter 2-3 retours de structures partenaires.
- Ajouter le nom de la structure si autorisé.
- Idéalement inclure une ville ou un type de structure : crèche, EAJE, festival, relais petite enfance.

Validation :

- [ ] Au moins 2 témoignages sont visibles.
- [ ] Les témoignages sont crédibles et contextualisés.
- [ ] Les autorisations de publication sont validées.

### 10. S'inscrire sur des annuaires professionnels petite enfance

- Statut : `[ ]`
- Impact : moyen
- Effort : faible
- Délai cible : mois 1-2
- Pourquoi : le profil de backlinks est faible, surtout côté liens français et locaux.

À faire :

- Identifier 3-5 annuaires pertinents.
- Commencer par les annuaires petite enfance et locaux.
- Vérifier que le nom, la zone, le téléphone et l'URL sont cohérents partout.

Pistes citées dans le rapport :

- `lesprosdelapetiteenfance.fr`
- `pagesjaunes.fr`
- annuaires EAJE / petite enfance locaux

Validation :

- [ ] Les fiches créées pointent vers le site.
- [ ] Les informations de contact sont cohérentes.
- [ ] Les liens obtenus sont listés ici ou dans un fichier de suivi.

## Priorité 3 - Long terme

Objectif : développer l'autorité et capter des recherches informationnelles qualifiées.

### 11. Créer un article ressource : choisir un intervenant musical pour une crèche

- Statut : `[ ]`
- Impact : moyen
- Effort : élevé
- Délai cible : mois 3-6
- Pourquoi : capter les recherches de pros qui préparent un choix d'intervenant.

À faire :

- Rédiger un article ciblant `intervenant éveil musical crèche`.
- Répondre aux questions pratiques : critères, sécurité, pédagogie, adaptation, budget, fréquence.
- Relier l'article vers la page `/eveil-musical-creche`.

Validation :

- [ ] L'article est publié.
- [ ] Il répond à une intention professionnelle claire.
- [ ] Il contient un lien interne vers l'offre crèche.

### 12. Travailler les backlinks locaux

- Statut : `[ ]`
- Impact : fort
- Effort : élevé
- Délai cible : long terme
- Pourquoi : l'autorité du domaine est le point faible majeur du rapport.

À faire :

- Demander des liens aux structures partenaires qui ont une page web.
- Contacter des associations petite enfance locales.
- Chercher des opportunités de presse locale autour de Saint-Étienne, Lyon et Pilat.

Validation :

- [ ] Une liste de prospects backlinks est créée.
- [ ] Les premiers contacts sont envoyés.
- [ ] Les liens obtenus sont suivis.

## Points à garder en tête

- 2026-10-08 : fiche Google Business Profile existante revue avec Emeric. Elle est configurée sans adresse publique, avec des zones desservies (dont Saint-Étienne, le Gier, le Pilat et Lyon), des horaires du lundi au vendredi de 8 h à 18 h, une photo d'atelier en couverture et un profil de réseau social ajouté. La catégorie principale « Artiste » est conservée faute de meilleure option trouvée dans Google. Le téléphone reste volontairement absent. La description observée cible les crèches et EAJE ; la correction de la virgule dans « Le Jardin Sonore, propose » reste à confirmer sur la fiche. Ce suivi SEO n'est plus le point de reprise du projet : voir la [ROADMAP.md](../ROADMAP.md).
- Ne pas chercher à se positionner sur `jardin sonore` : la requête est dominée par le festival de Vitrolles.
- Audience prioritaire : les responsables de crèches, EAJE et structures petite enfance qui cherchent un musicien intervenant.
- Prioriser les formulations métier `musicien intervenant en crèche`, `intervenant musical petite enfance`, `éveil musical en crèche` et `ateliers d'éveil musical pour crèches` ; ne pas présenter l'offre comme des cours destinés aux familles.
- L'export Search Console du 5 octobre montre des volumes faibles mais une intention cohérente : 2 impressions pour `intervenant musical en crèche` (position moyenne 5,5) et 1 pour `éveil musical crèche` (position 20). La page d'accueil est la seule URL présente dans l'export des pages ; ces données ne suffisent pas à estimer un potentiel de trafic.
- La page principale d'offre crèche/EAJE est publiée. Évaluer ses premiers résultats avant d'éventuelles pages locales ; les projets et partenaires pourront renforcer la confiance et apporter des visites si leurs sites font un lien vers l'offre.
- La homepage est déjà techniquement saine : les gains viennent surtout de l'identité, des balises, des données structurées, des pages dédiées et de l'autorité locale.

## Reprise du plan

Ce document conserve les recommandations de l'audit initial et note la livraison de la page crèche/petite enfance ainsi que la revue de la fiche Google Business Profile. L'état et l'ordre de travail courants sont dans [ROADMAP.md](../ROADMAP.md) ; le prochain point SEO utile sera l'observation de l'indexation et des requêtes après publication, sans conclure à partir de l'export Search Console du 5 octobre.
