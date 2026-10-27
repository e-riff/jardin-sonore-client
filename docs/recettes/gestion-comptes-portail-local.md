# Recette locale — gestion des comptes portail

Ce protocole vérifie le déplacement des écrans dans l’onglet **Technique** sans changer les règles des comptes. Utiliser uniquement des comptes et des coordonnées de test, jamais un vrai compte client pour les suppressions ou les essais d’échec.

## Préparation

- [ ] Démarrer l’environnement local habituel et ouvrir le backoffice Symfony sur `http://localhost:8080` (port backend Docker par défaut).
- [ ] Se connecter avec un compte administrateur interne.
- [ ] Ouvrir **Technique → Comptes portail** et vérifier que la liste s’affiche, triée par e-mail.
- [ ] Saisir une adresse e-mail, un prénom, un nom, puis le nom d’une structure ; vérifier que chaque recherche se met à jour sans rechargement complet.
- [ ] Combiner une recherche texte avec le filtre de structure ; vérifier que les deux critères s’appliquent ensemble.
- [ ] Filtrer successivement les comptes en attente, actifs et inactifs ; vérifier que l’état affiché et les résultats correspondent.
- [ ] Filtrer les comptes autorisés et non autorisés ; vérifier que ce filtre reste distinct de l’état du compte.
- [ ] Cliquer sur plusieurs en-têtes de colonnes ; vérifier le tri ascendant/descendant sur e-mail, prénom, nom, structure, état et préférences.
- [ ] Avec plus de 25 comptes, changer de page puis modifier un filtre ; vérifier que le filtrage revient à la première page et que les résultats restent cohérents.
- [ ] Vérifier les actions par état : invitation pour un compte en attente ; réinitialisation et ouverture du portail pour un compte actif ; aucune de ces trois actions pour un compte inactif.
- [ ] Vérifier que la fiche détail montre les informations du compte, son état et tous ses accès.

## E-mails locaux

Le service Mailpit Compose expose son interface sur `http://localhost:8025` et son port SMTP local sur `1025`. Le `.env` versionné du backend utilise `null://null` par défaut : avant de commencer, vérifier que la configuration locale effective du backend envoie bien vers Mailpit (`localhost:1025`). Sans cela, les actions d’e-mail peuvent sembler réussir sans message visible dans Mailpit.

- [ ] Créer un accès de test et vérifier dans Mailpit la réception d’une invitation à la bonne adresse, avec le sujet et le lien attendus.
- [ ] Ouvrir le lien d’invitation, définir un mot de passe conforme, puis vérifier que le compte passe à l’état actif et que la connexion au portail fonctionne.
- [ ] Depuis un compte en attente, renvoyer l’invitation ; vérifier que le nouveau message arrive et que le lien précédent n’est plus utilisable.
- [ ] Ouvrir un ancien lien d’invitation ou de réinitialisation ; vérifier que le message d’indisponibilité apparaît avant le formulaire de mot de passe.
- [ ] Depuis un compte actif, demander une réinitialisation ; vérifier la réception du bon message, définir un nouveau mot de passe et confirmer que le compte reste actif.
- [ ] (Optionnel) Sur un compte de test seulement, rendre le transport SMTP local indisponible avant un envoi ; vérifier que l’interface indique l’échec et que l’action peut être retentée une fois le transport rétabli.

## Création et rattachement à l’annuaire

- [ ] Créer un compte en choisissant un e-mail existant déjà lié à la structure recherchée ; vérifier que l’e-mail est repris et que l’accès est rattaché à la bonne structure/personne.
- [ ] Créer un compte avec un nouvel e-mail et choisir de le rattacher à la structure ; vérifier l’adresse dans l’annuaire et l’accès du compte.
- [ ] Créer un compte avec un nouvel e-mail et choisir une nouvelle personne ; vérifier le prénom, le nom, l’adresse liée à cette personne et l’accès correspondant.
- [ ] Essayer une recherche de structure par nom, commune et code postal ; vérifier que le choix correspond à l’organisation voulue.
- [ ] Avec un compte de test existant, tenter de recréer le même e-mail ; vérifier que l’adresse unique n’entraîne pas la création d’un doublon et que le message de validation est compréhensible.
- [ ] Pour chaque création, vérifier qu’un seul compte portail et un seul premier accès ont été créés, et qu’une invitation est envoyée.

## Modification des informations et des accès

- [ ] Modifier le prénom et le nom d’un compte actif, enregistrer, puis rouvrir sa fiche pour vérifier les valeurs.
- [ ] Inverser la préférence de notifications de nouvelles séances, enregistrer, puis vérifier qu’elle est conservée. Vérifier qu’aucune préférence de newsletter n’a été modifiée.
- [ ] Remplacer la photo par une image JPEG, PNG ou WebP de moins de 2 Mo ; vérifier son affichage sur la fiche puis après reconnexion.
- [ ] Sur un compte de test, retirer la photo actuelle ; vérifier que la fiche ne l’affiche plus.
- [ ] Depuis un compte ayant plusieurs accès, ajouter une structure et vérifier qu’elle apparaît dans la fiche et dans la liste.
- [ ] Modifier la structure d’un accès et retirer un accès, puis vérifier les changements sur la fiche et dans le portail du compte.
- [ ] Essayer le retrait du dernier accès sur un compte de test ; noter précisément si le formulaire bloque l’opération ou si le compte se retrouve sans accès.
- [ ] Vérifier qu’un accès lié à une personne conserve cette personne quand on modifie un autre accès ou les informations du compte.

## Réinitialisation, ouverture du portail et suppression

- [ ] Vérifier qu’une tentative d’envoyer une invitation à un compte actif ou une réinitialisation à un compte en attente/inactif est refusée.
- [ ] Ouvrir le portail depuis un compte actif ; vérifier l’ouverture dans un nouvel onglet, l’identité du compte et la durée de session habituelle.
- [ ] Rejouer le lancement d’impersonation ou réutiliser son jeton ; vérifier que le jeton ne peut pas servir une seconde fois.
- [ ] Vérifier qu’une requête GET ne déclenche ni invitation, ni réinitialisation, ni impersonation, ni suppression.
- [ ] Supprimer un compte de test après confirmation ; vérifier son absence de la liste et la disparition des accès/sessions associés selon le comportement en cascade actuel.
- [ ] Vérifier qu’un compte administrateur interne peut toujours se connecter au backoffice après ces opérations.

## Déroulement interactif

Pour une recette accompagnée, faire les cas dans cet ordre : index/recherche, création avec e-mail existant, création avec nouvelle personne, invitation et activation, édition et accès multiples, réinitialisation, impersonation, puis suppression d’un compte jetable. Cocher chaque ligne après comparaison du résultat observé avec le résultat attendu.
