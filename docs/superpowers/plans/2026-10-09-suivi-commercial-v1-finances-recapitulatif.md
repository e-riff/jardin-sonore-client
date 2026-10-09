# Suivi commercial V1 — devis, factures et récapitulatif Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Compléter le suivi commercial par les références de devis, l'état des factures et un courriel personnel de gestion configurable, envoyé une seule fois les jours concernés.

**Architecture:** Les devis et factures sont des fiches de suivi liées au dossier, tandis que leurs documents restent dans Drive et leurs échanges dans Gmail. Les rappels impayés sont des actions métier durables. Un distributeur périodique lit le réglage horaire du backoffice, prépare le contenu du jour et utilise l'envoi asynchrone existant sans modifier les autres courriels.

**Tech Stack:** PHP 8.4, Symfony 8.1, Doctrine ORM, Twig/Symfony UX, Mailer et Messenger, cron cPanel existant, PHPUnit 12.

**Spec:** [Suivi commercial V1 — conception](../specs/2026-10-08-suivi-commercial-v1-design.md). Dépend du [plan du cœur métier](2026-10-09-suivi-commercial-v1-coeur.md), qui fournit dossiers, actions, historique et accueil.

## Global Constraints

- Interface dans le backoffice métier, mobile first, suivant les maquettes Stitch et les patterns UX validés dans le plan du cœur.
- Aucun document PDF n'est déposé ou généré par cette V1 ; seule la référence du devis, son nom exact et son montant sont saisis.
- Un devis envoyé est immuable ; une correction crée un nouveau numéro et conserve la relation avec l'ancien.
- La facture est émise hors du backoffice ; le paiement est constaté manuellement sur le compte bancaire.
- Relance interne à trente jours calendaires après émission, une seule par facture impayée ; aucun envoi au client par cette relance.
- Courriel personnel à 8 h par défaut, heure modifiable en Europe/Paris, du lundi au vendredi, jamais vide ni répété pour un même jour ; pause limitée à ce courriel.
- Aucun commit sans accord explicite. Demander confirmation avant d'exécuter la migration générée et contrôler le diff de schéma.

## Review Focus

1. Deux devis d'un même projet couvrant deux périodes : la signature du premier confirme le projet, le second peut rester envoyé — test de la tâche 1.
2. Numéro de devis répété dans le backoffice : l'utilisateur reçoit une erreur avant enregistrement, sans prétendre connaître les fichiers Drive — test de la tâche 1.
3. Facture enregistrée plus de trente jours après sa date d'émission : une relance apparaît immédiatement, sans doublon au prochain cron — test de la tâche 2.
4. Paiement après report de relance : la relance encore ouverte est retirée et l'historique du report subsiste — test de la tâche 2.
5. Heure modifiée, pause/reprise, changement d'heure saisonnier ou cron répété : au plus un courriel par date locale admissible — test de la tâche 4.

---

## Carte des fichiers

- `src/Infrastructure/Doctrine/Entity/CommercialQuoteEntity.php`, `CommercialInvoiceEntity.php`, `CommercialDigestSettingsEntity.php`, `CommercialDigestDeliveryEntity.php` : suivi durable et clés d'unicité.
- `src/Application/Commercial/RecordCommercialQuote.php`, `RecordCommercialInvoice.php`, `CreateOverdueInvoiceActions.php` : règles d'envoi, signature, paiement et relance.
- `src/Application/Controller/CommercialQuoteController.php`, `CommercialInvoiceController.php`, `CommercialDigestSettingsController.php` et `templates/commercial/` : édition et listes authentifiées.
- `src/Application/Command/DispatchCommercialDigestCommand.php`, `src/Application/Commercial/BuildCommercialDigest.php`, `src/Infrastructure/Mailer/SymfonyCommercialDigestSender.php` : orchestration, sélection du contenu et SMTP.
- `translations/commercial+intl-icu.fr.yaml` et `templates/mail/commercial_digest.*.twig` : libellés et courriel HTML/texte.

