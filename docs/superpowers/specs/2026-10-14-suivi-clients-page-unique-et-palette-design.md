# Suivi clients : page Dossiers unique et fiche plus lisible

## Intention validée le 14 octobre 2026

L'utilisateur veut retrouver la lisibilité des propositions visuelles discutées avant l'implémentation de la fiche. Le fond actuel du backoffice paraît trop rose et les commandes de la partie commerciale trop rouges. Les pages « À faire » et « Dossiers » lui semblent redondantes. La refonte conserve le modèle dossier, tâches et fil défini dans la [conception principale](2026-10-13-suivi-clients-dossier-first-design.md) et les données déjà enregistrées.

## Une seule page Dossiers

`/commercial/projects` devient l'entrée canonique « Dossiers ». Elle rassemble les sections repliables « Prioritaires », « À venir », « Sans prochaine tâche » et, via le filtre, les dossiers clos. La recherche, le statut et le tri réactifs restent sur cette page. Chaque dossier reste présent une seule fois, classé selon sa prochaine tâche ; les commandes terminer et reporter restent sur sa ligne. « À faire » cesse d'être une page et un lien de navigation distincts : c'est la lecture prioritaire de la page Dossiers, avec les échéances les plus proches en haut.

`/commercial` et le nom de route `commercial_dashboard` restent utilisables pour les anciens liens et les courriels, mais redirigent vers la page canonique. Les actions rapides reviennent à la page Dossiers, qu'elles proviennent d'un ancien formulaire `dashboard` ou `index`. Le menu « Suivi clients » présente un seul lien « Dossiers », suivi des autres rubriques utiles. Les pages de création et de détail reviennent à cette destination. Aucune migration ni modification du modèle métier n'est nécessaire.

## Palette et hiérarchie

Le fond du shell interne devient un crème presque blanc, sans halo rose marqué. Les surfaces de travail sont blanches ou blanc chaud ; les séparateurs sont discrets. Le texte principal reste foncé. Le rouge corail de la marque garde un rôle ponctuel : action principale, échéance réellement urgente, repère chronologique ou état qui le justifie. Les liens et actions secondaires du suivi clients prennent le vert déjà présent dans la palette. Un simple survol ne doit pas transformer toute une zone en rouge. Les états et commandes restent reconnaissables sans se fier à la couleur seule.

La fiche dossier reprend la hiérarchie de la proposition approuvée : retour constant, date d'ouverture, statut et titre compacts ; coordonnées de structure et de personne lisibles en haut, avec valeur en texte et petite icône d'appel ou de courriel ; commandes distinctes pour ajouter une tâche ou un élément au fil ; documents repliables et sobres ; tâches datées faciles à trouver ; fil vertical avec dates marquées, pictogrammes légers et dernier événement ouvert. Sur ordinateur, le haut doit éviter la grande carte de contacts qui pousse les tâches sous l'écran. Sur téléphone, les coordonnées essentielles de la structure restent immédiatement visibles et le reste peut se déplier.

## Vérification et limites

Comparer la page unique et la fiche aux captures approuvées sur téléphone puis ordinateur, avec un compte authentifié. Vérifier les liens `tel:` et `mailto:`, l'absence de défilement horizontal, les commandes et dépliants au clavier, les filtres LiveComponent, les retours après action et les anciennes URL. Tester la redirection `/commercial`, la navigation et les liens du courriel. L'implémentation conserve les devis, factures, documents, événements et tâches existants. Cette refonte visuelle et de navigation ne déclenche aucun déploiement.
