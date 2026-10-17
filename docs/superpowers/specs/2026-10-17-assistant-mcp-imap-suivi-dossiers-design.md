# Assistant MCP IMAP pour le suivi des dossiers

**État :** conception relue et approuvée par l'utilisateur le 17 octobre 2026. Un compte Google professionnel distinct sera créé pour Drive ; la messagerie Jardin Sonore reste sur cPanel et demeure accessible avec tout client IMAP. L'assistant envisagé est Mistral Vibe Work, avec un connecteur MCP personnalisé.

## Objectif

Le matin, l'utilisateur demande à l'assistant d'examiner les nouveaux échanges de la boîte Jardin Sonore. L'assistant identifie les dossiers commerciaux concernés, propose pour chacun un résumé à inscrire dans le fil et, si une réponse est utile, prépare un brouillon dans la boîte mail. L'utilisateur valide chaque écriture et envoie lui-même les messages depuis son client mail.

Le contenu intégral des mails reste dans la boîte cPanel. Le suivi commercial conserve un résumé et une référence au message source, selon le choix de l'utilisateur. Les messages lus pour la tâche sont transmis à Mistral afin qu'il puisse les analyser.

## Approches examinées

| Approche | Atout | Coût ou limite |
| --- | --- | --- |
| Connecteur Gmail avec transfert depuis cPanel | Essai immédiat du connecteur prêt à l'emploi | Copie de la boîte professionnelle chez Google ; dépendance au parcours Gmail |
| Serveur MCP IMAP tiers hébergé séparément | Réutilisation de fonctions de lecture et de brouillon existantes | Service supplémentaire à exploiter ; fonctions parfois trop larges ; compatibilité avec l'offre cPanel à vérifier |
| Endpoint MCP dans le backend Symfony existant | Même hébergement HTTPS et accès direct aux services des dossiers ; fonctions limitées au besoin | Implémentation du transport MCP et de l'adaptateur IMAP |

**Choix :** un endpoint MCP dans le backend Symfony. Un projet IMAP existant, notamment `ni-c/imap-mcp`, peut servir de référence de comportement, sans être exposé tel quel. Cette décision évite de rendre nécessaire un service Node permanent. Le backend déclare PHP 8.4 ; une montée à PHP 8.5 n'est pas requise pour ce lot.

## Frontières et flux

```text
Mistral Vibe Work
    │ HTTPS + authentification dédiée
    ▼
Endpoint MCP du backend Jardin Sonore
    ├── adaptateur IMAP TLS ── boîte mail cPanel
    └── services commerciaux ── dossiers, contacts, fil d'événements

Client mail de l'utilisateur ── IMAP TLS ── même boîte cPanel
```

Le MCP expose une petite liste stable d'outils. Il répond à la demande de l'utilisateur ; aucun balayage planifié de la boîte n'est nécessaire. Le connecteur Google Drive professionnel reste distinct. Le calendrier Google et les cron existants ne sont pas concernés.

### Outils de la première version

1. `search_mail`: rechercher les messages de la boîte professionnelle par période, expéditeur et termes, avec limites de résultats et sans récupérer toute la boîte.
2. `read_mail_thread`: lire les messages nécessaires à un échange, avec métadonnées et corps texte ; signaler les pièces jointes sans les transférer automatiquement.
3. `search_projects`: rechercher des dossiers candidats à partir du contact, de l'organisation et du contexte ; retourner les candidats et les éléments utiles à la décision.
4. `record_mail_summary`: après validation explicite, inscrire dans le dossier choisi un résumé éditable avec date, expéditeur, objet et identifiant de source. L'identifiant sert à retrouver et dédupliquer le message ; il ne promet pas un lien universel ouvrable dans un client mail.
5. `save_mail_draft`: après validation explicite, enregistrer une réponse comme brouillon dans le dossier IMAP effectivement utilisé par le client mail de l'utilisateur, avec les en-têtes de réponse permettant de rattacher l'échange.

Le MCP n'expose aucune fonction d'envoi de mail, de suppression de message, de gestion des comptes cPanel ou de modification du calendrier. Les outils de lecture arrivent en premier ; les deux outils d'écriture sont ajoutés seulement après validation du trajet complet de lecture et du dossier « Brouillons ».

## Validation humaine et cohérence

