# Newsletter : footer et gestion des abonnés dans E-mails

Date : 1er octobre 2026. Conception validée et livrée sous `deploy-newsletter-footer-20261001-01`. Cette conception conserve les décisions du lot ; état et priorités actuels : [ROADMAP.md](../../../ROADMAP.md).

## Demande et décisions

L'utilisateur souhaite réaliser ensemble la partie 5 du plan newsletter et
l'inscription publique depuis le footer. Il veut ajouter et modifier les abonnés
libres dans la rubrique E-mails, puis les retrouver par des filtres. Il a choisi
l'activation directe dans le backoffice lorsque le consentement a déjà été recueilli.

Cette conception complète le [cadrage newsletter](2026-09-30-session-notifications-newsletter-design.md)
et la [partie 5 du plan existant](../plans/2026-09-30-newsletter-subscriptions-and-audiences.md).
Les audiences et la vérification du consentement avant SMTP sont déjà déployées
sous `deploy-newsletter-audiences-consent-20261001-01` ; elles ne sont pas à refaire.

## Approche retenue

Conserver une adresse partagée dans `email_contact`, son consentement courant et
son appartenance au groupe libre. Symfony reste l'autorité pour l'inscription,
la confirmation et les opérations administrateur. Next.js affiche le formulaire
et relaie les demandes par des routes serveur, comme le BFF du portail existant.

Un stockage séparé des abonnés créerait deux états concurrents de consentement et
compliquerait le désabonnement. Une activation publique immédiate permettrait
d'abonner une adresse sans vérifier que la personne la contrôle. La solution
retenue réutilise les contacts existants et confirme l'inscription publique par
e-mail ; la saisie administrative atteste explicitement le consentement préalable.

## Footer et confirmation publique

- Ajouter au footer un bloc compact avec titre, explication, champ e-mail,
  bouton d'inscription et ALTCHA existant. Tous les libellés viennent du
  dictionnaire français. Le formulaire reste utilisable au clavier et sur mobile,
  avec états de chargement et messages annoncés aux lecteurs d'écran.
- Une adresse syntaxiquement valide reçoit une réponse générique identique,
  qu'elle soit nouvelle, connue, déjà abonnée ou bloquée. Une erreur de validation,
  de captcha ou une indisponibilité peut être indiquée sans révéler l'état du contact.
- Un nouveau contact est créé avec `optInNewsletter=false`. Aucun consentement
  n'est acquis par la simple soumission du formulaire.
- Envoyer un message de confirmation HTML et texte utilisant les gabarits de
  messages de service existants. Le lien est valable 48 heures.
- Le GET du lien affiche une page avec un bouton « Confirmer mon inscription ».
  Seul un POST explicite active l'abonnement : un scanner de messagerie qui visite
  le lien ne doit rien modifier.
- Une confirmation valide inscrit dans le groupe libre, enregistre date et origine
  `footer`, active le consentement newsletter et efface le désabonnement courant.
  Elle préserve les liens d'annuaire et les accès du portail.
- Une adresse déjà abonnée au groupe libre et consentante ne reçoit pas de nouveau
  message. Une adresse connue sans appartenance au groupe libre doit confirmer.
  Une adresse désabonnée peut se réinscrire en confirmant une nouvelle demande.
  Une adresse techniquement inactive n'est jamais réactivée par ce parcours.
- Le renvoi remplace le jeton précédent. Un jeton expiré ou remplacé conduit à
  demander un nouveau lien. Un jeton déjà consommé affiche un état informatif et
  ne réactive jamais une adresse désabonnée depuis sa consommation.

## Gestion dans E-mails

Conserver le point d'entrée existant de la rubrique E-mails. Ajouter une action
« Ajouter un abonné », les informations d'inscription libre et des filtres :
appartenance au groupe libre, consentement courant, date de désabonnement et
origine d'inscription. L'origine de l'inscription est distincte de la provenance
historique du contact (`ContactDataSource`).

L'ajout contient une adresse et une attestation obligatoire « J'ai déjà recueilli
le consentement de cette personne à recevoir la newsletter ». Sans attestation,
aucune activation n'est possible. Une adresse existante est réutilisée ; aucun
doublon ni lien d'annuaire supplémentaire n'est créé. Le consentement recueilli
est enregistré à la date de saisie, avec origine `backoffice`. La date affichée
est la date d'enregistrement, sans prétendre dater le recueil antérieur.

Une activation nouvelle ou après désabonnement exige cette attestation. Une
opération sur une inscription déjà active est idempotente et conserve sa date et
son origine. Une adresse techniquement inactive reste bloquée ; sa réactivation
technique relève de la gestion du contact, pas du consentement newsletter.

