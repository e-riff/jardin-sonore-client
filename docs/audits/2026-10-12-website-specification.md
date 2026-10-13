# Audit du site Jardin Sonore contre The Website Specification

**Date :** 12 octobre 2026  
**Référence :** [checklist de The Website Specification](https://specification.website/checklist/) et son serveur MCP `https://mcp.specification.website/mcp`  
**Périmètre :** `https://jardin-sonore.fr/`, `/eveil-musical-creche`, `/mentions-legales`, `/portail/connexion`, réponses HTTP publiques, client Next.js du dépôt et parcours sans authentification sur `localhost:3000`.

## Méthode et limites

La checklist canonique a été récupérée via `get_categories` et `get_checklist` du serveur MCP ; elle compte **169 sujets dans 10 catégories**. Le prompt MCP `audit_url` a servi à repérer les critères marqués *required*. Chaque sujet a été trié selon sa pertinence pour ce site. Les contrôles détaillés ci-dessous portent en priorité sur les critères obligatoires et sur les parcours réellement présents. Il ne s'agit pas d'une certification WCAG, d'un audit juridique ni d'une mesure de trafic réel.

Les réponses de production ont été lues avec `curl` le 12 octobre 2026. Le navigateur automatisé a reçu une page Tiger Protect **HTTP 429** en production ; les essais interactifs ont donc utilisé le serveur local déjà en fonctionnement sur `localhost:3000`. Je n'ai pas ouvert de session portail, déclenché d'envoi de formulaire ou modifié la production. Les fichiers non suivis de l'hébergement, la configuration Apache globale, les journaux et les métriques utilisateurs ne sont pas accessibles depuis le dépôt.

**Convention du rapport :** « constaté » = réponse ou comportement observé ; « code » = comportement déduit du code actuel ; « à mesurer » = aucune preuve suffisante pour conclure. Les priorités P1 à P3 reflètent l'impact sur les visiteurs et l'effort de correction, pas une gravité légale.

## Synthèse

Le socle éditorial et technique est bon pour un petit site vitrine : HTML rendu côté serveur, langue française, titres et descriptions, URL canoniques sur les trois pages publiques, Open Graph, `robots.txt`, sitemap, données structurées, images WebP avec `alt`, lien d'évitement, focus visible, vrai 404, HTTP/2, Brotli et ETag/304. Le portail emploie un cookie `__Host-` avec `Secure`, `HttpOnly` et `SameSite=Lax` dans le code.

Les écarts les plus utiles à corriger sont les **en-têtes de sécurité absents**, **l'erreur de connexion invisible** et **la galerie qui se fige en mode réduction des animations**. Les autres améliorations concernent surtout la robustesse sans JavaScript, les médias privés, les polices externes et quelques points de découverte machine. Aucun score global n'est calculé : la checklist mélange exigences, recommandations, options et sujets sans objet pour ce site.

## Constats prioritaires

### P1 — En-têtes de protection absents des pages observées

**Constaté :** les réponses `200` de l'accueil et du formulaire de connexion ne portent ni `Strict-Transport-Security`, ni `X-Content-Type-Options`, ni `Content-Security-Policy`, ni `X-Frame-Options`, ni `Referrer-Policy`, ni `Permissions-Policy`. Le seul en-tête déclaré dans [next.config.ts](../../jardin-sonore-client/next.config.ts) vise une page de confirmation newsletter. Les en-têtes [HSTS](https://specification.website/spec/security/hsts/), [nosniff](https://specification.website/spec/security/x-content-type-options/) et [protection contre l'encadrement](https://specification.website/spec/security/frame-ancestors/) sont « required » dans la checklist.

**Impact :** le navigateur ne reçoit pas de politique explicite contre le retour en HTTP après la première visite, l'interprétation d'un type MIME erroné ou l'affichage du site dans une iframe tierce. L'absence de CSP laisse aussi moins de défense en profondeur contre les injections côté navigateur. L'absence de ces en-têtes ne démontre pas, à elle seule, une exploitation possible.

**Action :** configurer les en-têtes à l'origine qui sert effectivement les réponses (Next ou hébergeur) et vérifier à nouveau les réponses publiques, privées et d'erreur. Commencer par `nosniff`, une politique d'encadrement (`frame-ancestors 'none'` ou `'self'` selon les intégrations), puis HSTS après vérification de tous les sous-domaines. Déployer la CSP d'abord en `Report-Only` : Next, ALTCHA et les éventuels médias exigent une politique testée, sans modèle copié aveuglément. Ajouter ensuite `Referrer-Policy: strict-origin-when-cross-origin` et une `Permissions-Policy` adaptée. Le `Referrer-Policy: no-referrer` déjà présent sur la confirmation newsletter doit rester plus strict.

### P1 — Connexion : l'échec n'est pas annoncé

**Constaté en local :** `/portail/connexion?error=1` affiche exactement le formulaire ordinaire, sans texte d'erreur ni région d'annonce. [loginPortalAction](../../jardin-sonore-client/src/app/portail/actions.ts) redirige pourtant vers ce paramètre après un refus d'authentification ; [la page de connexion](../../jardin-sonore-client/src/app/portail/connexion/page.tsx) ne lit que `next`. Une personne ne sait donc pas si les identifiants ont été refusés, si le service est indisponible ou si l'action s'est produite. C'est un écart à [Accessible form errors](https://specification.website/spec/accessibility/form-errors/).

**Action :** lire le paramètre d'erreur, afficher un message traduit près du formulaire avec `role="alert"`, et associer les champs concernés avec `aria-describedby` ou un résumé d'erreur. Distinguer une réponse 401 d'une indisponibilité du backend si l'API permet de le faire. Tester une connexion refusée au clavier et avec lecteur d'écran.

### P1 — Galerie bloquée avec réduction des animations

**Constaté en local à 390 px :** après activation de `prefers-reduced-motion: reduce`, « Photo suivante » rend les deux flèches désactivées et elles le restent après plus d'une seconde. Le mode réduit supprime la transition CSS, tandis que [ExplorationGallery.tsx](../../jardin-sonore-client/src/components/sections/ExplorationGallery.tsx) attend `onTransitionEnd` pour terminer le changement d'image. Cela touche une préférence d'accessibilité explicitement [requise par la checklist](https://specification.website/spec/accessibility/reduced-motion/).

**Reproduction :** ouvrir l'accueil local, émuler `prefers-reduced-motion: reduce`, activer « Photo suivante », attendre 1,5 seconde ; le bouton reste `disabled=true`. Capture : `/tmp/jardin-audit-gallery-reduced-motion.png` (preuve locale de session, hors dépôt).

**Action :** lorsque la préférence est active, changer directement `activeGroupIndex` sans créer `slideTransition`. Conserver `onTransitionEnd` pour le chemin animé et prévoir un repli si l'événement n'arrive jamais.

### P2 — Le contact et la newsletter dépendent entièrement du JavaScript

**Code :** [CtaContactPanel.tsx](../../jardin-sonore-client/src/components/CtaContactPanel.tsx) garde le formulaire de contact fermé et `inert` jusqu'à un clic géré par React, puis intercepte l'envoi avec `fetch`. [NewsletterSignupForm.tsx](../../jardin-sonore-client/src/components/newsletter/NewsletterSignupForm.tsx) intercepte aussi l'envoi côté client. Si JavaScript est indisponible ou échoue avant l'hydratation, ces deux parcours ne permettent pas l'envoi. La [dégradation progressive](https://specification.website/spec/resilience/graceful-degradation/) est recommandée ; c'est important ici car le contact est la conversion principale.

**Action :** proposer un lien de contact qui fonctionne sans script et/ou une route de formulaire HTML classique, avec traitement et protection anti-abus côté serveur. Vérifier le parcours en désactivant JavaScript. Le téléphone lui-même est également révélé par un POST client ; prévoir un moyen de contact alternatif accessible depuis les mentions légales.

### P2 — Polices Google chargées par une feuille externe

**Code :** la première ligne de [globals.css](../../jardin-sonore-client/src/app/globals.css) charge `fonts.googleapis.com` par `@import`. Cela ajoute une dépendance réseau externe au rendu et transmet une requête au fournisseur de polices avant toute interaction. La page de confidentialité mentionne les médias externes mais pas ce chargement. Voir [Web font loading](https://specification.website/spec/performance/web-font-loading/) et [Third-party scripts and privacy](https://specification.website/spec/privacy/third-party-scripts/).

**Action :** héberger les fichiers de police avec `next/font/local` ou des fichiers locaux, avec sous-ensembles et graisses utiles. Vérifier les licences, le chargement sans flash gênant et le rendu mobile. Ce constat ne préjuge pas de la conformité juridique du traitement.

### P2 — Les lecteurs audio/vidéo du portail n'ont pas de piste de sous-titres ni de transcription liée

**Code :** [PortalMediaGallery.tsx](../../jardin-sonore-client/src/components/portal/PortalMediaGallery.tsx) rend `<video controls>` et `<audio controls>` sans `<track>` ni lien de transcription ; [PortalSessionPreview.tsx](../../jardin-sonore-client/src/components/portal/PortalSessionPreview.tsx) rend des vidéos intégrées. Le contenu réel est privé et n'a pas été inventorié. Pour les médias qui portent de la parole ou une information visuelle, [captions and transcripts](https://specification.website/spec/accessibility/captions-and-transcripts/) est un critère obligatoire.

**Action :** inventorier les médias publiés ; ajouter au modèle de média une piste VTT et/ou une transcription, puis les afficher. Pour YouTube, vérifier la présence et la qualité des sous-titres sur chaque ressource. Ne pas marquer tout le portail « non conforme » avant cet inventaire.

### P2 — Contraste et cibles tactiles : tests de parcours à compléter

**Code et calcul :** les principales couleurs de texte passent souvent le seuil de 4,5:1 (`#be4f41` sur `#fdf8f5` ≈ 4,54:1), mais `#88726f` sur ce fond ≈ 4,25:1 et les couleurs de badges du portail sont variables. L'usage exact de chaque paire et les états de survol/focus n'ont pas été mesurés automatiquement. Les flèches de galerie font 40 px sur mobile, ce qui dépasse 24 px, mais le contrôle complet des liens et widgets n'a pas été fait. Voir [Colour contrast](https://specification.website/spec/accessibility/color-contrast/) et [Touch target size](https://specification.website/spec/accessibility/touch-target-size/).

**Action :** mesurer les couleurs réellement calculées sur accueil, offre, formulaires et portail, puis faire un parcours clavier et tactile sur téléphone avec les états ouverts et en erreur. Tester aussi le mode de couleurs forcées et le zoom à 200 %. Ne pas déduire un PASS global d'une palette seule.

### P3 — Points de découverte et de résilience absents ou non documentés

- `/.well-known/security.txt` renvoie 404. Ce point est [recommandé](https://specification.website/spec/security/security-txt/) ; publier une adresse de signalement et une date d'expiration tenues à jour.
- `/.well-known/change-password` renvoie 404 alors que le portail possède un changement de mot de passe. C'est [optionnel](https://specification.website/spec/well-known/change-password/) et utile aux gestionnaires de mots de passe ; rediriger vers la bonne page, en tenant compte de l'authentification.
- `/llms.txt` et `/.well-known/api-catalog` renvoient 404. Les deux sont recommandés dans cette spécification, mais leur apport est faible pour trois pages publiques sans API publique documentée. Un fichier court et exact peut aider les agents ; un catalogue API et un serveur MCP du **site Jardin Sonore** ne sont utiles que si une API publique doit être découverte. Aucun déploiement MCP n'est justifié par cet audit.
- Une page 404 personnalisée existe et l'URL inconnue testée renvoie 404. Il existe un composant d'erreur pour le portail, mais aucune page 500 globale dédiée dans `src/app/`. Le rendu et le code HTTP d'une vraie erreur serveur n'ont pas été provoqués en production : **à vérifier** avant de conclure sur [Custom error pages](https://specification.website/spec/resilience/error-pages/).
- Aucun dispositif de monitoring ou relevé des Core Web Vitals des visiteurs n'a été établi dans ce périmètre. C'est **à mesurer**, en particulier LCP, INP et CLS au 75e percentile. Un test sur le serveur local de développement ne remplacerait pas ces données.

## Lecture des dix catégories

| Catégorie | Ce qui est étayé | Écart ou limite principale |
| --- | --- | --- |
| Fondations (20) | `<!DOCTYPE html>`, `lang=fr`, UTF-8, viewport, titres, descriptions, canonical, icônes et Open Graph observés. | Pas de `theme-color` ni de `color-scheme` dans le HTML observé. Feed, WebSub et domaine à vendre sans objet. Les primitives CSS récentes sont des options d'implémentation, pas des obligations de refonte. |
| SEO (14) | `robots.txt`, sitemap de 3 URL, HTML rendu serveur, JSON-LD, fil d'Ariane sur l'offre, vrai 404 ; redirections HTTP et `www` en 301 vers l'origine canonique. | L'indexation effective reste à vérifier dans Search Console ; IndexNow et sitemap index sans utilité pour 3 pages. |
| Accessibilité (29) | Lien d'évitement premier élément interactif, landmarks, `alt` sur les 13 images HTML de l'accueil observées, labels, focus visible, statuts de newsletter ; structure H1/H2/H3 cohérente sur l'accueil. | Erreur de connexion, galerie en mouvement réduit, médias du portail ; contraste, couleurs forcées, clavier et cibles à vérifier systématiquement. |
| Sécurité (19) | HTTPS, HTTP→HTTPS 301, cookie portail `__Host-` protégé dans le code, absence de `X-XSS-Protection` observée. | HSTS, nosniff et anti-iframe absents ; CSP, Referrer-Policy, Permissions-Policy et security.txt à ajouter selon pertinence. CAA et DNSSEC non publiés d'après les requêtes DNS du 12 octobre. TLS 1.2/1.3 et tous les sous-domaines non testés. |
| Well-known URIs (12) | Le site ne se présente pas comme fournisseur OAuth/OIDC, application associée Apple/Android ou nœud fédéré. | `change-password` absent malgré les comptes ; `api-catalog` absent mais faible intérêt sans API publique. Les autres chemins sont sans objet ou facultatifs. |
| Agent Readiness (22) | URL publiques stables, SSR, données structurées et sitemap. | `llms.txt` absent. Les endpoints Markdown sont surtout pertinents pour un site documentaire ; MCP, A2A, WebMCP et formats expérimentaux ne sont pas des besoins établis du site. |
| Performance (25) | Images WebP et `next/image`, image héro préchargée, Brotli observé avec `Accept-Encoding`, ETag et 304 observés, cache public sur les pages statiques et `no-store` sur la connexion, HTTP/2. | Vitals réels inconnus ; `@import` Google Fonts ; stratégie de cache longue (`s-maxage=86400`, `stale-while-revalidate=31449600`) à confirmer selon le rythme de publication. Les Early Hints, dictionnaires de compression et animations de vue restent optionnels. |
| Privacy (7) | Page de confidentialité publiée, liens depuis les formulaires ; aucun traceur analytique identifié dans le client examiné. Cookie de session nécessaire au portail. | Polices externes ; durées de conservation décrites sans valeur chiffrée. Le choix d'un bandeau de consentement dépend d'un inventaire réel des cookies/stockages tiers : ne pas en ajouter à l'aveugle. |
| Résilience (8) | 404 personnalisée avec vrai statut, manifeste Web App servi en 200, erreurs de portail dédiées. | Dégradation sans JS absente pour le contact, 500 globale et supervision à vérifier. Aucun besoin établi de service worker ou d'en-tête Sunset. |
| Internationalisation (13) | Site francophone cohérent avec `lang=fr`, contenu et métadonnées français. | Les mécanismes `hreflang`, sélecteur de langue, URL régionales, RTL et pluriels multilingues sont sans objet tant qu'une seule langue est publiée. Les emprunts ou segments réellement étrangers doivent être revus ponctuellement. |

## Séquence recommandée

1. **Correctifs d'usage** : afficher l'erreur de connexion ; corriger la galerie en mode mouvement réduit ; vérifier ces deux cas au clavier et sur mobile.
2. **Protection navigateur** : ajouter les en-têtes obligatoires à la bonne couche d'hébergement ; démarrer une CSP en observation ; vérifier les réponses des pages publiques, du portail, des fichiers statiques et des erreurs.
3. **Conversion et contenu** : assurer une voie de contact utilisable sans JavaScript ; inventorier les médias privés et leurs sous-titres/transcriptions ; auto-héberger les polices.
4. **Mesures** : collecter les Core Web Vitals de production et une revue accessibilité manuelle (contraste calculé, zoom, clavier, couleurs forcées, lecteurs d'écran). Contrôler aussi la page 500, le cache après publication et l'inventaire cookies/stockage.
5. **Découverte légère** : publier `security.txt` puis, si utile, `change-password` et un `llms.txt` factuel. Les protocoles agents expérimentaux n'ont pas à précéder les correctifs ci-dessus.

La [ROADMAP](../../ROADMAP.md) reporte les compléments de la page légale au **5 novembre 2026**. Les remarques sur la conservation et les informations de confidentialité sont donc consignées pour cette échéance, sans rouvrir ce chantier avant la date prévue.

## Vérifications effectuées

- MCP : `get_categories`, `get_checklist`, `audit_url` ; serveur interrogé directement en lecture seule, sans ajout de configuration permanente.
- HTTP production : `curl` sur accueil, connexion, offre, mentions légales, robots, sitemap, manifeste, chemins `.well-known`, `llms.txt` et URL inconnue ; tests `Accept-Encoding: br,gzip` et `If-None-Match` ; HTTP et `www` vers HTTPS.
- DNS : recherches CAA et DS du domaine, réponses vides au moment du contrôle.
- Navigateur local existant : arbre d'accessibilité de l'accueil et de la connexion ; connexion avec `?error=1` ; largeur mobile 390 px sans débordement horizontal ; galerie avec `prefers-reduced-motion: reduce`.
- Code : métadonnées, sitemap, robots, styles, formulaires, galerie, lecteurs multimédias, cookie portail et pages d'erreur. Aucun fichier applicatif n'a été modifié pour cet audit.
