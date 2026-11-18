# Dossiers : accès au portail et séances de la semaine

## Objectif

Donner accès au portail depuis un dossier et retrouver rapidement les dossiers des séances de la semaine, en gardant les rappels immédiatement visibles.

## Approche retenue

Réutiliser la gestion des comptes et invitations existante dans un dialogue du dossier ; réutiliser la liste des dossiers avec une vue dédiée aux validés en cours. Un renvoi vers la gestion technique des comptes imposerait de ressaisir la structure et quitterait le dossier. Garder tous les validés sur l'accueil continuerait à encombrer la page.

## Accès au portail

- Bouton « Donner accès au portail » lorsque le dossier possède une structure liée.
- Dialogue proposant les personnes et adresses connues de cette structure, ainsi qu'une adresse libre.
- Récapitulatif de la structure et de l'adresse avant validation explicite.
- Nouveau compte : création et invitation par le mécanisme existant.
- Compte existant : rattachement à la structure sans doublon ; signaler un accès déjà présent sans réinviter automatiquement.
- Respecter les autorisations existantes ; l'accès porte sur la structure, pas seulement sur le dossier.
- Aucun envoi réel pendant les vérifications techniques.

## Accueil des dossiers

- Petit tableau « Séances de cette semaine » au début, du lundi au dimanche en heure de Paris, incluant les séances déjà passées et excluant les annulées.
- Une ligne compacte par séance liée à un dossier : date et heure, lieu si renseigné et repère court du dossier ou de la structure.
- Toute la ligne ouvre le dossier ; navigation clavier et focus visibles.
- Garder une présentation dense sur mobile, sans cartes volumineuses ni détails secondaires. Les rappels suivent immédiatement.
- Conserver les priorités, les rappels à venir et les dossiers en discussion sans rappel.
- Retirer uniquement le groupe des dossiers validés sans rappel de l'accueil. Un validé avec une action reste visible dans les rappels.
- Ajouter « Dossiers validés en cours » à côté de « Dossiers clôturés » : vue de tous les dossiers validés, avec ou sans rappel, recherche et retour à l'accueil.

## Réalisation et vérification

Conserver Symfony, Twig, les composants et interactions existants ; traduire les libellés. Pas de refonte générale ni de dépendance nouvelle prévue. Vérifier les bornes de semaine, les annulations, la sélection des dossiers, les comptes existants, les autorisations et le rendu compact. Lancer les contrôles backend adaptés et compiler les assets modifiés.
