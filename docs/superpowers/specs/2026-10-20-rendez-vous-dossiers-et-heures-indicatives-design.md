# Rendez-vous de dossier et heures indicatives — conception

Date : 20 octobre 2026. La [roadmap](../../../ROADMAP.md) reste la source des priorités et de l'état livré. Cette conception décrit le premier lot validé en discussion ; elle ne décrit pas une fonctionnalité déjà déployée.

## Intention et périmètre

Un dossier doit distinguer une action à faire d'un rendez-vous réellement fixé. Une action garde sa date d'échéance et peut indiquer une heure souhaitée, par exemple « vers 14 h », sans occuper l'agenda. Un rendez-vous occupe un créneau dans Google Calendar, apparaît dans le fil du dossier et dans le rapport personnel du matin, puis peut recevoir un court compte rendu. Les rendez-vous ne sont jamais des séances et ne déclenchent ni statistiques d'intervention ni préparation de facture.

Le premier lot porte sur les dossiers commerciaux. Les rappels généraux sans dossier, leur récurrence et un calendrier dédié aux mailings restent à réévaluer après usage. Aucun rendez-vous ne crée d'invitation, d'email ou de notification au contact : le créneau concerne uniquement le propriétaire du calendrier. Le premier lot ne gère pas les séries de rendez-vous récurrents.

## Choix retenu

Trois approches ont été examinées : mettre une heure sur toutes les tâches, enregistrer des rendez-vous uniquement dans le backoffice, ou rattacher un type de rendez-vous distinct à Google Calendar. La première ne réserve aucun créneau et confond échéance et horaire convenu. La deuxième crée deux agendas à maintenir. Le choix retenu est un rendez-vous de dossier lié à un événement Google, avec une heure indicative facultative sur les tâches pour les appels non convenus.

Google Calendar fait référence pour le titre, le début, la fin, le fuseau et le lieu d'un rendez-vous. Le backend conserve le dossier lié, le type « rendez-vous », son état, sa version de synchronisation et son compte rendu. Le préfixe lisible `RDV` est ajouté au titre Google lors de la création, mais il ne constitue pas à lui seul l'identité métier d'un événement déjà lié : retirer ou changer ce préfixe dans Google ne transforme pas le rendez-vous en séance.

## Tâches avec heure indicative

Une tâche de dossier conserve une date obligatoire et gagne une heure locale facultative, interprétée en `Europe/Paris`. Cette heure n'est ni une échéance à la minute ni une réservation dans Google Calendar. La liste des tâches, l'index si la tâche y est affichée, le contexte MCP et le rapport du matin montrent « vers 14 h » lorsqu'elle existe, dans un style secondaire qui reste compréhensible sans la couleur. Les tâches du même jour peuvent être ordonnées par heure connue, sans cacher celles qui n'en ont pas ; les tâches en retard restent visibles.

Création, édition et report permettent de renseigner, corriger ou retirer l'heure. Un report à une autre date conserve l'heure indicative tant que l'utilisateur ne la retire pas explicitement. Les tâches existantes n'ont pas d'heure ; leur affichage reste inchangé.

## Création et présentation des rendez-vous

Sur la fiche dossier, « Ajouter un rendez-vous » se trouve près d'« Ajouter une tâche ». Le formulaire demande un titre, un début et une fin ; le lieu est facultatif. Il crée l'événement dans le calendrier professionnel déjà connecté et enregistre son lien avec le dossier. La création depuis Google Calendar puis le rattachement manuel au dossier ne fait pas partie de ce lot. Le formulaire n'ajoute aucun invité, même lorsque le dossier possède une adresse de contact.

Le fil montre les rendez-vous à venir en tête, sous une forme distincte des faits passés : icône calendrier, encadrement discret, date et heure lisibles, accès aux actions de modification et d'annulation. Son en-tête donne au moins le prochain rendez-vous à venir lorsque le fil est replié. Après leur horaire, les rendez-vous apparaissent dans la chronologie du fil à leur date de début. Le compte rendu se saisit et se corrige sur l'élément du rendez-vous ; il reste lié à celui-ci si l'événement est déplacé ou annulé. Le changement d'horaire actualise le même élément, sans créer une nouvelle entrée à chaque synchronisation.

Une annulation depuis le backoffice demande confirmation et supprime l'événement Google concerné. Un rendez-vous annulé demeure dans le fil, avec son compte rendu éventuel, et disparaît des rendez-vous à venir. Les changements et annulations significatifs restent identifiables dans l'historique du dossier sans produire un bruit de synchronisation périodique.

## Synchronisation et conflits

La synchronisation existante, toutes les quinze minutes et à l'ouverture du dossier, suit aussi les rendez-vous liés. Une modification du titre, de l'horaire ou du lieu dans Google actualise le dossier. Une suppression ou annulation confirmée par Google marque le rendez-vous « annulé » ; elle ne supprime ni son rattachement historique ni son compte rendu. Une erreur réseau, d'autorisation ou de quota conserve la dernière valeur connue, signale une synchronisation incertaine et ne vaut jamais annulation.

