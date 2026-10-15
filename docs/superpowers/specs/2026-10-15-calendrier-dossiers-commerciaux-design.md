# Calendrier dans les dossiers commerciaux — spécification

Date : 15 octobre 2026. État : conception à relire avant le code. La [roadmap](../../../ROADMAP.md) porte la priorité courante ; le [plan initial](../plans/2026-10-15-calendrier-dossiers-commerciaux.md) décrit les lots envisagés.

## Intention et résultat attendu

Depuis un dossier, l'utilisateur voit les séances prévues, leurs dates, leur lieu et leur état, puis peut les rattacher, les créer, les corriger ou les annuler. Le fil reste accessible mais prend moins de place. Le courriel personnel des jours ouvrés résume les séances de cette semaine et de la suivante. Après la dernière séance confirmée et passée d'un mois, une action rappelle de préparer la facture du dossier.

Une séance n'entre dans ce suivi qu'après son rattachement explicite au dossier. Google Calendar « Professionnel Musique » fait référence pour les dates et heures ; le backoffice conserve le lien au dossier, l'état métier et le suivi de facturation. La première version n'ajoute ni agenda complet ni émission automatique de facture.

## Choix d'architecture

Trois approches ont été envisagées. Une simple recopie manuelle des dates serait rapide mais laisserait diverger le dossier et Google Calendar. Intégrer seulement la vue Google dans la fiche montrerait les événements, sans lien fiable avec les dossiers ni rappel de facturation. Le choix retenu est de conserver dans Symfony une fiche d'intervention pour chaque événement Google explicitement lié : elle permet la synchronisation, les états métier et les rappels sans copier tout l'agenda.

Le backend possède déjà les dossiers, les actions, leur fil et le courriel personnel. Il n'a pas de connexion Google Calendar. L'intégration ajoute un accès OAuth administrateur, un adaptateur Calendar, un modèle d'intervention et une synchronisation ciblée. Les événements professionnels non liés peuvent signaler un conflit d'horaire ; ils n'apparaissent pas dans les séances du dossier et ne déclenchent aucune facturation.

## Connexion et contrôle d'accès

Le propriétaire du compte Google autorise le backend à accéder au calendrier « Professionnel Musique ». L'application demande le droit le plus étroit permettant de lire, créer et modifier les événements de ce calendrier ; le choix exact entre les scopes d'événements du propriétaire et les scopes plus larges dépendra du compte et des calendriers à consulter. Le jeton de renouvellement est chiffré au repos avec une clé locale, jamais versionnée. L'interface administrateur montre le compte connecté, le calendrier retenu, la date du dernier accès réussi et une commande de déconnexion.

Les pages et mutations du calendrier exigent les mêmes droits que le dossier commercial. Les formulaires de mutation suivent la protection CSRF existante. Une connexion absente ou expirée est visible sur la fiche ; elle empêche création et modification depuis le backoffice jusqu'à reconnexion. Le MCP Mistral envisagé plus tard n'intervient pas dans cette autorisation.

Les identifiants OAuth, la clé de chiffrement et les autres valeurs sensibles restent dans la configuration locale du serveur, jamais dans Git. Au déploiement, vérifier explicitement la présence des nouvelles variables sur cPanel et leurs droits d'accès avant d'activer la connexion Google. Le script de déploiement exclut les fichiers .env.local du transfert : leur contenu ne se propage pas automatiquement depuis le poste de développement.

## Parcours dans le dossier

1. **Rattacher une séance existante.** Rechercher les événements du calendrier sur une période choisie, voir titre, dates et lieu, puis choisir explicitement un événement et le dossier. Un événement déjà lié à un autre dossier est signalé et ne peut pas être lié deux fois.
2. **Créer une séance.** Saisir titre, début, fin, lieu réel et état « option » ou « confirmée ». La création Google réussit avant que le dossier affiche la séance comme enregistrée. L'événement créé depuis le dossier est lié automatiquement.
3. **Modifier une séance.** Corriger début, fin, titre ou lieu depuis le dossier. Le changement part immédiatement vers Google, puis la version locale est rafraîchie. Une modification dans Google est récupérée à l'ouverture du dossier et par le rafraîchissement périodique.
4. **Confirmer ou annuler.** La confirmation est une commande explicite du backoffice ; le retrait des « ??? » dans Google ne la déclenche pas. Annuler depuis le dossier demande confirmation et supprime l'événement Google, ou seulement l'occurrence concernée. La séance annulée reste visible dans l'historique du dossier et ne compte plus dans les prévisions ni dans la facturation. Retirer seulement le lien au dossier conserve l'événement Google et arrête son suivi commercial.
5. **Dates alternatives.** Plusieurs séances peuvent toutes avoir lieu, ou représenter des options pour une même prestation. Dans le second cas, l'utilisateur peut les regrouper comme alternatives ; confirmer l'une propose de libérer les autres, sans suppression silencieuse.

