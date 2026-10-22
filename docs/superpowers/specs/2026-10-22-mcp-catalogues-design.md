# Catalogues métier via MCP — conception

## But

Permettre à l'assistant de retrouver, lire, créer et modifier les fiches utilisées pour préparer les séances ou les mailings : comptines et jeux de doigts du répertoire, instruments, ressources média, prolongements et recommandations du catalogue mailing. Les fiches restent dans leurs catalogues actuels. Ajouter une fiche au catalogue ne l'ajoute pas automatiquement à une séance ni à un mailing.

## Périmètre et vocabulaire

| Catégorie MCP | Modèle existant | Particularités |
| --- | --- | --- |
| `repertoire` | `RepertoireItem` | `nursery_rhyme` ou `fingerplay`, blocs de texte/geste, médias liés, thèmes |
| `instrument` | `Instrument` | Accordage, quantité, notes, tags d'instruments |
| `media` | `MediaResource` | `soundtrack`, `video` ou `link`, URL principale, URL secondaire et image éventuelle |
| `recommendation` | `SessionRecommendation` | Prolongement du catalogue avec texte, liens et image |
| `mailing_recommendation` | `NewsletterRecommendation` | Fiche réutilisable du catalogue mailing, avec titre, tag, texte, lien et image locale |

Une recommandation du catalogue mailing est distincte de sa copie dans une campagne (`MailingRecommendation`). Une recommandation placée dans une séance reste distincte du catalogue des prolongements. La création et l'édition de séances, la suppression de fiches, la gestion des tags et des thèmes et l'envoi de contenu restent hors de ce lot.

Pour `SessionRecommendation`, la seule source d'image à conserver est un fichier local. Les champs `primaryUrl` et `secondaryUrl` sont des liens de contenu et restent disponibles. Cette simplification ne concerne pas les ressources média, dont les URL décrivent leurs sources.

## Interface MCP

- `search_catalog_items` reçoit une catégorie, un texte facultatif, un filtre `activeOnly` et une limite bornée. La réponse contient les UUID, intitulés, types et une courte description, sans retourner tout le contenu de chaque fiche.
- `get_catalog_item` reçoit la catégorie et un UUID exact. Il retourne tous les champs modifiables, les UUID des relations, l'état actif, une URL de backoffice et une révision calculée à partir des valeurs courantes.
- `list_catalog_terms` retourne les thèmes et tags d'instruments avec leurs UUID et libellés. L'assistant ne devine pas ces UUID.
- La création et la modification utilisent dix outils nommés par catégorie (`create_repertoire_item`, `update_repertoire_item`, `create_mailing_recommendation`, etc.). Leurs schémas reprennent les champs des entrées métier existantes et, pour les recommandations mailing, les limites plus courtes du formulaire du catalogue. Une modification exige l'UUID et la révision obtenus par `get_catalog_item`, puis transmet les valeurs complètes de la fiche. L'absence d'une valeur facultative conserve la valeur existante ; `null` explicite l'efface. Les listes de relations remplacent la liste entière quand elles sont fournies.
- Chaque écriture renvoie l'UUID, la nouvelle révision et l'URL de la fiche. Une révision périmée est rejetée avant la sauvegarde. Une erreur de validation nomme le champ concerné ; aucun UUID de relation inconnu n'est ignoré silencieusement.

Les outils appellent les cas d'usage `Create*`, `Update*`, `Search*` et `Get*` existants. Un adaptateur MCP distinct traduit les arguments JSON vers les entrées typées et les vues de chaque catalogue ; les registres MCP actuels gardent la gestion du protocole, des outils de mail et des outils commerciaux. Les recherches sont bornées, notamment pour les instruments dont le catalogue est paginé.

Pour les mailings, `add_catalog_recommendation_to_mailing` reçoit l'UUID exact de la campagne, sa révision et l'UUID de la fiche du catalogue mailing. Après contrôle de la révision et de l'état modifiable, il réutilise `AddNewsletterRecommendationToCampaign`, qui refuse une fiche inactive, évite de rattacher deux fois la même source, copie son contenu et son image et conserve `sourceRecommendationUuid`. L'outil retourne la recommandation ajoutée et la nouvelle révision ; `get_mailing_campaign` expose aussi `sourceRecommendationUuid` pour chaque copie. Le parcours assistant est : chercher une fiche existante, la créer dans le catalogue si nécessaire, lui ajouter une photo si nécessaire, puis la rattacher au brouillon. `create_mailing_draft` ne crée plus de recommandations dans ses arguments ; `update_mailing_draft` peut conserver, ordonner ou retirer les copies existantes, mais ne crée plus de nouvelle recommandation sans source. Le nouvel outil de rattachement est une action distincte et confirmée. Les copies déjà présentes dans un mailing restent des instantanés : une modification ultérieure de la fiche source ne les met pas à jour automatiquement, ce qui préserve le contenu d'un mailing déjà préparé ou envoyé.

## Images et fichiers

Le backoffice accepte déjà un fichier téléversé et le range dans `public/uploads/session/recommendations/`. L'utilisateur veut envoyer **la photo elle-même** pour une recommandation créée ou modifiée via l'assistant, et retirer la saisie d'URL d'image externe. Le parcours MCP doit donc recevoir les octets du fichier, les faire valider côté serveur, puis les enregistrer dans ce même espace local. Le formulaire métier et EasyAdmin n'affichent plus de champ « URL du visuel » : ils montrent l'image actuelle et un champ fichier pour la remplacer.

