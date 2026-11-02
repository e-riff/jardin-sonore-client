# Bilan mailing — plan d’implémentation

**Objectif :** afficher par campagne les états des envois et deux listes consultables sans charger toute l’audience.

**Approche :** utiliser les statuts existants, une lecture SQL paginée et deux composants Live indépendants. Les structures sont rapprochées par les liens actuels de l’annuaire et du portail ; ce rapprochement n’est pas un historique figé. Les adresses reçues individuellement restent dans la liste des adresses.

- Tester les comptes, les pages, la recherche, l’isolation entre campagnes et les adresses partagées ; tester les liens actuels et l’absence de structure.
- Ajouter les requêtes en lecture seule, les composants paginés, la page de bilan et son accès depuis la campagne ; traduire les textes.
- Vérifier l’authentification, la compatibilité mobile, les campagnes vides et les erreurs ; lancer les contrôles backend et la compilation des assets.
- Mettre à jour la roadmap selon le résultat effectif. Aucune migration ni modification des données d’envoi.