Le lieu de la séance est saisi pour l'intervention réelle et peut différer de l'adresse de la structure. Une séance suivie doit avoir un début et une fin horodatés ; un événement Google « journée entière » peut être repéré dans la recherche, mais demande une heure avant rattachement comme intervention.

## États, données et fil

Une intervention distincte du fil conserve le dossier, l'identifiant du calendrier, l'identifiant Google de l'événement, la date et l'heure de début et de fin, le fuseau, le titre, le lieu, l'état métier (option, confirmée, annulée), le groupe d'alternatives éventuel, la version Google, les dates de synchronisation et l'erreur éventuelle. Le couple calendrier/événement est unique. Pour une occurrence récurrente, conserver aussi l'identifiant de série et l'heure de début d'origine de l'occurrence, afin de reconnaître une occurrence déplacée.

Le fil du dossier enregistre les faits utiles, notamment une annulation et une précision rédigée par l'utilisateur. Chaque création ou rafraîchissement technique de séance n'ajoute pas une ligne au fil. Le dossier fermé conserve ses séances liées ; sa clôture reste manuelle. La suppression d'un dossier retire les liens et données du backoffice après confirmation, sans supprimer les événements Google.

L'état « option » ou « confirmée » appartient au backoffice. Le titre Google peut en donner un repère visuel, mais aucune convention d'abréviations (??? / IPE / JS) n'est figée avant examen d'événements réels. Une édition du titre dans Google ne change pas l'état métier.

## Synchronisation et incidents

Une mutation demandée depuis le dossier est envoyée à Google avec la version connue de l'événement. Si Google indique qu'une autre modification est intervenue, le backoffice affiche la nouvelle version et demande à l'utilisateur de reprendre son changement ; il n'écrase pas l'édition concurrente. Le succès affiché exige aussi l'enregistrement local. Une création interrompue après l'appel Google doit être retrouvable par une clé de requête unique pour éviter un doublon au nouvel essai.

Les interventions liées sont relues régulièrement, à l'ouverture du dossier et avant la préparation du courriel du matin. Le premier rythme envisagé est un passage toutes les quinze minutes, à ajuster après mesure. Une séance déplacée ou renommée dans Google met à jour le dossier. Un événement annulé ou supprimé après une lecture Google réussie passe à « annulée » et garde sa trace. Une réponse incomplète, une erreur réseau, de quota ou d'autorisation laisse la dernière version connue avec un avertissement « synchronisation à vérifier » ; elle ne vaut jamais annulation.

Une occurrence récurrente est liée et modifiée individuellement, sans modifier la série entière depuis le dossier. La recherche par période affiche les occurrences, y compris leurs exceptions. Les événements non liés du calendrier « Professionnel Musique » servent à prévenir un chevauchement lors d'une création ou modification. Les autres calendriers personnels pourront fournir un indicateur libre/occupé après vérification des droits nécessaires ; leur contenu n'entre pas dans le suivi commercial.

## Présentation de la fiche

Sous les contacts et près des tâches, un bloc « Séances et dates » montre d'abord les séances à venir, puis les dates passées consultables. Chaque ligne affiche date, heure, lieu et état, avec un accès clair à la correction ; les options sont visuellement distinctes. Les commandes sont des boutons explicites avec icônes compréhensibles et libellés accessibles. La dernière synchronisation et son éventuelle erreur sont visibles près des dates. Le fil devient repliable par défaut lorsqu'une séance est liée, tout en restant ouvrable.

Un dossier sans séance affiche une invitation courte à créer ou rattacher un événement. La fiche reste utilisable sur téléphone ; aucune vue agenda globale ni manipulation par glisser-déposer n'est requise pour ce lot.

## Courriel personnel

