# Pièces jointes sur les brouillons et envois MCP

## Demande et constat

Permettre au connecteur de joindre des fichiers aux mails et de retirer les
pièces jointes d'un brouillon, y compris pour les réponses à tous.

Contrôle HTTP authentifié du 18 novembre 2026 : 53 outils exposés en production.
`save_mail_draft` expose bien `replyAll`. `create_mail_draft` expose `attachments` ;
`update_standalone_mail_draft` expose `attachments` et `removeAttachmentIds` pour
tous les brouillons IMAP, manuels ou MCP. Une réponse peut donc déjà recevoir des
fichiers dans un second appel. `update_mail_draft` conserve les fichiers existants
mais ne permet pas de les ajouter ou de les retirer directement.
`send_personal_notification` n'accepte aucun fichier.

Les 36 tests ciblés existants passent, avec 176 assertions. Ce contrôle ne constitue
pas une recette IMAP réelle ni un contrôle du cache du client Mistral.

## Approches

1. Conserver les deux appels pour les réponses et compléter seulement l'envoi
   direct : modification minimale, mais parcours de réponse moins homogène.
2. Compléter les outils mail existants avec le même contrat de pièces jointes :
   choix recommandé, sans nouvel outil ni nouveau mécanisme de stockage.
3. Créer un service d'upload et des références de fichiers réutilisables : utile
   pour d'autres sources de fichiers, mais dépasse le besoin immédiat et demande
   des règles de stockage et de durée de conservation supplémentaires.

## Proposition à valider

- Ajouter `attachments` à `save_mail_draft`, en réponse simple ou à tous.
- Ajouter `attachments` et `removeAttachmentIds` à `update_mail_draft` ; conserver
  les fichiers omis et vérifier la révision avant tout remplacement.
- Ajouter `attachments` à `send_personal_notification`. Inclure le contenu des
  fichiers dans l'empreinte de dédoublonnage : une même clé avec un fichier changé
  doit produire un conflit, sans nouvel envoi.
- Réutiliser filename/mimeType/base64, les validations et les limites actuelles :
  10 fichiers, 5 Mio par fichier, 10 Mio au total. Valider avant réservation ou
  écriture. Aucun chemin serveur ni téléchargement arbitraire d'URL.
- Conserver l'éditeur général comme moyen de modifier tous les brouillons et
  d'ajouter ou retirer des fichiers sans modifier le corps du message.
- Préciser dans les descriptions et la documentation la conservation des fichiers,
  le parcours de réponse à tous et le rafraîchissement des outils du connecteur.

Le périmètre proposé porte sur les brouillons mail IMAP et l'envoi direct déjà
existant. Les campagnes de mailing sont un modèle distinct, sans outil MCP d'envoi
ou de pièces jointes ; leur extension demanderait un périmètre séparé.
Un message déjà envoyé ne peut pas perdre rétroactivement sa pièce jointe.

## Vérification et livraison

Tests des paramètres MCP, construction MIME, ajout/retrait et conservation des
fichiers, réponse à tous avec fichier, validation avant écriture et dédoublonnage
des notifications. Puis PHPUnit backend, style, PHPStan et conteneur Symfony.
Pas de migration attendue. Aucun envoi réel de recette. Mise en ligne autorisée après validation, avec commit, tag et push ; contrôle de production limité à HTTP et aux schémas MCP, à la demande de l’utilisateur.

Statut : conception approuvée le 18 novembre 2026 ; implémentation et livraison suivies dans le plan associé.
