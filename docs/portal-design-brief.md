# Brief de maquettage — Portail structures Jardin Sonore

Copier-coller le prompt ci-dessous dans l’outil de maquettage, puis joindre `portal-visual-reference.html` et, si l’outil l’accepte, le site public `https://jardin-sonore.fr` comme référence de marque.

```text
Conçois les maquettes responsive haute fidélité du « Portail structures » de Jardin Sonore.

Contexte et but
- Jardin Sonore propose des ateliers d’éveil musical à des crèches et EAJE.
- Les personnes connectées travaillent dans une ou plusieurs structures et consultent uniquement les séances qui leur ont été partagées : le portail est strictement en lecture seule.
- Le ton doit être rassurant, calme, humain et professionnel. Ce n’est ni un SaaS générique, ni un espace administratif froid.
- Référence de marque : https://jardin-sonore.fr. Utilise également le fichier HTML/CSS joint comme référence exacte de couleurs, typographies, rayons, boutons et logo typographique.

Contraintes visuelles non négociables
- Desktop et mobile pour chaque écran important ; mobile d’abord, sans cartes dans des cartes.
- Fond ivoire très clair, surfaces blanches ou rose très pâle, accents corail, vert doux et brun chaud.
- Titres : Noto Serif ; interface et données : Plus Jakarta Sans.
- Style : aéré, éditorial, doux, arrondis généreux, ombres très discrètes. L’orange/corail signale l’action principale ; le vert les états positifs. Ne pas utiliser de dégradés flashy, de bleu SaaS, de tableaux lourds ou de métriques factices.
- Le logo textuel « Jardin Sonore » reste visible dans les en-têtes. Prévoir un emplacement de logo texte coloré, pas une icône générique.
- Accessibilité : contrastes suffisants, états focus, libellés de champs visibles, erreurs explicites, zones cliquables confortables.

Produire les 8 écrans suivants dans un seul flux cohérent
1. Connexion
   - Logo, libellé « Espace structures », titre « Accéder à vos séances ».
   - E-mail, mot de passe, bouton « Se connecter », lien « Mot de passe oublié ? ».
   - Prévoir états erreur de connexion et chargement.

2. Demande de réinitialisation
   - Champ e-mail, bouton « Envoyer le lien », retour à la connexion.
   - Prévoir l’état de confirmation neutre : « Si cette adresse est associée à un compte, un lien… ».

3. Définition du mot de passe
   - Nouveau mot de passe, indication « au moins 12 caractères », bouton de validation.
   - Prévoir l’état lien expiré/invalide et son CTA de retour.

4. Liste des séances — état avec contenu
   - En-tête compact : logo, adresse e-mail de l’utilisateur, déconnexion.
   - Titre « Vos séances », courte phrase d’accueil, filtre de structure si plusieurs rattachements.
   - Liste chronologique décroissante de séances : date, structure, titre/thème, court résumé, statut du PDF et CTA « Voir la séance ».
   - Pagination sobre si utile. Pas de contenu inventé excessif : utiliser seulement 4 à 6 exemples réalistes (comptines, instruments, exploration sonore).

5. Liste des séances — état vide
   - Même shell. Message chaleureux expliquant qu’aucune séance n’est encore partagée, sans culpabiliser la structure.
   - CTA secondaire de contact discret, pas d’action de création.

6. Détail d’une séance
   - Fil d’Ariane/retour à la liste, date, structure, titre et thème.
   - Hiérarchie éditoriale : intention de séance, instruments et matériel, déroulé en séquences ordonnées, paroles/gestes dans des disclosures accessibles, liens média externes sûrs, recommandations.
   - Bloc PDF : disponible (télécharger), en préparation, indisponible. Le portail ne génère jamais le PDF.
   - Longue page lisible sur mobile, sans pavage de cartes.

7. Impersonation administrateur
   - Réutilise le shell connecté, avec bandeau fixe très visible mais sobre : « Mode aperçu administrateur » et bouton « Terminer la session ».
   - Montrer cet état sur la liste et/ou le détail ; il ne doit pas suggérer une possibilité d’édition.

8. État indisponible
   - Erreur de service temporaire, wording empathique, action « Réessayer » et retour à la connexion.

Ce que tu peux décider toi-même
- Icônes minimalistes et cohérentes (calendrier, document, instrument, flèche, téléchargement).
- Répartition desktop (contenu éditorial large + éventuel panneau latéral léger) et empilement mobile.
- Densité exacte, espacements, visuels décoratifs abstraits très discrets inspirés de la musique/du jardin — jamais nécessaires pour comprendre l’interface.
- Microcopies secondaires, tant qu’elles restent en français, sobres et cohérentes avec le ton ci-dessus.

Ce que tu ne dois pas inventer
- Aucune fonction de modification, ajout, suppression, messagerie, paiement, tableau de bord analytique ou rôles complexes.
- Aucune donnée personnelle sensible autre que l’adresse e-mail déjà connectée.
- Aucun choix de sécurité visible tel qu’un token, une API ou une option de session.

Livrable attendu
- Un design system léger (couleurs, typo, boutons, champs, badges d’état) puis les 8 écrans desktop + mobile.
- Des annotations brèves pour les états : erreur, chargement, vide, PDF en préparation/indisponible, impersonation.
- Une continuité visuelle évidente avec le site Jardin Sonore public, mais une navigation dédiée et plus compacte.
```

## Ce qui est déjà déterminé techniquement

- Connexion, déconnexion, réinitialisation et définition de mot de passe existent déjà côté prototype.
- Les comptes, droits par structure, séances et PDF restent contrôlés par le backend ; le front n’affiche que les données autorisées.
- La liste est paginée et filtrable par structure autorisée ; l’ordre est antéchronologique.
- Le PDF peut être disponible, en préparation ou indisponible. Il n’existe aucun bouton de génération.
- L’impersonation est une consultation temporaire par un administrateur, limitée à 30 minutes ; elle doit être signalée par un bandeau et pouvoir être arrêtée.

## Ce que les maquettes doivent nous livrer

Les décisions de design nécessaires avant reprise du développement : comportement mobile du filtre de structure, format visuel d’une ligne de séance, structure du détail long, rendu des trois états PDF, et niveau de visibilité du bandeau d’impersonation.
