# Portail structures — conception V1

## Objectif

Offrir aux structures invitées un espace en lecture seule sur `https://jardin-sonore.fr/portail` : connexion, réinitialisation autonome, historique des séances auxquelles elles sont rattachées, consultation détaillée et téléchargement du PDF associé.

## Architecture et sécurité

Le navigateur communique exclusivement avec Next.js sur `jardin-sonore.fr`. Next est un BFF : ses composants serveur, actions serveur et route de téléchargement appellent Symfony en HTTPS sur `https://admin.jardin-sonore.fr`. Le navigateur ne reçoit ni URL d'API interne, ni jeton bearer ; CORS n'est donc pas requis.

Symfony reste l'autorité unique pour les comptes, mots de passe, rattachements, séances et documents. Une authentification Bearer stateless protège l'API portail. Une session est un jeton opaque aléatoire de 32 octets, transmis une seule fois à Next et conservé par Symfony sous l'empreinte SHA-256, avec compte, dates de création/expiration et révocation. Next le garde dans le cookie `__Host-portal_session`, `HttpOnly`, `Secure`, `SameSite=Lax`, sans attribut `Domain`.

Une session structure dure sept jours. À chaque appel privé, Symfony vérifie le jeton, son expiration, l'état actif du compte et l'existence d'au moins un rattachement actif. Une déconnexion, un changement de mot de passe, une désactivation ou la suppression des accès révoquent les sessions concernées. Les endpoints de connexion et de reset ont leur propre limitation de débit. Les réponses de demande de reset restent identiques, que l'adresse existe ou non.

## Parcours

- Une invitation ou un reset pointe vers le front public. Après définition d'un mot de passe valide, le compte est activé si nécessaire, les anciennes sessions sont révoquées et l'utilisateur arrive connecté sur la liste des séances.
- Un compte actif se connecte par e-mail et mot de passe, demande lui-même un reset si nécessaire, puis peut se déconnecter.
- La liste contient toutes les séances passées et à venir accessibles via au moins une structure liée au compte. Elle est triée de la plus récente à la plus ancienne, paginée et filtrable par structure. Une séance multi-rattachée n'apparaît qu'une fois ; seuls les rattachements autorisés sont affichés.
- La fiche reprend les contenus visibles dans la prévisualisation back-office, sans édition ni informations techniques internes. Le PDF est disponible seulement lorsqu'il est réellement prêt ; son téléchargement est proxifié par Next, après autorisation Symfony.

## Impersonation

Un administrateur peut ouvrir, dans un nouvel onglet, le portail tel qu'il serait vu par un compte. L'action back-office protégée par CSRF crée un jeton de lancement à usage unique, valable cinq minutes, stocké haché et journalisé. Le formulaire POST ciblant le nouvel onglet transmet ce jeton à Next, qui l'échange côté serveur contre une session d'impersonation non persistante, plafonnée à trente minutes. Un bandeau fixe indique le mode test et permet de le terminer. L'action ne donne aucun droit d'écriture, puisque le portail est en lecture seule.

## API et données publiques

Les contrôleurs Symfony sous `/api/portal` exposent uniquement les représentations dédiées au portail :

- `POST /auth/login`, `POST /auth/logout`, `POST /auth/password-reset-requests` ;
- `POST /password-tokens/{token}/consume` ;
- `GET /me`, `GET /sessions`, `GET /sessions/{uuid}`, `GET /sessions/{uuid}/document.pdf` ;
- un échange interne de jeton d'impersonation.

Les réponses séance sont des DTO API explicites ; elles ne sérialisent jamais `SessionSummaryView`, notamment pour ne pas exposer `documentPath` ou `documentError`.

## Interface et maquettes

Le portail adopte l'identité Jardin Sonore avec un shell dédié : en-tête compact compte/déconnexion, sans navigation marketing complète. L'entrée publique « Espace structures » est discrète dans l'en-tête et le pied de page du site.

Les maquettes nécessaires sont : connexion ; demande et confirmation de reset ; définition de mot de passe avec état de lien indisponible ; liste avec filtre, pagination et état vide ; fiche avec PDF prêt ; fiche avec PDF en préparation/indisponible ; bandeau d'impersonation. Les écrans authentifiés sont adaptés au mobile et utilisent les textes du dictionnaire FR.

## Configuration, migration et limites

`PORTAL_PUBLIC_URL=https://jardin-sonore.fr` est configuré côté Symfony pour les e-mails. Le déploiement client injecte `PORTAL_API_BASE_URL=https://admin.jardin-sonore.fr` au build Next, sans réglage de routage cPanel. Une migration crée les tables de sessions et de lancements d'impersonation ; elle sera relue puis exécutée uniquement après accord explicite.

Cette V1 n'ajoute ni édition de séance, ni génération de PDF, ni lien magique, ni CORS ou cookie partagé entre sous-domaines.
