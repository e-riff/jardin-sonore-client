# Brouillons et notifications personnelles MCP

## Retrouver et modifier un brouillon

1. Appeler `search_mail_drafts` avec éventuellement `query` et `limit` (1 à 25).
   La recherche reste dans le dossier Brouillons configuré. Les références
   `draft:…:…` désignent un UID dans une version donnée de ce dossier ; elles
   fonctionnent aussi pour les brouillons sans Message-ID. Les traiter comme
   des identifiants opaques, sans les fabriquer.
2. Appeler `read_standalone_mail_draft` avec le `draftMessageId` choisi.
   Malgré son nom historique, cet outil lit les brouillons manuels et MCP.
3. Appeler `update_standalone_mail_draft` avec ce `draftMessageId`, la `revision`
   retournée et uniquement les champs à changer.

Exemple d'édition :

```json
{
  "draftMessageId": "draft:123:456",
  "revision": "<révision retournée par la lecture>",
  "subject": "Proposition d’atelier",
  "body": "Bonjour,\nVoici la proposition mise à jour.",
  "attachments": [
    {"filename": "devis.pdf", "mimeType": "application/pdf", "base64": "<contenu base64>"}
  ]
}
```

Les champs omis sont conservés : texte, HTML, objet, To/Cc/Bcc et pièces jointes.
`recipient` remplace les To par une seule adresse ; `recipients` définit tous les
To. Ne pas fournir ces deux champs ensemble. `cc` et `bcc` sont des listes
d'adresses ; une liste vide les efface. `htmlBody: null` retire le HTML ; du HTML
fourni explicitement est filtré. Le HTML déjà présent est conservé tel quel si
le champ est omis, avec les images intégrées et leurs vrais Content-ID.

`attachments` ajoute des fichiers. `removeAttachmentIds` retire explicitement
les fichiers choisis d'après les identifiants retournés à la lecture. Les corps
binaires ne sont pas retournés au connecteur. Aucun téléchargement depuis une
URL ni lecture d'un chemin serveur n'est accepté.

Limites : 10 fichiers, 5 Mio par fichier et 10 Mio cumulés après décodage. La
création `create_mail_draft` accepte aussi `attachments`. Pour une réponse créée
avec `save_mail_draft`, ajouter ensuite les fichiers par l'éditeur général.

Une modification externe des corps, destinataires, objet, pièces jointes ou du
contexte de réponse invalide la révision. Relire après `conflict`. Après `missing`
ou un identifiant ambigu, rechercher à nouveau et choisir explicitement le bon
brouillon ; ne pas le déduire d'un objet ressemblant. Un remplacement doit être
relu et vérifié avant le retrait de l'ancien UID, sans expunge global.

Les verrous `MCP_DRAFT_COMPOSE_ENABLED` et `MCP_DRAFT_EDIT_ENABLED` restent
nécessaires à l'édition générale. Aucun outil de brouillon n'envoie de mail.

## Résultats des écritures de brouillons

La création et l’édition relisent le contenu stocké avant de répondre `saved`.
L’objet renvoyé vient du message relu, et les accents UTF-8 sont conservés ; seuls
les mots MIME encodés de l’en-tête brut sont décodés. La recherche expose les dates
au format ISO8601 avec le décalage Europe/Paris (hiver/été).

`draftMessageId` est la référence canonique courante `draft:…:…`, et `messageId`
est l’identifiant MIME du message. Les deux formats sont acceptés à la lecture
et à l’édition générale. Un remplacement change d’UID ; utiliser directement la
nouvelle référence retournée, sans refaire systématiquement une recherche.

| État de l’édition générale | Action |
| --- | --- |
| `saved` | Remplacement relu et vérifié, retrait de l’ancien confirmé. Utiliser les nouveaux identifiants et la nouvelle révision. |
| `verification_failed` | L’ancien n’a pas été retiré par l’opération ; inspecter `replacementDraftMessageId` plutôt que créer une autre copie. |
| `replacement_saved_cleanup_pending` | Le nouveau brouillon est vérifié, le retrait de l’ancien reste à confirmer. Relancer exactement les arguments originaux : même source, même révision, mêmes changements. Le remplacement existant sera réutilisé. |
| `uncertain` | Une étape a été interrompue ; `stage` et `replacementMessageId` permettent de la situer. Ne pas reformuler la demande en nouvelle création. |

Les anciennes révisions obtenues avant la correction du décodage doivent être
rafraîchies par une lecture avant toute édition. Les descriptions MCP sont mises
à jour : un rafraîchissement des outils est recommandé pour l’IA ; les noms,
paramètres et authentification ne changent pas.

## Notifications de tâches planifiées

L'outil distinct `send_personal_notification` envoie par le transport du backend.
Le champ `recipient` facultatif permet de choisir une adresse ; s'il est omis,
l'adresse personnelle configurée côté serveur est utilisée. L'expéditeur reste
celui de Jardin Sonore. L'outil n'accède pas à Gmail. Une tâche planifiée autorisée
peut aussi utiliser un destinataire explicite.

Configuration locale ou de production, jamais une adresse personnelle dans le
fichier `.env` versionné :

```dotenv
MCP_PERSONAL_NOTIFICATION_ENABLED=1
MCP_PERSONAL_NOTIFICATION_RECIPIENT=<adresse personnelle confirmée>
# DEFAULT_CONTACT et MAILER_DSN : expéditeur et transport Jardin Sonore existants.
```

