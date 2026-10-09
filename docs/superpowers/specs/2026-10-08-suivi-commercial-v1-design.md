# Suivi commercial — conception de la V1

Date : 8 octobre 2026. Cette conception reprend le parcours validé en discussion. Elle doit encore être relue par l'utilisateur avant le plan d'implémentation. Les échanges plus larges et les lots futurs restent dans les [notes de cadrage](../../suivi-commercial-cadrage-en-cours.md) ; l'état des priorités reste dans [ROADMAP.md](../../../ROADMAP.md).

## But et critères de réussite

Le Jardin Sonore reçoit surtout ses demandes par courriel, répond dans Gmail et utilise Google Drive pour les devis. Le backoffice doit devenir l'endroit où retrouver, pendant un appel ou au début de la journée, le contexte d'un projet, les personnes à joindre et la prochaine action. Il ne doit pas imposer de recopier les conversations, les PDF ou les séances de Google Calendar.

La V1 est utilisable si une demande du site ne se perd pas, si une demande téléphonique ou reçue directement par courriel peut être saisie rapidement, si un dossier montre son historique et ses contacts, et si les actions et factures impayées réapparaissent dans le backoffice et le courriel de gestion. L'utilisateur travaille seul.

## Périmètre et sources de vérité

| Objet | Source de vérité en V1 |
| --- | --- |
| Courriels et conversations | Gmail, avec lien de conversation facultatif dans le dossier si sa forme fonctionne avec les comptes réels |
| Demandes, dossiers, notes, actions, état des devis et des factures | Backoffice Symfony |
| Coordonnées des structures et personnes | Annuaire Symfony existant, réutilisé et corrigé après validation humaine |
| Fichiers de devis et factures | Drive ou l'outil d'émission actuel ; le backoffice garde leurs références et montants |
| Séances et disponibilités | Google Calendar uniquement, sans ressaisie dans le backoffice en V1 |

La V1 n'intègre ni la synchronisation de l'agenda, ni la vue hebdomadaire des séances, ni le déclenchement automatique d'une facture à préparer après la dernière séance du mois. La génération de devis, le dépôt de PDF signé, la synchronisation Drive, la recherche géographique des anciens tarifs et les suggestions de dates appartiennent à des lots ultérieurs. Une action « Préparer la facture » peut toujours être ajoutée manuellement.

## Demande et qualification

Une soumission valide du formulaire public crée une demande « À qualifier » avec les champs reçus, leur texte d'origine, le canal et la date de réception. Le courriel de contact actuel continue d'arriver. Une demande saisie depuis le backoffice pour un appel, un courriel direct ou une recommandation entre dans la même file. Pour cette saisie manuelle, l'utilisateur choisit ou crée d'abord la structure, puis sélectionne une personne active de cette structure, en crée une avec son prénom, nom et fonction, ou indique qu'elle n'est pas encore identifiée. Le formulaire n'exige pas de nom d'interlocuteur dans ce dernier cas.

Le formulaire manuel n'affiche que le champ de personne correspondant au mode choisi ; il ne répète pas le nom de la personne, le nom de la structure ni la ville dans les détails de la demande. La ville vient de la commune reliée à l'adresse de la structure. L'e-mail et le téléphone reçus pendant ce premier échange sont conservés facultativement sur la demande comme contexte ; leur saisie ne modifie pas automatiquement les coordonnées de l'annuaire. Aucun rapprochement ou changement de l'annuaire ne se déduit d'une ressemblance de nom, d'adresse ou de courriel. Le texte du premier échange reste le résumé central de la demande.

Une demande qualifiée est liée à un ou plusieurs dossiers de projet. Le cas ordinaire est une demande pour un dossier ; un même message initial peut exceptionnellement donner lieu à deux projets distincts sans recopier son contenu. Une demande sans débouché est classée « Sans suite » et reste consultable. Une réponse envoyée depuis Gmail ne fait pas sortir automatiquement la demande de « À qualifier ».

