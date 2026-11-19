# Factures simples et liens des séances

## Besoin approuvé

Ouvrir les dossiers de la liste des séances dans un autre onglet et proposer le lien de carte dans le mail récapitulatif. Conserver le rappel automatique de préparation après la dernière séance confirmée du mois. Le suivi simple distingue les factures à faire des factures envoyées : lorsqu'une facture est envoyée, sa référence, son montant et une description de ce qu'elle couvre sont enregistrés et le rappel correspondant disparaît du mail et des tâches à faire du connecteur. Le connecteur peut lire les factures à faire et enregistrer une facture envoyée, avec confirmation avant écriture.

Les nouvelles factures ne déclenchent ni relance de paiement ni blocage de clôture. Les données et l'historique des anciennes factures et leur fonctionnement de paiement sont préservés. Le paiement sera étudié ultérieurement. Le lien carte reprend Google Maps avec l'adresse de la séance et est présent en versions HTML et texte.

## Choix technique

Étendre le modèle existant en distinguant explicitement les anciens enregistrements avec suivi du paiement des nouvelles factures simples. Continuer à utiliser les préparations existantes pour représenter le travail à faire ; résoudre immédiatement la préparation correspondante lors de l'enregistrement d'une facture envoyée. Encadrer les écritures MCP avec les verrous, validations, confirmation et protections contre les doublons déjà en place. Toute évolution du schéma devra préserver les données existantes.