Dans le domaine, la vue applicative et l'entité Doctrine de `SessionRecommendation`, `imageUrl` devient `imagePath` pour exprimer qu'il s'agit d'un chemin local généré par le stockage. La colonne `session_recommendation.image_url` est renommée `image_path` par migration, sans perdre les chemins locaux déjà enregistrés. L'utilisateur confirme qu'il n'y a aucune image distante dans ces fiches en production et ne demande pas d'audit préalable ; la base locale en contient également zéro. Les anciens instantanés de séquence et les contrats de lecture qui exposent `imageUrl` peuvent conserver ce nom pour compatibilité : ils présentent une URL publique calculée depuis `imagePath`, jamais une nouvelle saisie libre d'URL. La migration ne touche pas `MediaResource.imageUrl` ni les liens de contenu `primaryUrl` et `secondaryUrl`.

Le contrôle reprend la limite actuelle du formulaire : JPEG, PNG ou WebP, 5 Mo maximum. Il vérifie la taille réelle et le contenu détecté, pas seulement l'extension ou le MIME annoncé ; il rejette les fichiers vides, tronqués ou non décodables. Le nom du fichier stocké est généré côté serveur. En cas de rejet, la recommandation et son ancien visuel restent inchangés. Une modification sans nouveau fichier conserve l'image ; `clearImage: true` demande explicitement son retrait de la fiche, sans effacer automatiquement un ancien fichier local.

Le connecteur Mistral peut, selon le retour de l'assistant utilisé, fournir les octets d'une image en Base64 après les avoir obtenus depuis Drive ou la génération d'images. Le contrat MCP reçoit donc `data` (Base64 strict, sans URL ni `data:`), `filename` (nom informatif uniquement) et l'identifiant exact de la recommandation à illustrer. Le serveur refuse un argument encodé trop grand avant décodage, décode strictement, vérifie le fichier temporaire avec la même limite de 5 Mo et les mêmes formats que le backoffice, puis réutilise le stockage local existant. Le plafond HTTP actuel de 10 Mo couvre une image de 5 Mo encodée en Base64 (environ 6,7 Mo) ; la limite effective du connecteur Mistral reste à vérifier sur un vrai appel.

L'image est attachée à une recommandation existante, après sa création, par un outil d'écriture dédié `set_catalog_recommendation_image`. Il exige l'UUID de la fiche et sa révision courante, puis retourne le chemin local et la nouvelle révision. Cette séquence évite de faire accepter un chemin de fichier arbitraire dans `create_recommendation` ou `update_recommendation`. Le fichier temporaire est nettoyé dans tous les cas ; si l'enregistrement métier échoue après l'écriture du nouveau fichier public, ce dernier est aussi retiré. L'image précédente reste liée à la fiche tant que la nouvelle n'est pas enregistrée ; son ancien fichier public n'est pas supprimé automatiquement. L'outil demande la même confirmation explicite côté client que les autres écritures.

Le catalogue mailing utilise le même trajet Base64, mais un outil `set_mailing_catalog_recommendation_image` distinct : UUID exact de la fiche du catalogue, sa révision, `data` et `filename`. Il range l'image dans `uploads/mailing/recommendations/`, l'attache à la fiche source et renvoie le nouveau `imagePath` et sa révision. L'assistant ajoute ensuite cette fiche au brouillon avec `add_catalog_recommendation_to_mailing` : la copie reçoit l'image existante. `imagePath` dans les autres outils reste une valeur de lecture ou de conservation, jamais une consigne pour adopter un chemin inconnu. Les images des blocs d'introduction du mailing restent hors de ce lot.

Pour les ressources média, le MCP gère les URL et préserve les fichiers déjà associés lors d'une modification. Le téléversement de nouveaux fichiers audio, vidéo ou image pour ces ressources reste dans le backoffice.

## Autorisation et confirmation

Les lectures restent disponibles avec le jeton MCP existant. Les huit écritures des catalogues de séance et `set_catalog_recommendation_image` utilisent un paramètre serveur `MCP_CATALOG_WRITE_ENABLED`, désactivé par défaut dans la configuration versionnée et activé explicitement dans l'environnement de production au moment de la livraison. Les deux écritures du catalogue mailing, `set_mailing_catalog_recommendation_image` et `add_catalog_recommendation_to_mailing` réutilisent `MCP_MAILING_WRITE_ENABLED` ; le rattachement contrôle aussi que la campagne reste modifiable. Leurs descriptions MCP demandent une confirmation explicite de l'utilisateur avant chaque appel et leurs annotations les déclarent comme écritures. Dans le connecteur utilisé par l'assistant, chaque nouvel outil d'écriture est réglé sur « Demander » ; le serveur MCP ne peut pas déduire à lui seul le clic d'approbation de l'utilisateur à partir du jeton Bearer.

## Comportements à vérifier

- Rechercher puis lire chacune des cinq catégories, y compris les fiches inactives et les relations par UUID.
- Créer et modifier une fiche dans chaque catégorie sans modifier une autre fiche ou une séance.
- Refuser une révision périmée, un type inconnu, un UUID de relation introuvable et une image non conforme ou trop volumineuse.
- Confirmer que les écritures sont refusées quand le paramètre serveur est désactivé, et que chaque outil demande l'accord du client quand il est activé.
- Créer et modifier une recommandation du catalogue, puis lui attacher une photo réellement envoyée et stockée localement ; conserver l'ancien visuel si le décodage, la validation ou l'écriture échoue.
- Rechercher ou créer une recommandation du catalogue mailing, lui attacher une photo, puis la rattacher par UUID à un brouillon existant ; vérifier `sourceRecommendationUuid` et l'image copiée. Refuser le rattachement après le début d'un envoi.
- Vérifier le trajet Base64 sur un vrai appel du connecteur Mistral, avec une petite image et un fichier proche du plafond autorisé.
- Vérifier que les deux formulaires de prolongement n'acceptent plus d'URL d'image externe, que les chemins locaux survivent à la migration et que les liens de contenu restent utilisables.