Le cas rare d'une nouvelle demande du formulaire concernant un dossier déjà ouvert n'a pas de rattachement dédié en V1. L'utilisateur la sort de la file en la classant comme doublon/sans suite et ajoute manuellement au dossier existant la note ou l'action utile. Les nouveaux courriels qui arrivent directement dans Gmail au sujet d'un dossier ouvert n'entrent pas dans la file : l'utilisateur n'en note que ce qui mérite un suivi.

Le formulaire public passe déjà par Next.js et ALTCHA. Après validation, la création métier est confiée à un point d'entrée backend réservé au serveur Next.js. Le backend conserve la demande et une notification à distribuer de façon durable avant de confirmer la soumission ; un échec SMTP ultérieur laisse la demande visible et permet la reprise de la notification. Le destinataire, le contenu utile et l'adresse de réponse du courriel actuel sont préservés. Les reprises d'une même soumission ne doivent pas créer deux demandes ni deux notifications. La réponse publique ne doit pas annoncer un succès si aucune demande n'a été conservée.

## Dossier, contacts et historique

Le dossier correspond à un objectif cohérent et est rattaché à la structure qui porte la décision ou le devis : par exemple la mairie, même si les interventions concernent plusieurs crèches. Il ne correspond ni à un devis ni nécessairement à une année civile. Une prestation indépendante demandée plus tard donne un nouveau dossier depuis la structure, sans réutiliser artificiellement la demande d'origine.

Le dossier montre sans navigation supplémentaire les coordonnées de la structure et des personnes liées. Il permet de corriger rapidement ces coordonnées pendant un appel. Il accepte plusieurs personnes, avec un interlocuteur principal facultatif. Une action de contact propose cet interlocuteur par défaut, mais permet d'en choisir un autre ou aucun. Les contacts liés à l'histoire du dossier restent visibles même après leur départ ; un contact marqué comme ancien n'est plus proposé pour de nouvelles actions. Les références historiques ne sont jamais effacées par un simple retrait de la liste des interlocuteurs actuels. Les règles globales de suppression définitive dans l'annuaire feront l'objet d'un audit ciblé avant implémentation ; l'indicateur `active` existant sert aussi aux audiences de mailing et ne doit pas être réutilisé à l'aveugle comme soft delete.

L'historique est chronologique. Une note libre peut être créée pendant un appel, avec type d'échange et interlocuteur facultatifs ; une nouvelle action peut être créée dans le même geste. Les événements connus de l'application y sont inscrits automatiquement, notamment qualification, envoi ou signature d'un devis, report d'une action, changement d'état du dossier, création ou paiement d'une facture. Les courriels ne sont pas copiés dans cet historique. Un lien Gmail vers une conversation est facultatif et sera retenu seulement après vérification du comportement multi-compte réel.

## États des dossiers et actions

Un dossier commence « En discussion ». La réception marquée d'un devis signé le passe à « Confirmé ». Plusieurs devis peuvent coexister dans un dossier : le premier signé peut confirmer le projet tandis que le suivant attend encore une réponse.

Un dossier « En discussion » qui n'aboutit pas est classé manuellement « Sans suite » ; son historique reste accessible. Avant ce classement, l'interface signale les actions encore ouvertes et les annule en conservant leur trace. Un dossier « Confirmé » n'est proposé « Prêt à clôturer » que lorsque ses actions ouvertes sont traitées et toutes les factures enregistrées sont payées. L'utilisateur vérifie lui-même que les séances et obligations non connues de la V1 sont achevées, puis valide manuellement le passage à « Terminé ». Une facture enregistrée impayée empêche cette clôture. Terminer la dernière action ne change jamais l'état du dossier.

Une action possède un titre, une date d'échéance, des détails facultatifs et éventuellement un interlocuteur. Ses états utiles sont « À faire », « Terminée » et « Annulée ». L'échéance proposée pour une relance après appel, courriel ou devis est de sept jours calendaires, toujours modifiable à la création et ensuite. Si elle tombe le week-end, elle sera visible dans le courriel du lundi. Une action n'a pas de date supplémentaire de rappel anticipé.

