# Notifications de séances et inscription à la newsletter

**Conception historique.** Les mentions de « reprise » et de « prochaine étape » ci-dessous décrivent les arrêts du 30 septembre et du 1er octobre, pas l'état actuel. Consulter [ROADMAP.md](../../../ROADMAP.md) pour les livraisons et priorités ; la [conception des disponibilités](2026-09-30-session-availability-notifications-design.md) précise le déclenchement finalement retenu.

Date : 30 septembre 2026. Les notifications et préférences ont été livrées sous `deploy-availability-newsletter-20260930-01`, les audiences et le contrôle du consentement sous `deploy-newsletter-audiences-consent-20261001-01`, puis la gestion des abonnés libres et l'inscription publique sous `deploy-newsletter-footer-20261001-01`. Les [plans notifications](../plans/2026-09-30-session-first-publication-notifications.md) et [newsletter](../plans/2026-09-30-newsletter-subscriptions-and-audiences.md) gardent les recettes de chaque étape.

## Objectif et périmètre

Prévenir les clients de la première publication d'une séance, permettre aux utilisateurs du portail de choisir leur abonnement newsletter et ouvrir une inscription publique depuis le footer.

Les notifications de séances et les campagnes newsletter ont des préférences, des déclencheurs et des traitements distincts. Les e-mails de service doivent présenter une identité Jardin Sonore cohérente et soignée, particulièrement la notification de nouvelle séance destinée aux clients.

La page légale était hors du périmètre de ce cadrage ; elle a été publiée ensuite. Ce cadrage ne modifie pas les données d'abonnement historiques.

## Décisions convenues

- Une notification est programmée à la première disponibilité de la séance pour une structure : rattachement d’une séance active ou activation d’une séance rattachée. Les éditions, republications et rattachements rétablis ne produisent pas de nouvel envoi ; une même séance/un même compte reçoit au plus une livraison.
- Le profil du portail propose deux cases indépendantes : nouvelles séances et newsletter.
- Les utilisateurs du portail abonnés à la newsletter sont sélectionnés quand au moins une de leurs structures appartient à l'audience de la campagne.
- Les abonnés libres constituent un groupe supplémentaire activable ou désactivable pour chaque campagne, indépendamment du ciblage géographique ou par structure.
- L'inscription publique commence par un petit formulaire dans le footer.
- L'adresse et l'état d'abonnement newsletter restent dans `EmailContactEntity`, avec des informations supplémentaires d'inscription et une demande temporaire de confirmation.
- Une même adresse reçoit un seul exemplaire d'une campagne, même si plusieurs chemins la sélectionnent.

Les précisions de fonctionnement ci-dessous sont les propositions de conception soumises à relecture.

## Socle existant au cadrage initial

Les constats ci-dessous décrivent l'état avant le lot 1. Depuis, les notifications de première publication, la présentation commune des mails de service et le retour à la fiche après connexion sont implémentés et vérifiés localement ; voir le plan du lot 1 pour l'état final.

- `EmailContactEntity` possède une adresse normalisée et unique, un état actif, `optInNewsletter`, un jeton de désinscription et `unsubscribedAt`. Ses liens vers l'annuaire sont facultatifs.
- `DoctrineNewsletterAudienceResolver` sélectionne actuellement les contacts via leurs liens vers des entrées actives de l'annuaire et déduplique par adresse.
- Le mailing possède déjà des audiences réutilisables, une prévisualisation des destinataires, un instantané à la mise en file, une livraison cadencée, des reprises et un lien de désinscription.
- `UserEntity` stocke la préférence `newSessionNotificationsEnabled`, modifiable dans le profil ; aucun flux d'envoi de nouvelle séance n'a été identifié.
- La publication peut être modifiée depuis le bouton dédié ou depuis le formulaire d'édition de séance. Les deux chemins doivent produire le même comportement.
- Les templates newsletter utilisent une palette crème, vert et terracotta ; le template actuel d'invitation/réinitialisation est plus sommaire.

## Lot 1 — Notifications de première publication

### Déclencheur et destinataires

Enregistrer durablement la première publication d'une séance. Une bascule ultérieure vers « non publiée », suivie d'une republication, ne crée pas une nouvelle notification. Le rattachement ultérieur à une autre structure ne crée pas non plus de nouvel envoi dans cette première version.

