# Introduction des mailings en blocs et préheader indépendant

## Intention

Composer la première partie d'un mailing sans écrire de balises HTML à la main. L'introduction doit accepter plusieurs paragraphes et plusieurs images, placés dans l'ordre voulu, tout en produisant un email lisible sur ordinateur et téléphone. Le préheader affiché par les messageries doit être éditable séparément du sous-titre visible dans l'email.

Ce chantier concerne l'introduction entre le titre et les recommandations. La bannière, le bouton d'appel à l'action, les recommandations, l'audience et l'envoi conservent leur place et leur fonctionnement.

## Choix de conception

Un éditeur de blocs guidés est retenu. Un champ HTML libre offrirait plus de commandes mais rendrait le résultat difficile à garantir dans les clients email. Un simple assistant insérant des balises dans la zone de texte actuelle ne résoudrait pas la difficulté de composer et déplacer les images.

Les blocs disponibles sont :

1. **Paragraphe** : texte avec gras, italique, lien et couleurs limitées à la palette Jardin Sonore.
2. **Image** : image sur toute la largeur utile de l'introduction.
3. **Paragraphe avec image** : texte et image côte à côte, avec image à gauche ou à droite.

L'utilisateur peut ajouter, supprimer et réordonner les blocs. Plusieurs blocs peuvent contenir une image. Chaque image appartient à un seul bloc ; une image informative reçoit un texte alternatif, une image décorative peut être signalée comme telle. Les contrôles de mise en forme restent limités à ceux que le rendu email prend réellement en charge. Le collage de texte ne doit pas importer une mise en page ou des styles arbitraires.

L'édition reste visuelle et l'aperçu du mailing utilise le même rendu serveur que l'envoi. Les blocs côte à côte s'empilent sur les petits écrans. Le bouton d'appel à l'action reste après tous les blocs de l'introduction.

## Préheader

Un champ facultatif « Préheader » figure près de l'objet de l'email dans les formulaires de création et d'édition. Il ne modifie pas le sous-titre affiché dans le corps de l'email. L'aperçu montre le préheader qui sera envoyé. Pour une campagne créée ou enregistrée avec le nouvel éditeur, un champ vide produit un extrait du premier paragraphe non vide, sans balises ni couleur ; le sous-titre n'est pas utilisé comme source de repli. Une campagne historique non convertie conserve son calcul actuel du préheader à partir du sous-titre, puis du texte principal. Cette exception protège le rendu des emails existants.

## Données et compatibilité

Une migration de schéma ajoute à `mailing_campaign` un préheader nullable et une liste ordonnée de blocs nullable. Elle ne réécrit pas les contenus existants. `main_text` reste présent et obligatoire en base pour préserver les anciens enregistrements et leurs usages actuels.

Une campagne dont la liste de blocs est nulle continue à être rendue avec `main_text` par le formateur actuel, en HTML comme en texte. Cela inclut les campagnes déjà envoyées : leur contenu historique reste inchangé, sans conversion implicite à la lecture ni lors de la migration. Pour un ancien brouillon ouvert dans le nouvel éditeur, le texte historique est présenté comme un bloc paragraphe initial. La conversion en blocs n'est enregistrée qu'après une sauvegarde explicite et valide ; un formulaire annulé ou invalide ne change rien. Après cette sauvegarde, les blocs deviennent la source du rendu et un texte de synthèse dérivé des paragraphes est maintenu dans `main_text` pour les usages hérités. Au moins un paragraphe non vide est requis pour un nouveau contenu.

Chaque bloc porte un identifiant stable, un type et seulement les données utiles à ce type. Les chemins d'image sont des chemins locaux gérés par le backend, jamais du HTML fourni par le navigateur. Les images sont déposées dans un répertoire dédié sous `public/uploads/mailing/`, conservé lors des déploiements. La suppression ou le remplacement d'une image est coordonné avec l'enregistrement de la campagne, afin qu'un formulaire invalide ne fasse pas perdre l'image existante. Les campagnes déjà envoyées restent non éditables.

## Rendu et sécurité

Le backend valide la structure des blocs et assainit le contenu riche selon une liste fermée : gras, italique, liens HTTP(S) et couleurs autorisées. Les URL et les chemins d'image sont validés indépendamment. Le HTML email est produit par Twig avec les tableaux et styles adaptés aux clients email ; aucune balise libre fournie par l'éditeur n'est insérée directement dans le message. La version texte conserve les paragraphes et les liens, sans dépendre de l'affichage des images. Les images disposent d'URL absolues accessibles aux destinataires.

## Vérification attendue

- Une campagne héritée non modifiée garde exactement le rendu HTML et texte de son introduction. Un ancien brouillon peut être ouvert puis abandonné sans conversion ; une sauvegarde valide crée ses blocs sans perte de texte ni de mise en forme autorisée.
- L'ajout, le déplacement, l'édition et la suppression de blocs et d'images sont reflétés dans l'aperçu, l'email HTML et, pour le texte, dans la version texte.
- Les couleurs et liens autorisés passent ; le collage ou la soumission de HTML non autorisé ne produit pas de code actif dans l'email.
- Les blocs avec image à gauche et à droite restent lisibles sur écran étroit et dans les clients email visés par la recette.
- Un préheader renseigné est utilisé tel quel après validation ; un champ vide prend le premier paragraphe sur les nouveaux contenus. Une campagne historique non convertie garde son ancien préheader calculé.
- La migration ne change que les colonnes prévues et le stockage des images survit au déploiement.
