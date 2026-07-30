# Séances : rendu, médias et PDF canonique

## Objectif et périmètre

Faire d'une séance un document unique, toujours à jour et partageable ultérieurement avec une structure. Cette première étape couvre le rendu backoffice, les médias attachés aux séquences et la production locale du PDF canonique. L'accès structure Next.js et Google Drive ne font pas partie de cette livraison.

## Décisions produit

- Une séance n'a pas de versions métier : le HTML, le PDF local et le futur fichier Drive représentent toujours sa version courante.
- Toute modification d'une séance déclenche la régénération asynchrone de son PDF : création ou édition de la séance, ajout, édition, suppression, déplacement, changement de rôle et réordonnancement d'une séquence.
- Une séquence conserve une copie autonome de ses contenus et de ses médias. Modifier un élément de catalogue après son insertion ne modifie donc pas la séance. Aucun listener sur les catalogues n'est nécessaire.
- Le rendu HTML client masque uniquement les notes privées. Le matériel et les prolongements font partie du document client. Les paroles sont repliables en HTML et toujours développées dans le PDF.

## Modèle de médias

Les champs historiques `primaryUrl`, `secondaryUrl` et `imageUrl` d'une séquence sont remplacés par une collection ordonnée de médias copiés dans le JSON de la séance. Chaque média comprend :

- un libellé ;
- un type ;
- une URL ;
- une image optionnelle ;
- un booléen `featured`.
- un booléen `displayOnSession`.

Une séquence possède au plus un média mis en avant : il est rendu directement dans l'activité (lecteur compact si c'est une vidéo YouTube, sinon lien principal). Il est nécessairement visible dans la séance. Les autres médias peuvent être conservés pour l'usage interne ou cochés comme `displayOnSession` afin d'apparaître dans la liste de liens clients ; cela permet par exemple de garder une partition sans la partager. L'invariant métier garantit qu'il n'existe jamais plus d'un média mis en avant. La migration transforme l'URL principale en média mis en avant visible et l'URL secondaire en média complémentaire visible. L'ancienne image devient l'image du média mis en avant lorsqu'il existe, ou un média image complémentaire visible sinon.

## Rendu de lecture

Une projection de lecture dédiée fournit le même contenu aux pages HTML et au générateur PDF. La page de lecture devient un déroulé vertical sans action d'édition, reprenant les repères visuels du compositeur : titre, date, thème, matériel, prolongements, numéro et rôle de séquence, type, texte, gestes, médias visibles et paroles.

Le PDF utilise un template imprimable dédié. Il développe les paroles, inclut matériel et prolongements, exclut les seules notes privées et évite les interactions HTML. Il est stocké localement hors du répertoire public et exposé plus tard par une route autorisée.

## Cycle de vie du document

`SessionSummary` porte l'état du document, le chemin de son PDF local et la dernière erreur :

- `pending` après une sauvegarde ;
- `generating` pendant le traitement ;
- `ready` quand le PDF courant est disponible ;
- `failed` en cas d'erreur, avec relance explicite depuis le backoffice.

Le repository de séances devient le point unique qui invalide le document et envoie un message Messenger dédié après persistance. Cette centralisation garantit qu'un nouveau cas d'usage qui sauvegarde une séance est couvert sans ajout manuel dans chaque contrôleur. Le handler charge la séance, génère le PDF avec un moteur PHP autonome, écrit le fichier localement et met l'état à jour. Les échecs sont journalisés et ne bloquent jamais l'édition.

Le message ne contient que l'UUID de la séance. Les traitements redondants restent sûrs : le handler relit la séance au moment de générer et produit uniquement la dernière version sauvegardée.

Les étapes de planification, de début, de succès, d'échec et de relance sont tracées dans un canal Monolog `session_document`, avec l'UUID de séance et le chemin du fichier lorsqu'il existe. Le handler reçoit ce logger explicitement par l'attribut Symfony `#[AutowireLogger(channel: 'session_document')]`; la configuration suit le modèle du canal `mailing_delivery` avec un fichier rotatif distinct.

## Interfaces et migration

- Les objets de domaine, inputs, views et formulaires de séquence portent la collection de médias à la place des trois champs historiques.
- La migration Doctrine ajoute les métadonnées du document à `session_summary` et transforme les payloads JSON existants sans perdre les URLs ou images.
- Le backoffice affiche l'état documentaire et une action de relance si l'état est `failed`.
- Une dépendance PDF PHP compatible PHP 8.4 est ajoutée via Composer ; son choix précis est validé au moment de l'implémentation en fonction de sa compatibilité avec le déploiement Docker existant.

## Gestion des erreurs

- Une erreur de génération passe l'état à `failed`, conserve l'erreur technique pour le support et laisse la séance modifiable.
- Si le fichier local manque alors que l'état est `ready`, le téléchargement répond de façon sûre et l'état repasse à régénérer.
- Les URLs de médias restent rendues comme des liens externes sûrs ; seul YouTube reçoit un traitement d'intégration, après validation de son identifiant.

## Plan de tests

### Automatisés

- Tests unitaires du modèle de médias : normalisation, ordre, unicité du média mis en avant et filtrage des liens visibles dans la séance.
- Tests de compatibilité de `SessionSequence::fromArray()` avec les anciens payloads, puis de sérialisation avec les nouveaux médias.
- Tests du cycle documentaire : une sauvegarde invalide l'ancien document et publie un message ; le handler passe à `ready` ou `failed` selon le résultat du générateur.
- Tests des mutations de séance : édition générale, ajout, édition, suppression, rôle et réordonnancement rendent bien le document obsolète.
- Tests de la projection HTML/PDF : les paroles sont présentes dans le PDF, repliables côté HTML ; les notes privées sont absentes tandis que matériel et prolongements sont présents ; les médias internes ne sont pas exposés.
- Test de migration sur un enregistrement contenant une URL principale, une secondaire et une image.

### Vérifications techniques

- `composer cs-check`
- `composer stan`
- Tests Symfony ajoutés pour le domaine, les cas d'usage et Messenger.
- `php bin/console doctrine:migrations:diff --allow-empty-diff` : le schéma obtenu ne doit contenir aucun écart hors périmètre.

### Recette manuelle

- Créer une séance, ajouter plusieurs séquences et vérifier le rendu vertical HTML.
- Vérifier qu'un média YouTube mis en avant est intégré, que les autres médias restent des liens et que les paroles se replient correctement.
- Modifier successivement chaque élément de la séance et constater l'état « mise à jour en cours », puis le PDF actualisé.
- Simuler une erreur de générateur, vérifier l'état d'erreur et l'action de relance ; contrôler que l'édition reste possible.
- Vérifier dans le journal `session_document` les traces de planification, de génération, de succès et d'échec avec l'UUID de la séance.
- Ouvrir et imprimer le PDF : ordre des séquences, paroles complètes, absence de notes privées, mise en page lisible.
- Modifier ensuite la fiche catalogue source et confirmer que la séquence déjà insérée reste inchangée.

## Hors périmètre

- Association à `Organization` et authentification des accès structure.
- Espace `/espace` dans Next.js.
- Synchronisation Google Drive.
- Versionnage métier des séances ou de leurs PDF.

## État de la première tranche

La prévisualisation verticale, la migration des médias, le cycle documentaire Messenger, le PDF local et le journal `session_document` sont implémentés. La prochaine reprise porte sur la recette complète de génération PDF et la finition ergonomique de l’édition de collections de médias.