Les chemins sont relatifs à `jardin-sonore-backend/`. Les travaux sur l'annuaire et le formulaire public sont déjà dans le plan du cœur.

### Task 1: Devis envoyés et confirmation du dossier

**Files:** Create `src/Infrastructure/Doctrine/Entity/CommercialQuoteEntity.php`, `src/Application/Commercial/RecordCommercialQuote.php`, `src/Application/Controller/CommercialQuoteController.php`, `src/Application/Form/CommercialQuoteType.php`, `templates/commercial/quote/*.html.twig` et une migration générée ; modify la fiche dossier et son historique ; test `tests/Integration/Infrastructure/Commercial/CommercialQuoteTest.php`, `tests/Functional/Application/Controller/CommercialQuoteControllerTest.php`.

**Interfaces:** `RecordCommercialQuote::sent(CommercialProjectEntity, string $reference, string $filename, int $amountCents, DateTimeImmutable $sentOn, ?CommercialQuoteEntity $replaces): CommercialQuoteEntity` ; `RecordCommercialQuote::markSigned(CommercialQuoteEntity, DateTimeImmutable $signedOn): void`. La référence est unique parmi les devis enregistrés dans le backoffice ; le montant est stocké en centimes.

- [ ] Écrire les tests : une fiche n'existe qu'au moment de l'envoi ; envoi propose une relance facultative à `+7` jours ; référence répétée refusée ; correction d'un devis envoyé exige nouvelle référence et lien « remplace » ; deux devis d'un même projet avancent séparément ; marquer le premier signé confirme le projet.
- [ ] Lancer les tests ciblés et vérifier l'échec attendu.
- [ ] Implémenter entité, opération métier, contrôleur et formulaire ; ajouter le lien général vers le dossier Drive des devis depuis le backoffice et les événements d'historique. Garder les PDF hors de la base.
- [ ] Générer et relire la migration du devis (référence unique parmi les devis enregistrés) ; demander confirmation avant exécution et vérifier le diff de schéma. Après accord, l'appliquer en développement/test.
- [ ] Relancer les tests ciblés, `composer cs-check` et `composer stan`.

### Task 2: Factures et relance impayée

**Files:** Create `src/Infrastructure/Doctrine/Entity/CommercialInvoiceEntity.php`, `src/Application/Commercial/RecordCommercialInvoice.php`, `CreateOverdueInvoiceActions.php`, `src/Application/Controller/CommercialInvoiceController.php`, `src/Application/Form/CommercialInvoiceType.php`, `templates/commercial/invoice/*.html.twig` et une migration générée ; modify l'accueil « Suivi clients » ; test `tests/Integration/Infrastructure/Commercial/CommercialInvoiceTest.php`, `tests/Functional/Application/Controller/CommercialInvoiceControllerTest.php`.

**Interfaces:** `RecordCommercialInvoice::issue(CommercialProjectEntity, string $reference, int $amountCents, DateTimeImmutable $issuedOn): CommercialInvoiceEntity`, `markPaid(CommercialInvoiceEntity, DateTimeImmutable $paidOn): void` ; `CreateOverdueInvoiceActions::run(DateTimeImmutable $now): int`. L'action de relance porte l'identifiant de la facture pour empêcher un second rappel automatique.

- [ ] Écrire les tests : référence de facture enregistrée en double refusée ; facture saisie séparément des autres ; impayé avant J+30 sans relance ; à J+30 une seule action ; facture ancienne saisie tardivement crée son action ; relance reportée non recréée ; paiement retire la relance encore ouverte ; GET de fiche ne marque jamais payé ; clôture du projet bloquée tant qu'une facture enregistrée est impayée.
- [ ] Lancer les tests ciblés et vérifier l'échec attendu.
- [ ] Implémenter suivi, page/listes et lien depuis le dossier ; les actions de paiement et d'annulation utilisent POST protégé par CSRF. Une action « Préparer la facture » reste créée manuellement tant que l'agenda n'est pas intégré.
- [ ] Générer et relire la migration de facture, sa référence unique parmi les factures enregistrées et sa clé de rappel ; demander confirmation avant exécution, appliquer après accord en développement/test et vérifier le diff de schéma.
- [ ] Relancer les tests ciblés, `composer cs-check` et `composer stan`.