Avant une modification depuis le backoffice ou le MCP, le backend relit l'événement et compare sa version Google. En cas de changement concurrent, il présente l'état actuel et demande de reprendre la modification au lieu d'écraser silencieusement la version Google. Une création porte une clé de requête stable pour retrouver un événement créé si la réponse ou l'enregistrement local échoue après l'appel Google ; une répétition ne crée pas de second rendez-vous. Le même couple calendrier/identifiant d'événement ne peut appartenir à deux rendez-vous.

Les événements Google libres dont le titre commence par le préfixe `RDV` sont exclus de l'écran de rattachement des séances, de ses compteurs et de l'action de rattachement côté serveur. Les rendez-vous déjà liés sont également exclus par leur identifiant Google, même si leur titre a changé. La vue calendrier distingue rendez-vous et séances ; les rendez-vous n'alimentent ni les statistiques de séances ni la facturation. Les autres événements libres conservent le comportement actuel de l'écran de rattachement et de son compteur « non liés ».

## Rapport du matin

Le rapport personnel affiche les rendez-vous non annulés du jour, triés par heure, dans une rubrique « Rendez-vous » distincte des séances et des tâches. Chaque ligne indique l'heure, le titre, le dossier, le lieu éventuel et un lien vers la fiche. La rubrique est présente même lorsque le rapport ne contient aucune autre action. Les tâches dues continuent d'apparaître dans « À faire » ; leur heure facultative s'affiche avec « vers ».

La préparation du rapport utilise la dernière synchronisation disponible et tente un rafraîchissement avant l'envoi, suivant le principe déjà employé pour les séances. Si Google est indisponible, le rapport indique que les horaires de rendez-vous peuvent être anciens au lieu de les présenter comme vérifiés. Un rendez-vous annulé confirmé n'apparaît plus dans le rapport.

## Accès MCP Mistral

La recherche de dossiers et `get_project_context` permettent d'identifier le bon dossier et d'en lire les tâches et rendez-vous, y compris l'heure indicative et les comptes rendus utiles. Un outil de lecture d'un rendez-vous exact fournit sa version courante avant modification. Les outils d'écriture permettent de créer, déplacer ou modifier un rendez-vous, l'annuler et ajouter ou corriger son compte rendu ; ils permettent aussi de créer, modifier et terminer une tâche de dossier, avec heure facultative. L'outil existant de rappel fondé sur un mail conserve sa vérification du mail source et peut recevoir cette heure facultative. Une demande directe de tâche ou de rendez-vous ne requiert pas de mail source artificiel.

Chaque écriture MCP cible un identifiant de dossier et, pour une modification, un identifiant de tâche ou de rendez-vous exact ; aucune recherche approximative ne choisit le dossier à la place de l'utilisateur. Chaque opération demande confirmation dans Mistral. Les modifications de rendez-vous vérifient la version lue ; les créations sont idempotentes sur une clé de requête stable. Les écritures disposent d'un interrupteur serveur désactivé par défaut, activé après contrôle des confirmations côté client. Le MCP ne peut ni inviter un contact, ni envoyer un mail, ni créer une séance à travers ces outils.

## Vérifications et critères de réussite

- Une tâche sans heure continue de fonctionner ; une tâche avec heure indique « vers HH:mm » dans le dossier, le rapport et le contexte MCP, sans créer d'événement Google.
- Un rendez-vous créé depuis le dossier apparaît une fois dans Google, dans le fil et dans le rapport du jour ; aucun invité n'est ajouté. Un second appel avec la même clé ne crée pas de doublon.
- Une modification dans Google est visible dans le dossier et dans le prochain rapport ; un conflit de version côté backoffice ou MCP n'écrase pas Google.
- Une suppression dans Google conserve le rendez-vous annulé et son compte rendu dans le fil, mais le retire du rapport.
- Un événement `RDV` libre ne peut pas être sélectionné ni forcé dans le rattachement des séances. Un rendez-vous lié reste classé comme tel après renommage dans Google.
- Les compteurs de séances et la préparation des factures ne changent pas lorsqu'un rendez-vous est créé, déplacé ou annulé.
- Une panne Google est visible et ne transforme pas un rendez-vous en annulation.
- Les outils MCP refusent une écriture désactivée ou sans identifiant exact ; les confirmations et les reprises après erreur sont vérifiées dans Mistral avant activation en production.

Le lot nécessite une migration pour l'heure facultative des tâches et les rendez-vous liés. Avant livraison, vérifier le SQL, le `down()`, l'absence de diff Doctrine hors sujet et l'exécution sur la base locale, puis faire la recette authentifiée du dossier sur ordinateur et téléphone et la recette MCP réelle sans invitation ni envoi.