Au moment de cette première publication, sélectionner les comptes actifs et utilisables ayant un accès actif à une des structures rattachées, avec la préférence de notification activée. Un compte lié à plusieurs structures concernées ne reçoit qu'un message.

L'activation de la préférence après publication ne produit aucun rattrapage. Une publication sans destinataire éligible reste considérée comme la première publication.

Les séances déjà publiées au moment du déploiement sont initialisées comme ayant déjà franchi cette étape, sans notification rétroactive.

### Envoi et reprise

La publication et la programmation durable des notifications sont enregistrées dans la même transaction, puis les envois sont traités de façon asynchrone avec les outils Messenger existants. Une panne SMTP ne bloque pas la publication.

Conserver un suivi par séance et compte, avec une contrainte d'unicité, les états d'envoi, les tentatives et les erreurs. Les demandes répétées de publication et les messages Messenger répétés ne doivent pas programmer plusieurs livraisons métier.

Avant l'envoi, vérifier que la séance est encore publiée, que le compte dispose encore de l'accès nécessaire et que sa préférence reste activée. Une notification devenue inéligible est abandonnée. Elle n'est pas recréée lors d'une republication.

Le suivi évite les doublons habituels et permet les reprises. Avec SMTP, une interruption après acceptation du mail mais avant enregistrement du succès laisse une incertitude ; la conception ne promet pas une garantie absolue d'envoi exactement une fois.

### Contenu client

- Objet explicite : une nouvelle séance est disponible dans l'espace Jardin Sonore.
- Aperçu de boîte mail utile, sans texte technique.
- Salutation naturelle et courte introduction.
- Titre de la séance, date et nom de la ou des structures auxquelles le destinataire a accès.
- Bouton principal « Découvrir la séance », vers la fiche du portail.
- Retour à la fiche après connexion si l'utilisateur n'est plus connecté ; le lien ne donne aucun droit supplémentaire.
- Lien discret vers les préférences de notification dans le compte.

Le message n'expose ni notes privées, ni noms d'autres structures sans accès, ni document en pièce jointe. Il ne dépend pas de la disponibilité du PDF et ne promet pas que celui-ci est prêt.

## Lot 2 — Abonnements et audiences newsletter

### Une adresse et un état d'abonnement communs

Réutiliser `EmailContactEntity` pour les abonnés libres, même sans lien à une entrée de l'annuaire. Rechercher l'adresse normalisée avant toute création et s'appuyer sur son unicité pour traiter les demandes concurrentes.

Ajouter un indicateur explicite d'inscription libre et la date/origine de sa confirmation. Le groupe supplémentaire dépend de cette inscription explicite, pas simplement de l'absence de structure ni de la source initiale du contact.

Une inscription libre confirmée reste enregistrée si un lien vers l'annuaire est ajouté plus tard. Cette adresse peut alors être sélectionnée par plusieurs chemins ; le résultat reste dédupliqué. Une simple création de compte portail ne l'ajoute pas au groupe des abonnés libres.

L'état newsletter et la désinscription restent portés par l'adresse. La case newsletter du portail lit et modifie cet état ; elle ne crée pas un second booléen d'abonnement sur le compte. Une modification depuis le profil et une désinscription depuis un mail doivent rester cohérentes.

La préférence de notification de séance reste indépendante sur le compte. Retirer l'abonnement newsletter ne désactive pas les notifications de séance.

Les valeurs historiques d'abonnement sont préservées et ne sont pas présentées comme des confirmations publiques nouvelles. Les nouvelles adresses issues du formulaire public commencent non abonnées, contrairement au défaut historique `optInNewsletter = true` de l'entité.

### Changement d'adresse

Aujourd'hui, le profil portail ne propose pas de modification de l'adresse et le CRUD des comptes expose ce champ uniquement à la création. L'adresse d'un contact de l'annuaire peut en revanche être modifiée. Une telle modification ne change pas implicitement l'identifiant d'un compte portail.

L'adresse identifie une boîte de réception : un remplacement doit rechercher ou créer le contact correspondant à la nouvelle adresse, plutôt que renommer un contact partagé et emporter son historique. Les liens d'autres personnes ou structures et leurs adresses restent préservés. Les dates de confirmation, jetons et choix d'abonnement ne sont pas copiés automatiquement d'une adresse à une autre.