Le verrou reste désactivé par défaut. Appliquer la migration de suivi avant
l'activation. Le destinataire local est préparé dans `.env.local` ; aucune
activation ni modification de production n'est automatique.

Exemple de demande :

```json
{
  "requestId": "veille-petite-enfance:2026-11-12",
  "subject": "Veille petite enfance",
  "body": "Voici les informations du jour…",
  "htmlBody": "<h2>Veille du jour</h2><p>Voici les informations…</p>"
}
```

Pour envoyer à une autre adresse, ajouter par exemple
`"recipient": "marie@example.test"` à la demande. Sans ce champ, le mail part vers
l'adresse personnelle configurée.

La clé identifie une occurrence de tâche, pas une tentative d'appel : la conserver
à chaque relance ; en créer une autre pour l'occurrence suivante. Elle contient
1 à 128 caractères ASCII (lettres, chiffres, point, tiret, soulignement, deux-points).
Objet : 255 caractères, texte : 10 000, HTML : 20 000. Le HTML conserve les
paragraphes, titres simples, listes et liens HTTP(S), sans scripts ni images.
Le champ `recipient` accepte une seule adresse email valide (254 caractères
maximum). L'outil n'accepte ni expéditeur libre, ni Cc/Bcc, ni pièces jointes.

Résultats :

| État | Signification |
| --- | --- |
| `accepted` | Transport synchrone terminé ; avec SMTP, le serveur a accepté le mail. Ce n'est pas une preuve de livraison dans Gmail. |
| `sending` | Demande déjà réservée, résultat final non enregistré. Aucun nouvel essai SMTP. |
| `uncertain` | Issue incertaine du transport ou de l'enregistrement du résultat. Aucun renvoi automatique. |
| `conflict` | Clé déjà utilisée avec un contenu, un destinataire ou un expéditeur différents. |
| `rate_limited` | 10 nouvelles demandes déjà réservées dans la dernière heure. |

La base garde clé unique, empreinte, état et dates, sans corps du mail. Le verrou
de réservation est commun aux connexions de la même base, pour que la limite
horaire et le dédoublonnage résistent aux appels simultanés. Les relances connues
ne consomment pas de nouvelle place. Les traces serveur ne contiennent ni corps
ni secret SMTP. Ne pas contourner `sending` ou `uncertain` en changeant de clé.

## Recette et hébergement

- Rafraîchir les outils dans Mistral après déploiement. Autoriser l'outil de
  notification pour les tâches planifiées concernées ; un réglage Mistral qui
  exige une confirmation interactive peut empêcher leur exécution autonome.
- Créer un brouillon manuel, le rechercher, modifier son objet et son texte,
  vérifier To/Cc/Bcc et une pièce jointe dans le logiciel mail, sans l'envoyer.
- Le modifier dans le logiciel mail, puis réessayer avec l'ancienne révision :
  obtenir un conflit ou une référence périmée, rechercher/relire et rééditer.
- Vérifier un ajout et un retrait de fichier, ainsi qu'une image intégrée.
- Après autorisation d'un envoi réel de recette, envoyer une notification vers
  l'adresse personnelle, puis répéter le même appel : un seul mail doit arriver.
- Les requêtes JSON MCP sont limitées à 16 Mio pour laisser passer 10 Mio de
  fichiers encodés en base64. Nginx et PHP ont la même limite POST de 16 Mio ; la
  limite des uploads de fichiers classiques reste à 8 Mio. Recharger les services
  concernés après déploiement de leur configuration. Un proxy d'hébergement peut
  imposer sa propre limite, à contrôler lors de la recette réelle.

Les tests locaux utilisent des transports factices et des tables temporaires de
test. Ils ne prouvent ni la recette IMAP réelle ni la livraison Gmail.


## État de la livraison du 12 novembre 2026

Déployé sous `deploy-mcp-drafts-notifications-20261112-01` (`9c468fb9`).
Migration appliquée, notifications activées vers l’adresse personnelle confirmée,
53 outils MCP exposés. Contrôles HTTP et refus des paramètres non autorisés réussis sans envoi réel ;
JSON de 14 Mio accepté. Recherche IMAP réelle réussie, aucun brouillon présent lors
du contrôle. Rafraîchir les outils Mistral avant la recette d’édition et d’envoi.


Le complément `recipient` facultatif est déployé sous
`deploy-mcp-drafts-notifications-20261112-02` (`91c3114b`), après demande explicite
de l’utilisateur.
Il conserve le destinataire personnel par défaut et inclut la destination effective
dans l’empreinte de dédoublonnage : une même clé ne peut pas être réutilisée pour
une autre adresse. Aucun mail réel n’est nécessaire pour vérifier cette règle.


## Correction accents et recette réelle

Le correctif `deploy-mcp-draft-encoding-20261112-01` (`4e67dd0e`) est déployé.
Recette IMAP réelle réussie : création et deux éditions avec accents,texte multiligne,
HTML et pièce jointe ; un seul brouillon de recette après chaque édition,lecture
immédiate via référence canonique ou Message-ID,date ISO8601 avec décalage correct.
L’en-tête brut est bien encodé MIME. Les seuls messages de recette ont été retirés ;
les brouillons existants et leurs éventuels doublons restent inchangés. Aucun envoi.
Les descriptions MCP corrigées sont exposées ; rafraîchir la liste des outils dans
Mistral pour les récupérer,sans reconnexion nécessaire à l’authentification.