### Task 3: Récapitulatif personnel et réglages

**Files:** Create `src/Infrastructure/Doctrine/Entity/CommercialDigestSettingsEntity.php`, `CommercialDigestDeliveryEntity.php`, `src/Application/Commercial/BuildCommercialDigest.php`, `src/Application/Command/DispatchCommercialDigestCommand.php`, `src/Infrastructure/Mailer/SymfonyCommercialDigestSender.php`, `src/Application/Controller/CommercialDigestSettingsController.php`, `templates/mail/commercial_digest.html.twig`, `commercial_digest.txt.twig` ; modify `translations/commercial+intl-icu.fr.yaml` et l'accueil ; test `tests/Unit/Application/Commercial/BuildCommercialDigestTest.php`, `tests/Integration/Infrastructure/Commercial/CommercialDigestDispatchTest.php`, `tests/Functional/Application/Controller/CommercialDigestSettingsControllerTest.php`.

**Interfaces:** `BuildCommercialDigest::forDate(DateTimeImmutable $localDay): CommercialDigest` sélectionne demandes à qualifier, actions dues/en retard, factures à relancer et, le lundi, dossiers actifs sans action ; `DispatchCommercialDigestCommand` lit heure et pause, verrouille la date locale et programme au plus un courriel. Le réglage expose seulement `HH:mm` et la pause, pas une expression cron.

- [ ] Écrire les tests : lundi avec demandes et dossiers sans action ; autre jour sans section hebdomadaire ; week-end sans envoi ; échéance du dimanche visible lundi ; contenu vide sans envoi ; heure par défaut `08:00 Europe/Paris` ; heure modifiée prise en compte ; pause sans altérer les actions ni les emails clients ; deux exécutions et changement d'heure d'été/hiver sans doublon ; lien de fiche qui conserve la destination après connexion et dont le GET ne paie rien.
- [ ] Lancer les tests ciblés et vérifier l'échec attendu.
- [ ] Implémenter réglage dans le backoffice, rendu HTML/texte, liens authentifiés vers listes/fiches et distribution durable en réutilisant le worker existant. Une relance de facture apparaît dans la section factures, sans doublon dans la liste générique des actions. Une ouverture de lien ne change aucun état ; après connexion, la destination est conservée.
- [ ] Générer et relire la migration des réglages/livraisons ; demander confirmation avant exécution, puis contrôler le diff de schéma.
- [ ] Relancer les tests ciblés, `composer cs-check` et `composer stan`. Documenter la commande cron cPanel sans modifier la crontab ou envoyer de mail réel avant la recette et l'autorisation habituelles.

### Task 4: Recette intégrée de la V1

**Files:** Modify `jardin-sonore-backend/README.md`, `ROADMAP.md` et les traductions/maquettes si la recette révèle un écart ; test les suites ciblées des deux plans.

**Interfaces:** Aucune nouvelle interface ; valide le parcours complet depuis une demande jusqu'à la clôture manuelle après paiement.

- [ ] Jouer en environnement de test le scénario : demande du site → qualification et contact corrigé → note d'appel → action reportée → devis envoyé puis signé → facture émise → relance J+30 → paiement → clôture manuelle.
- [ ] Vérifier sur téléphone puis bureau les quatre écrans essentiels et les liens du courriel ; contrôler l'absence de défilement horizontal et la conservation d'une note pendant édition des coordonnées.
- [ ] Lancer `./bin/phpunit` sur les suites commerciales, `composer cs-check`, `composer stan`, `npm run lint` et `npm run build` pour le client modifié ; relever explicitement tout contrôle non exécuté.
- [ ] Mettre à jour la documentation d'exploitation et `ROADMAP.md`. Proposer les messages de commit conventional commit ; ne pas committer, taguer, pousser ou déployer sans accord explicite et sans les vérifications du dépôt.
