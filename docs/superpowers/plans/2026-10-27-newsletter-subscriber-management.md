# Gestion des abonnements newsletter — plan d’implémentation

> **For agentic workers:** méthode d’exécution native, tâche par tâche.

**Clôture — 27 octobre 2026 :** l’index, les parcours de consentement partagé et les liens depuis l’annuaire sont implémentés. À la demande explicite de l’utilisateur, EasyAdmin et ses fichiers propres ont ensuite été retirés après la reprise des fonctions métier.

**Goal:** Gérer les consentements newsletter depuis l’annuaire et un index dédié dans Mailing, sans dupliquer les adresses ni leur état.

**Architecture:** Conserver `EmailContactEntity` comme source unique de l’adresse et de son consentement. Ajouter un catalogue paginé dans le backoffice métier, réutiliser le gestionnaire de consentement existant pour les changements, et exposer depuis l’annuaire l’état global avec un accès à sa gestion.

**Tech Stack:** Symfony, Doctrine DBAL/ORM, Twig, contrôleurs Stimulus existants, traductions YAML.

**Spec:** Décision utilisateur du 27 octobre 2026 : état d’abonnement unique par adresse ; état consultable depuis l’annuaire ; gestion centralisée dans Mailing ; préserver les consentements et les désinscriptions.

## Contraintes globales

- Aucun nouveau schéma sans nécessité ; préserver les adresses liées et les abonnés libres dans `email_contact`.
- Une désinscription ou réinscription s’applique à l’adresse partagée, y compris lorsqu’elle figure sur plusieurs fiches.
- Toute réinscription manuelle requiert l’attestation de consentement déjà prévue par le formulaire.
- L’index est paginé, filtrable et adapté aux volumes de production.
- La suppression d’EasyAdmin a été explicitement confirmée après la reprise de ses fonctions métier.

## Points de revue

- Une adresse liée à plusieurs fiches ne doit apparaître qu’une fois dans la liste ; afficher le nombre de fiches liées.
- Une désinscription doit rendre l’adresse inéligible aux envois, sans supprimer ses liens d’annuaire.
- Une réinscription d’une adresse liée ne doit pas la classer à tort comme abonné libre.
- Une adresse libre ajoutée via le backoffice doit conserver l’origine et la date de consentement.
- Les filtres et la recherche doivent fonctionner avec la pagination.

## Fichiers concernés

- Modifier `src/Application/Controller/NewsletterSubscriberAdminController.php` pour l’index métier et des liens qui ne dépendent pas d’EasyAdmin.
- Créer un query service DBAL paginé pour la liste des adresses et leur nombre de fiches liées.
- Modifier `src/Infrastructure/Mailing/FreeNewsletterSubscriptionManager.php` pour distinguer réinscription d’une adresse liée et inscription libre.
- Modifier `templates/newsletter/subscriber_form.html.twig` et créer `templates/newsletter/subscriber_index.html.twig`.
- Modifier `templates/internal/directory/_contact_rows.html.twig` pour afficher l’état et ouvrir sa gestion.
- Ajouter une entrée Abonnés sous Mailing dans `templates/internal/base.html.twig`.
- Ajouter les traductions et styles requis.

## Tâches

### 1. Catalogue des adresses

- Ajouter la recherche par e-mail, les filtres type (toutes, liées, libres) et état (éligible, désinscrit, inactif), avec pagination de 25 lignes.
- Charger pour chaque ligne les métadonnées de consentement et le nombre de liens actifs vers l’annuaire.
- Exposer le catalogue sur `/mailing/subscribers` et maintenir les formulaires d’ajout/gestion sous les routes Mailing.

### 2. Actions de consentement

- Garder l’attestation obligatoire pour réinscrire.
- Si l’adresse possède des liens d’annuaire, modifier son état global sans définir `freeNewsletterSubscription`.
- Si elle est libre, conserver le comportement existant de confirmation backoffice et ses métadonnées.
- Conserver l’action de désinscription protégée par CSRF et consommer toute confirmation en attente.

### 3. Accès depuis l’annuaire

- Afficher un badge d’état auprès de chaque adresse e-mail.
- Lier le badge à la fiche d’abonnement partagée ; ne pas ajouter un second consentement par fiche.
- Ajouter le lien Abonnés dans le sous-menu Mailing.

### 4. Vérification

- Lancer les lint PHP, Twig, YAML et `git diff --check`.
- Ne pas exécuter de tests automatisés sauf demande explicite.
- Vérifier qu’aucune migration n’est générée ou requise par le changement.