Le courriel ouvré existant ajoute les interventions liées du jour courant jusqu'au dimanche de la semaine suivante, en heure de Paris. Deux groupes « Cette semaine » et « Semaine prochaine » donnent, sur une ligne par séance, la date, l'heure, le dossier, le lieu si utile, l'état « option » ou « confirmée » et un lien vers le dossier. Les séances annulées sont absentes. Le courriel part aussi lorsque ces séances sont son seul contenu.

La préparation du courriel demande un rafraîchissement Calendar. Si celui-ci échoue, le message indique que les dates affichées peuvent être anciennes ; il ne présente pas des données incertaines comme confirmées par Google. Le matin de la dernière séance confirmée du mois, le courriel peut signaler « facture à prévoir après cette séance » sans créer l'action avant la fin réelle de l'horaire.

## Préparation de la facture

Le regroupement est **un dossier et un mois de prestations en heure de Paris**, même si les séances ont lieu dans plusieurs établissements. Une option non confirmée ne déclenche pas l'action. Une séance confirmée est considérée passée dès la fin de son horaire, sans validation manuelle supplémentaire. Après la fin de la dernière séance confirmée de ce mois, si la synchronisation est fiable et qu'aucune facture de ce mois n'est enregistrée, le système crée une seule action « Préparer la facture ». Son échéance est le jour de cette dernière séance. L'action est une proposition de travail : elle n'émet aucune facture. L'automatisation commence au mois d'activation et ne crée pas rétroactivement des rappels pour les anciens mois.

Une association explicite entre dossier, mois de prestations et action garantit l'unicité et conserve la décision de terminer ou d'annuler l'action. Si une séance est déplacée ou ajoutée avant facturation, une action encore ouverte est recalculée ; une action terminée ou annulée volontairement ne réapparaît pas silencieusement. Le dossier signale alors le changement pour décision manuelle. Si les données Google sont incertaines, la création automatique attend une synchronisation réussie.

La facture suivie dans le backoffice porte le mois de prestations concerné, distinct de sa date d'émission : une facture émise en novembre peut ainsi terminer l'action d'octobre. Les anciennes factures sans mois restent valides et ne sont pas associées automatiquement. Une facture complémentaire pour une séance ajoutée après émission reste une décision manuelle. Le rappel actuel de facture impayée trente jours après émission continue de suivre chaque facture séparément.

## Limites du lot et critères de réussite

Le lot ne crée pas de devis, n'envoie pas de facture, ne déduit pas l'état confirmé du titre Google et ne propose pas automatiquement des dates libres. La connexion Mistral et son MCP sont un chantier séparé.

La conception sera prête à implémenter lorsque les situations suivantes ont une réponse observable :

- Une séance Google existante n'apparaît dans un dossier qu'après rattachement explicite, sans doublon possible.
- Une date changée dans le dossier arrive dans Google ; une date changée dans Google arrive dans le dossier. Un conflit de versions demande une nouvelle lecture au lieu d'écraser.
- Une annulation Google confirmée demeure dans l'historique ; une panne Google affiche une synchronisation incertaine sans annuler ni facturer.
- Les options et dates alternatives restent visibles ; seule une séance confirmée et passée peut lancer le rappel de facture.
- Le mail ouvré montre cette semaine et la suivante, même sans autre tâche, et signale les données anciennes.
- Un seul rappel de préparation existe par dossier et mois de prestations ; enregistrer la facture correspondante le termine même si son émission a lieu le mois suivant.

## Vérifications avant implémentation

Confirmer l'identifiant exact du calendrier, le compte Google propriétaire et l'URL de retour OAuth de production. Examiner quelques événements anonymisés : séance simple, option « ??? », occurrence récurrente, date déplacée, lieu distinct de la structure et éventuel événement « journée entière ». Vérifier le mode de publication OAuth, les scopes disponibles pour ce compte et l'extension de chiffrement en production. Ces informations affinent la convention de titre, le traitement des occurrences et le périmètre des conflits avec les autres calendriers ; aucun secret ne doit être placé dans le dépôt.

Références techniques officielles : [autorisations Calendar](https://developers.google.com/workspace/calendar/api/auth), [OAuth web](https://developers.google.com/identity/protocols/oauth2/web-server), [événements](https://developers.google.com/workspace/calendar/api/v3/reference/events), [occurrences récurrentes](https://developers.google.com/workspace/calendar/api/guides/recurringevents), [versions et If-Match](https://developers.google.com/calendar/api/guides/version-resources).
