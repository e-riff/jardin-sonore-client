# Recommandations mailing

Périmètre demandé : historique des utilisations fiable, date de création visible et tri décroissant par création ; 420 caractères visibles (confirmation utilisateur), URL des liens exclue du compteur ; bouton de lien dans le texte des recommandations ; images centrées verticalement dans les recommandations et blocs latéraux des mails. Conserver les liens principaux et les instantanés de campagne. Appliquer la limite dans les formulaires et MCP. Réutiliser le rendu HTML sûr existant et préserver les anciennes descriptions texte. Pas de migration ni de réparation inventée de dates.

- [x] Reproduire puis corriger la recherche des utilisations par UUID binaire ; tester dates et tri du catalogue.
- [x] Tester puis ajouter le compteur visible, la validation à 420, l’éditeur de lien, les rendus mail HTML/texte et le contrat MCP.
- [x] Centrer les images par attribut et style inline ; vérifier le rendu généré.
- [x] Mettre à jour la roadmap : séances/PDF clos pour le moment ; rappels généraux légers backoffice/MCP à cadrer, sans les implémenter dans ce lot.
- [x] PHPUnit ciblé et complet, style, PHPStan, Twig, compilation assets et recette navigateur sans envoi réel.

Diagnostic : test de stockage/relecture d’utilisations en base de test en échec ; la requête IN transmet les UUID objets sans conversion binaire.

Résultats : 35 tests ciblés / 224 assertions ; suite complète 766 tests / 4 060 assertions sans échec, 2 dépréciations et 15 notices PHPUnit ; PHPStan, PHP CS Fixer, lint Twig (23 fichiers), lint du conteneur et compilation de 86 assets valides. Les assets compilés ont été déplacés vers `/tmp/jardin-mailing-recommendations-assets-20261126` pour préserver le service dynamique des assets en développement.

Recette agent-browser sur le vrai champ rendu par Twig, monté sur la page locale sans authentification ni soumission : contrôleur connecté, lien créé, modification sans liens imbriqués, URL exclue du compteur, Unicode à 420 valide, 421 signalé, schéma javascript refusé. Aucun mail envoyé ni donnée créée par le navigateur. Les tests d’historique utilisent uniquement la base de test ; aucun changement de schéma.

Rappels : l’utilisateur confirme leur inclusion dans le mail du matin ; piste conservée dans la roadmap, pas d’implémentation dans ce lot. Séances/PDF clos pour le moment. Pas de commit ou de déploiement de ce lot.

Revue indépendante : un défaut P2 détecté dans les descriptions MCP sans texte visible. Corrigé avec tests RED→GREEN (validation MCP et formulaires, y compris lien contenant uniquement une espace) ; 9 tests / 24 assertions, puis suite complète 766 tests / 4 060 assertions sans échec. Dernier contrôle ciblé du champ : retrait du retour source ajouté par nl2br pour éviter le double comptage des sauts de ligne ; régression reproduite puis corrigée, 11 tests / 44 assertions passent. PHPStan et style relancés après cette dernière correction. Aucun autre défaut concret en revue.
