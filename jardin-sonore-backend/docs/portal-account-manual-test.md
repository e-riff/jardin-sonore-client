# Test manuel — comptes portail

Prérequis : être connecté au back-office avec un compte administrateur et ouvrir Mailpit (`http://localhost:8025`). Pour chaque nouvel essai, utiliser une adresse e-mail inédite afin d’éviter la contrainte d’unicité des comptes portail.

## Création et invitation initiale

- [ ] Depuis **Comptes portail**, créer un accès depuis une structure avec un e-mail déjà rattaché à cette structure. Vérifier l’état `En attente` et l’accès structure associé.
- [ ] Créer un autre accès avec une nouvelle personne et un nouvel e-mail. Vérifier que la personne, son e-mail et son accès structure sont bien persistés.
- [ ] Vérifier dans Mailpit que l’invitation est envoyée automatiquement après chacune de ces créations, au bon destinataire.
- [ ] Vérifier l’objet, le texte sur les futures séances d’éveil musical de la structure, le bouton et le rappel que le lien est personnel et à usage unique.
- [ ] Vérifier que l’URL du bouton utilise le domaine configuré par `DEFAULT_URI` (en local : `https://admin.jardin-sonore.local:8443/portail/definir-mot-de-passe/...`), jamais `portail.example.test`.

## Définition du mot de passe et activation

- [ ] Ouvrir le lien reçu sans être connecté au back-office : la page doit être accessible publiquement.
- [ ] Vérifier l’apparence du formulaire et que son envoi ne bascule pas via Turbo.
- [ ] Soumettre un mot de passe de moins de 12 caractères : la page doit rester affichée avec le message explicite correspondant.
- [ ] Soumettre deux mots de passe différents : la page doit rester affichée avec le message d’égalité des mots de passe.
- [ ] Soumettre deux fois le même mot de passe d’au moins 12 caractères : vérifier la page de succès, l’état `Actif` dans le back-office et la consommation du lien.
- [ ] Réouvrir le lien consommé : vérifier la même réponse neutre `Ce lien n’est plus disponible.`

## Renvoi d’invitation et réinitialisation

- [ ] Sur un compte `En attente`, cliquer sur **Envoyer l’invitation** depuis l’index, le détail, puis l’édition. Vérifier à chaque fois le retour sur la page d’origine, sans redirection vers l’accueil EasyAdmin.
- [ ] Conserver le lien A, renvoyer l’invitation, puis conserver le lien B. A doit être indisponible et B doit permettre l’activation.
- [ ] Sur un compte `Actif`, cliquer sur **Réinitialiser le mot de passe**, ouvrir le message Mailpit et définir un nouveau mot de passe valide. Vérifier la page de succès et que le compte reste `Actif`.
- [ ] Vérifier qu’un lien de réinitialisation consommé ne peut pas être réutilisé.
- [ ] Tester un jeton inexistant et un jeton expiré. Vérifier que les liens inconnu, expiré, invalidé ou consommé présentent exactement la même réponse neutre.

## États, structures et incidents d’envoi

- [ ] Vérifier qu’un compte `En attente` n’affiche que l’invitation, qu’un compte `Actif` n’affiche que la réinitialisation et qu’un compte `Inactif` n’affiche aucune de ces actions (index, détail, édition).
- [ ] Ajouter une deuxième structure à un compte. Vérifier que la suppression d’un accès est permise lorsqu’il en reste un autre, mais que la suppression du dernier accès est refusée sans supprimer la relation.
- [ ] Provoquer temporairement un échec SMTP dans un environnement sûr. Après une création, une invitation ou un reset, vérifier l’absence d’écran d’erreur, le flash indiquant que le lien a été généré mais que l’e-mail n’a pas pu être envoyé, puis la possibilité de renvoyer un nouveau lien.
- [ ] Pendant une campagne newsletter en cours, créer ou renvoyer une invitation, puis un reset. Vérifier dans Mailpit que ces e-mails transactionnels arrivent immédiatement : ils sont envoyés directement et ne passent pas par la file Messenger de campagne.

## Login administrateur

- [ ] Se déconnecter, recharger `/login`, se reconnecter et vérifier que le CSRF fonctionne toujours.
- [ ] Vérifier que le formulaire de connexion reste soumis hors Turbo et que le retour au back-office fonctionne après rechargement/cookies.