La modification permet de retirer le consentement par une action POST protégée
par CSRF. Elle met à jour le consentement partagé et la date de désabonnement,
sans effacer la date et l'origine de l'inscription passée. La réinscription
administrative exige une nouvelle attestation et enregistre le nouvel état.

Une adresse ayant un historique d'inscription libre n'est pas réécrite en place.
L'interface permet d'ajouter l'adresse corrigée avec une nouvelle attestation et
de désabonner l'ancienne séparément. Cela conserve l'historique et évite de
déplacer les liens partagés d'un contact d'annuaire ou d'un compte du portail.
Les modifications de provenance et d'état technique existantes restent disponibles.
Les bascules directes de consentement EasyAdmin sont remplacées par les actions
validées afin de ne pas contourner l'attestation ou le désabonnement.

## Audiences, masques et campagnes

Ajouter la case « Inclure les abonnés libres » au formulaire d'audience, désactivée
par défaut. Elle inclut les abonnés libres confirmés et consentants indépendamment
des critères de structure. Le récapitulatif rappelle ce comportement ; le total
reste dédupliqué par adresse entre annuaire, portail et abonnés libres.

Propager cette option dans le modèle de formulaire, les reconstructions liées à
la géographie, les masques sauvegardés/appliqués, les duplications et les extensions.
Les données historiques sans option restent à `false`. Les audiences verrouillées
restent verrouillées. Une extension n'ajoute que les nouvelles adresses éligibles
et ne renvoie pas aux destinataires déjà présents.

## Stockage, concurrence et frontières

Créer une demande temporaire liée à `email_contact`, avec un seul enregistrement
courant par contact : empreinte unique du jeton, date de demande, expiration,
date de consommation et origine. Seule l'empreinte est persistée ; les jetons
bruts ne sont ni journalisés ni placés dans une file durable.

La création/réutilisation de l'adresse et le remplacement de la demande utilisent
une transaction et un verrou sur le contact, selon le pattern existant du gestionnaire
de préférence du portail. Deux requêtes concurrentes ne doivent pas créer deux
contacts ou deux jetons simultanément valables. La confirmation verrouille aussi
le contact et la demande, puis vérifie expiration, consommation et état technique.

L'e-mail de confirmation est envoyé après validation de la transaction, avec
l'expéditeur et l'URL publique existants. Un échec SMTP ne donne aucun consentement ;
il produit un état temporairement indisponible. Une demande suivante peut renvoyer
un lien selon la limitation configurée. Les confirmations et activations
administratives utilisent la même opération métier d'abonnement.

Les routes Next.js vérifient l'origine, la forme des données et ALTCHA avant
la demande publique. Les routes backend dédiées exigent le secret BFF existant,
comparé en temps constant, afin qu'un appel direct ne contourne pas le captcha.
Elles ne nécessitent pas de compte portail et ne rendent aucune route administrateur
publique. Les routes de confirmation imposent également le passage par le BFF.

Limiter les demandes à cinq par minute et par IP, ainsi qu'à trois par heure par
adresse normalisée, avec un délai minimal de 60 secondes entre deux envois pour
une adresse. Utiliser les rate limiters Symfony existants et la résolution d'IP
BFF existante. La clé par adresse est une empreinte ; elle ne contient pas l'adresse
en clair. Les refus liés à l'adresse gardent la réponse publique générique.
Les GET de confirmation utilisent `no-store` et une politique de référent empêchant
la fuite du jeton ; aucune ressource tierce n'est ajoutée à cette page.

## Vérification et livraison

Les tests couvrent : demande sans activation, réutilisation sans doublon, jetons
remplacés/expirés/consommés, GET sans mutation, POST valide, concurrence, contact
inactif, désabonnement puis réinscription, erreur SMTP, limites et accès backend
direct refusé. Les tests administrateur vérifient authentification, CSRF,
attestation obligatoire, modification d'adresse sans transfert de consentement
et filtres. Les tests de campagne vérifient toute la propagation de l'option et
l'absence de renvoi aux adresses déjà figées.

Recette locale dans les services existants, avec données fictives et Mailpit :
footer mobile/clavier, message de confirmation, page GET puis confirmation POST,
consultation du contact dans E-mails et prévisualisation de campagne. Aucun
e-mail de recette n'est envoyé à une personne réelle.

Lancer les tests backend, style, PHPStan, validation du conteneur, Twig/YAML,
puis lint et build Next.js. Générer et relire la migration ; vérifier que le diff
de schéma ne contient que le stockage nécessaire aux demandes. L'exécution de
la migration reste soumise à confirmation conformément aux consignes du projet.
La livraison suit les règles de commit, tag, push et déploiement du repo.

Hors périmètre : import massif, segmentation supplémentaire, page légale,
refonte du footer, modification du routage général des proxies et des campagnes
déjà figées. La rétention de la dernière demande suit celle du contact ; le jeton
expire à 48 heures même si son empreinte reste enregistrée.