L'assistant présente pour chaque message la source, le dossier candidat, le résumé et le projet de réponse. Il ne choisit pas silencieusement un dossier ambigu et ne crée pas de dossier ou de contact dans cette version. L'utilisateur peut corriger le dossier, le résumé et le brouillon avant leurs écritures respectives. Les fonctions d'écriture restent en approbation manuelle dans Mistral.

Le backend vérifie l'existence du dossier choisi, la cohérence de la référence du mail et les tailles autorisées. Il garde une référence unique du message importé afin qu'une demande répétée ne crée pas une seconde entrée dans le fil. Le résumé conserve le lien avec l'événement commercial créé. L'identité du mail combine le compte, le `Message-ID` quand il est exploitable et une référence IMAP de secours ; elle ne dépend pas du seul numéro de message dans un dossier. Une référence de brouillon empêche aussi une relance de créer plusieurs brouillons identiques. Si le brouillon échoue après l'inscription du résumé, l'assistant signale l'échec ; les deux opérations restent distinctes et relançables sans duplication.

## Sécurité et confidentialité

- L'endpoint MCP exige HTTPS et une authentification dédiée révocable. Le secret n'est pas stocké dans Git ni transmis dans les journaux.
- Les identifiants IMAP restent dans la configuration serveur locale. L'adaptateur se connecte uniquement à la boîte Jardin Sonore avec validation du certificat TLS.
- Le serveur borne les recherches, la taille des corps retournés et les appels ; il journalise les opérations sans contenu de mail ni identifiants secrets.
- Les messages reçus sont des données non fiables : aucune instruction trouvée dans un mail ne donne de droits supplémentaires au MCP.
- Le contenu d'un message lu peut entrer dans la conversation Mistral. La séparation des comptes Google et la limitation des outils ne suppriment pas ce traitement distant.

## Déploiement progressif et critères de réussite

1. Vérifier que le compte Mistral gratuit peut réellement utiliser un MCP personnalisé dans Vibe Work. Le formulaire d'ajout est déjà visible dans Studio, mais le parcours complet n'a pas été essayé. Ne choisir un abonnement payant qu'après avoir constaté une limite effective du forfait gratuit ou un besoin de capacité supérieur ; consulter alors l'offre du moment.
2. Vérifier sur l'hébergement que le backend peut répondre au dialogue MCP sur une URL HTTPS authentifiée et se connecter à la boîte IMAP cPanel. Confirmer le client mail choisi et son dossier « Brouillons ».
3. Livrer la recherche et la lecture IMAP ainsi que la recherche de dossiers, puis faire une recette sur quelques échanges professionnels connus, sans écriture.
4. Ajouter l'inscription d'un résumé validé dans le fil avec déduplication, puis la création d'un brouillon validé ; vérifier que le brouillon apparaît dans le client mail choisi.
5. Essayer la demande matinale complète dans Mistral, y compris un mail sans dossier évident, un doublon et un échec IMAP. Vérifier qu'aucun outil ne permet l'envoi.

## Point de reprise et responsabilités

- **Utilisateur :** créer le compte Google réservé à Jardin Sonore et y placer les documents professionnels destinés au Drive. Choisir, si souhaité, un client mail IMAP plus agréable que Roundcube ; garder l'accès à la boîte cPanel.
- **Codex, lundi :** commencer par un petit endpoint MCP de lecture afin de vérifier le dialogue HTTPS avec Mistral et les contraintes cPanel, puis raccorder l'IMAP et les recherches métier. La suite des écritures dépend de cette recette et du dossier « Brouillons » du client choisi.
- **Compte Mistral :** l'utilisateur dispose déjà d'un compte gratuit. L'abonnement payant n'est pas un prérequis confirmé à ce jour ; vérifier l'usage de l'endpoint MCP dans Vibe Work avant d'acheter.

Références : [connecteurs MCP Mistral](https://docs.mistral.ai/vibe/work/connectors/mcp-connectors), [tarifs Mistral](https://mistral.ai/pricing/), [configuration IMAP cPanel](https://docs.cpanel.net/cpanel/email/set-up-mail-client/).

La mise en production nécessite les vérifications habituelles du projet, puis un commit, un tag et leur publication avant le déploiement conformément aux consignes du dépôt. Les secrets de production sont posés par un canal séparé.