Tout futur parcours de changement d'adresse du compte, y compris une action d'administration, doit synchroniser son adresse avec le contact newsletter correspondant et vérifier la nouvelle adresse avant d'y établir un nouvel abonnement. Si cette adresse existe déjà, réutiliser sa fiche et respecter son état actif et sa désinscription. Une confirmation volontaire de réinscription peut lever la désinscription ; un simple changement de compte ne le peut pas.

Les notifications de séances en attente utilisent l'adresse actuelle du compte au moment de l'envoi. Un compte mis à jour n'est plus sélectionné par son ancienne adresse dans la source portail. L'ancienne adresse n'est toutefois pas supprimée ou désinscrite globalement : elle peut encore servir une structure ou porter une inscription libre indépendante. Le retrait de cet abonnement ancien reste une action explicite, affichée comme telle dans un éventuel parcours de changement d'adresse.

Les destinataires newsletter déjà figés pour une campagne ne sont pas réécrits vers la nouvelle adresse. Les nouvelles résolutions utilisent les données courantes et les envois déjà programmés restent soumis à la vérification de désinscription.

### Sélection des destinataires

Combiner trois sources, puis dédupliquer par adresse :

1. Les contacts de l'annuaire retenus par les critères existants.
2. Les comptes portail abonnés ayant un accès actif à une structure retenue par ces mêmes critères.
3. Les inscriptions libres confirmées, lorsque le groupe supplémentaire est coché.

La clé de dédoublonnage est l'adresse normalisée, en minuscules et sans espaces autour. Ne pas supprimer les suffixes `+...` ni les points propres à certains fournisseurs. Une adresse présente dans l'annuaire, dans plusieurs structures du portail et dans le groupe supplémentaire produit un seul destinataire.

Appliquer cette règle dans la prévisualisation puis lors de la préparation réelle de la livraison, avec une unicité campagne/adresse dans le stockage des destinataires. Une extension vérifie aussi les adresses déjà enregistrées pour la campagne, quel que soit leur chemin de sélection. Les doublons n'augmentent ni le total ni le nombre d'envois.

Déterminer les structures retenues indépendamment de l'existence d'un e-mail propre à la structure. Son utilisateur abonné doit pouvoir recevoir la campagne même si aucun e-mail de structure n'est éligible.

Dans tous les chemins, respecter l'abonnement, la désinscription et l'état actif de l'adresse. La case newsletter du portail doit également être respectée si la même adresse existe dans l'annuaire.

Le groupe supplémentaire est désactivé par défaut sur les campagnes et masques existants, pour préserver leur audience. Son réglage est conservé dans les filtres sauvegardés et dans les parcours de duplication ou d'extension qui les utilisent.

La prévisualisation montre le groupe supplémentaire et son effet sur le total réel après déduplication. La préparation et l'extension d'une campagne utilisent les mêmes règles. Avant chaque livraison, revérifier la désinscription afin de respecter un retrait intervenu après la mise en file.

## Lot 3 — Inscription publique

### Parcours

Le footer présente un court libellé, un champ e-mail et un bouton d'inscription. Prévoir les états chargement, demande enregistrée, adresse invalide et erreur temporaire dans les traductions du front.

Une demande valide nécessitant une confirmation déclenche un mail de confirmation. Le retour public reste identique si l'adresse est nouvelle, déjà connue ou déjà abonnée. Une adresse déjà connue n'est ni dupliquée ni réactivée par la simple soumission du formulaire.

Pour une adresse existante, distinguer les cas suivants :

- Connue de l'annuaire ou du portail, mais sans inscription libre confirmée : la confirmation ajoute l'inscription libre à la même fiche.
- Déjà inscrite au groupe libre et toujours abonnée : conserver l'abonnement sans nouvelle activation ni doublon ; le retour public reste identique et aucun mail de confirmation supplémentaire n'est nécessaire.
- Désinscrite : une nouvelle demande puis sa confirmation sont nécessaires pour reprendre l'abonnement.
- Bloquée techniquement : ne pas réactiver automatiquement l'adresse, même après confirmation.

Cette distinction évite les messages inutiles aux abonnés déjà inscrits. Pour les demandes en attente, les renvois sont limités et le nouveau jeton remplace le précédent. Traiter les soumissions concurrentes avec la contrainte d'unicité de l'adresse.