Terminer une action peut enregistrer une note et proposer la suivante, sans l'imposer. Reporter une action garde automatiquement l'ancienne et la nouvelle échéance dans l'historique, sans exiger de justification. Si la dernière action d'un dossier actif est terminée, l'interface propose une nouvelle action ou « Sans suite » si le dossier est encore en discussion. L'utilisateur peut aussi ne choisir aucun des deux : le dossier reste actif et rejoint la section « Sans prochaine action ».

## Devis et factures suivis manuellement

Avant la génération de documents par le backoffice, un devis est enregistré lors de son envoi depuis Gmail, avec son numéro, son nom de fichier Drive exact, son montant total et sa date d'envoi. L'application propose alors une relance à sept jours, facultative et modifiable. Un devis non envoyé reste suivi par une action « Préparer/envoyer le devis » sans fiche brouillon. Au retour, l'utilisateur marque le devis signé ; le PDF reste dans Drive en V1.

Un devis déjà envoyé ne se modifie pas. Une correction exige un nouveau numéro et une nouvelle fiche ; celle-ci peut référencer le devis remplacé. L'ancien reste dans l'historique. La numérotation actuelle `AAAA-MM - dN` est saisie et contrôlée en V1, sans tentative de déduire automatiquement le prochain numéro des fichiers Drive. Un même projet peut porter plusieurs devis, y compris sur deux années civiles.

La facture continue d'être émise hors du backoffice, aujourd'hui depuis le classeur Google et plus tard via Solo. La fiche de suivi contient au minimum son numéro, son montant, sa date d'émission, le dossier concerné et son état de paiement. L'utilisateur constate le virement sur son compte bancaire et marque explicitement la facture payée dans le backoffice. Le lien du courriel de gestion ouvre la fiche ou la liste, jamais une action GET qui change l'état de paiement.

Une facture encore impayée trente jours calendaires après sa date d'émission produit une seule action interne de relance. Si elle est déjà échue au moment de son enregistrement, l'action apparaît immédiatement. Le report ou l'annulation de cette action n'en crée pas une deuxième ; le paiement retire toute relance encore « À faire » en conservant son historique. Chaque facture reste indépendante : une facture nouvelle n'augmente pas son total du montant impayé d'une précédente. Le paiement partiel et l'acompte ne constituent pas un parcours V1.

## Point d'entrée et courriel de gestion

Le suivi commercial prend place dans le backoffice métier Symfony existant, avec ses propres pages de travail, et non dans le portail public. Une page « Suivi clients » sert d'entrée quotidienne sur mobile et ordinateur. Elle montre les demandes « À qualifier », les actions en retard, dues aujourd'hui et prochaines, les factures impayées et leurs relances, ainsi que les dossiers actifs sans prochaine action. Chaque ligne expose le titre, la date et le contexte utiles ; ouvrir une ligne affiche le détail. Terminer, reporter ou modifier une action reste rapide depuis la liste. Les écrans de dossier et de qualification gardent les coordonnées visibles pendant une prise de notes. L'interface suit les usages existants de Symfony UX, avec les composants interactifs utiles, sans imposer une grille d'agenda en V1.

Concevoir les écrans **mobile first** : hiérarchie, formulaires et actions rapides sont d'abord vérifiés sur une largeur de téléphone, puis adaptés au bureau. La liste d'actions, la qualification et la fiche dossier doivent rester utilisables sans défilement horizontal ; les coordonnées permettent d'appeler ou d'écrire directement ; la saisie d'une note pendant un appel ne doit pas être perdue lors d'une édition de contact. Le bureau peut afficher davantage d'informations côte à côte, sans devenir le modèle dont on réduit ensuite les colonnes.

