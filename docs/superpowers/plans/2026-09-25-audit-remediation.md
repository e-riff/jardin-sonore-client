# Suites du bilan technique backend et front

**Source :** [bilan du 25 septembre 2026](../../bilan-technique-2026-09-25.md). Ce plan décrit des lots futurs ; aucune tâche n'est lancée par sa rédaction.

## 1. Actions administrateur et sécurité du portail

- [ ] Convertir `sendInvitation` et `sendPasswordReset` en actions POST avec CSRF, puis remplacer leur retour basé sur `Referer` par une route interne.
- [ ] Tester GET, POST sans jeton, POST avec jeton invalide et POST valide ; vérifier qu'aucun e-mail ni jeton n'est créé sur les requêtes refusées.
- [ ] Examiner la configuration des proxies en production et tester la provenance de `x-forwarded-for` avant toute modification du rate limiting BFF/backend.

## 2. Contrats et cohérence fonctionnelle

- [ ] Décider d'une règle de mot de passe unique ; l'appliquer côté Symfony et afficher exactement la même règle dans Next.js. Couvrir l'API directe et le formulaire.
- [ ] Décider du traitement de l'avatar et du profil en cas de succès partiel ; adapter l'API ou le retour du formulaire et ajouter un test du cas où la seconde opération échoue.
- [ ] Définir les variantes de séquence réellement exposées par l'API, remplacer `Record<string, unknown>[]` par un contrat typé et tester la lecture des anciens contenus.

## 3. Refactors ciblés

- [ ] Mesurer les appels `/api/portal/me` par navigation ; dédupliquer à l'échelle d'une requête si le doublon est confirmé, puis centraliser seulement les traitements de statut API identiques.
- [ ] Extraire les parties stables des deux listes du portail sans fusionner leurs contenus ni leurs règles de détail.
- [ ] Extraire progressivement la préparation des formulaires et les conversions du contrôleur de séance ; préserver les routes et les cas d'usage.
- [ ] Déplacer les accès Doctrine des services `Application/Portal` et des modèles de formulaire concernés derrière des adapters/projections, conformément aux frontières documentées.
- [ ] Découper localement les composants interactifs les plus difficiles à relire ; ne mutualiser la vitrine qu'en présence d'un comportement réellement commun.

## 4. Outillage et Symfony 8.2

- [ ] Documenter une commande de test front qui exécute les tests `.ts` et `.tsx`, puis l'ajouter à la vérification habituelle.
- [ ] Après la sortie stable de Symfony 8.2, essayer `#[AsFormType]` et `#[FormField]` sur `RepertoireYoutubeVideoFormModel` ; comparer classes, lisibilité, tests et configuration avant toute migration plus large.

## 5. Vitrine et navigation du portail

- [ ] **Priorité haute :** préparer la page légale et les informations relatives aux données personnelles à partir des informations réelles de l'activité ; rendre les liens visibles dans le pied de page.
- [ ] **Priorité haute :** ajouter dans « En séance » une explication concrète du portail et de ses ressources, avec un accès clair. Prévoir des captures ou une vidéo seulement avec contenus fictifs ou autorisés.
- [ ] **Petit correctif, faible urgence :** stabiliser la largeur des liens du menu desktop du portail lorsque le point d'état actif apparaît ; vérifier les changements de rubrique et le focus clavier.
- [ ] **Priorité moyenne :** cadrer puis créer une page partenaires différenciant collègues, structures et projets ; obtenir les accords nécessaires pour noms, citations et visuels.
- [ ] **Priorité moyenne :** définir ce que l'accueil authentifié `/portail` apporte au-delà d'un choix de rubrique, puis décider où et comment afficher des messages aux structures avant de remplacer la redirection actuelle.
- [ ] **Priorité moyenne, coût élevé :** cadrer les messages temporaires publics éditables dans le back métier ; choisir leurs dates, destinataires et règles de publication avant de prévoir une éventuelle réutilisation pour le portail.
- [ ] **Plus tard :** étudier une page d'actualités avec sélection explicite des newsletters publiables et d'autres contenus originaux ; prévoir pages individuelles, métadonnées et sitemap public sans exposer les campagnes internes.

Chaque lot se termine par les tests ciblés, le lint ou PHPStan pertinents et une vérification des parcours concernés. La rédaction de ce plan ne modifie aucun schéma ; les fonctionnalités qui nécessiteraient une migration suivront la procédure du projet.