Le lien conduit à une page Jardin Sonore qui présente l'action de confirmation ; un bouton confirme par POST. L'ouverture du lien par un scanner de messagerie ne suffit pas à valider l'inscription.

Après confirmation, activer l'inscription libre et l'abonnement newsletter. Un lien expiré permet de demander un nouvel e-mail ; une nouvelle demande invalide les anciens liens. Une adresse déjà abonnée peut confirmer son appartenance au groupe libre sans modifier ses liens d'annuaire.

Une réinscription après désinscription exige une nouvelle confirmation. Un blocage technique de l'adresse, par exemple après un destinataire invalide, n'est pas levé automatiquement par le formulaire.

### Stockage et protection du formulaire

Prévoir une petite entité de demande, liée à l'adresse : jeton stocké haché, date de demande, expiration, consommation et origine du formulaire. Proposition de durée de validité : 48 heures.

Réutiliser les outils existants de vérification anti-robot et ajouter une limitation de fréquence des demandes et des renvois. Ne pas enregistrer de nom, mot de passe ou structure pour cette inscription simple.

## Présentation des e-mails de service

Construire une présentation commune aux notifications de séances, à la confirmation newsletter et aux invitations/réinitialisations de compte. Le contenu et l'action principale restent propres à chaque mail.

La direction visuelle reprend Jardin Sonore : fond crème, surface claire, signature vert/terracotta, titre lisible, espacements généreux et un bouton principal contrasté. Les mails utilitaires sont courts ; celui de nouvelle séance met particulièrement en valeur le titre, la date et la structure.

Utiliser une largeur fluide plafonnée autour de 600 px, des polices disponibles dans les messageries, des styles compatibles avec l'e-mail et une version texte complète. Le contenu essentiel reste compréhensible si les images sont bloquées. Une photographie n'est pas nécessaire pour obtenir un rendu soigné.

Prévoir des aperçus HTML avec données fictives pour chaque type de mail et une relecture sur mobile et desktop. Les essais dans les boîtes Gmail et Outlook précèdent la livraison ; aucun envoi de test à une tierce personne n'est réalisé sans instruction explicite.

La mise à jour des mails de compte inclut la correction du texte d'invitation qui annonce encore une disponibilité future du portail.

Le footer suit la présentation existante. Une maquette externe n'est pas nécessaire pour le champ d'inscription ; un aperçu du mail de nouvelle séance sera utile pour valider son rendu avant la livraison.

## Vérifications attendues à l'implémentation

- Première publication depuis chaque entrée du backoffice ; aucune notification à l'édition, à la republication ou pour les séances historiques.
- Destinataires autorisés uniquement ; plusieurs structures, comptes inactifs, retrait d'accès et préférence désactivée avant l'envoi.
- Reprise d'envoi et demandes concurrentes sans programmation métier en double.
- Case newsletter du portail cohérente avec la désinscription et les contacts déjà existants.
- Structure retenue sans adresse e-mail propre ; inscription libre incluse/exclue ; déduplication entre toutes les sources.
- Masques et campagnes existants conservant leur ciblage ; extension de campagne sans réenvoi aux mêmes adresses.
- Inscription publique, confirmation, expiration, réutilisation de lien, réinscription et absence de changement d'état sur simple GET.
- Adresse déjà connue, déjà inscrite librement, désinscrite ou bloquée ; casse/espaces et demandes concurrentes sans duplication.
- Changement d'adresse d'un contact préservant les autres liens et ne transférant pas son consentement ; aucun changement implicite d'adresse du compte portail.
- Notification en attente prenant l'adresse actuelle du compte ; aucun remplacement implicite des destinataires figés d'une campagne.
- Désinscription après mise en file prise en compte par l'envoi.
- Rendu HTML et texte, liens absolus, lecture mobile et absence de données privées dans les mails.
- Diff de schéma limité aux champs et tables nécessaires ; migration relue avant proposition d'exécution.

## Ordre de réalisation proposé

1. Notifications de première publication et présentation commune des mails de service.
2. Abonnement newsletter du portail, groupe libre et adaptation des audiences.
3. Formulaire footer, demandes de confirmation et pages de résultat.

Ces lots sont reliés par leurs préférences et leur présentation, mais chacun reçoit un plan et des vérifications adaptés. Ce document ne constitue pas une autorisation de commit, de migration ou de déploiement.