Avant l'implémentation des écrans, demander à Stitch des maquettes simples pour l'accueil du suivi, la qualification et la fiche dossier avec leur liste d'actions. Les relire sur mobile et sur bureau pour valider l'ordre des informations et les gestes courants. Les maquettes doivent reprendre l'identité visuelle du backoffice métier existant et guider une interface fonctionnelle et cohérente ; leur but n'est pas une refonte esthétique de cette partie. Les décisions de parcours et de contenu validées dans cette conception restent la référence si une maquette suggère autre chose.

Un seul courriel personnel de gestion est prévu par jour admissible, à l'heure configurée, initialement 8 h en Europe/Paris, du lundi au vendredi. Le réglage de l'heure et le bouton de pause/reprise se trouvent dans le suivi clients. La pause arrête uniquement ce courriel personnel ; elle ne modifie ni les échéances ni les courriels des clients, du portail ou des campagnes. À la reprise, les éléments non traités réapparaissent.

En V1, le courriel comprend les demandes « À qualifier » avec leur ancienneté et un lien, les actions dues ou en retard et les factures à relancer. Le lundi, il ajoute les dossiers actifs sans prochaine action. Il ne mentionne pas les séances, car elles restent uniquement dans Google. Aucun courriel n'est envoyé si ces sections sont toutes vides. Une date et un envoi déjà traités ne doivent pas être renvoyés par une seconde exécution du cron. Les liens ouvrent les pages authentifiées appropriées et conservent leur destination après connexion.

Le projet utilise déjà des crons cPanel réguliers et Messenger. La V1 peut s'appuyer sur un distributeur périodique qui lit l'heure et la pause enregistrées dans le backoffice, vérifie le jour local et l'unicité de l'envoi, puis programme le courriel avec l'infrastructure existante. Le réglage exposé à l'utilisateur est une heure, pas une expression cron libre : cela permet de déplacer l'envoi sans modifier la crontab et maintient la règle lundi–vendredi.

## Fiabilité, accès et vérification

Les pages de gestion et les actions de modification sont réservées au compte administrateur métier et protégées comme les écrans internes existants. Les liens du courriel n'effectuent aucune mutation à l'ouverture. Les données d'origine d'une demande, les notes, reports, changements d'état et références financières gardent des dates et une trace consultable. Les créations répétées du formulaire, du distributeur de courriel et de la relance de facture sont idempotentes.

La vérification avant livraison doit couvrir au moins : réception d'une demande du site avec une seule notification même si une tentative est rejouée ; préservation d'une demande et reprise de la notification quand SMTP échoue ; saisie manuelle avec choix ou création explicite de structure/personne, mode personne inconnue et coordonnées facultatives sans doublons ; état et clôture manuelle des dossiers ; historique et reports d'action ; devis remplacé et devis multiples ; relance unique à trente jours et annulation après paiement ; courriel du lundi, week-end, heure modifiable, pause, absence d'envoi vide et exécution répétée ; accès administrateur et absence de mutation par lien GET. Une recette sur téléphone puis sur ordinateur vérifie la prise de notes pendant un appel, le traitement rapide de la liste, les coordonnées actionnables et l'absence de défilement horizontal.

## Suite après la V1

Le lot agenda connectera le compte Google personnel propriétaire de « Professionnel Musique ». Le backoffice et Google pourront modifier les dates ; la confirmation d'une séance restera une action explicite dans le backoffice. Les événements liés au dossier serviront à l'aperçu hebdomadaire et aux actions « facture du mois à préparer » ; les autres événements compteront seulement pour les conflits. Une suppression dans Google annulera la séance tout en gardant sa trace. Les modifications Google seront reprises à l'ouverture du dossier et par synchronisation régulière, avec signalement des erreurs.

Les lots documentaires ajouteront ensuite le PDF signé et sa synchronisation Drive, puis la génération de devis à partir du vrai canevas éditable. Le lien entre numéro, document généré, PDF envoyé et corrections sera détaillé à ce moment. La recherche géographique des tarifs et les suggestions automatiques de dates restent des améliorations distinctes.
