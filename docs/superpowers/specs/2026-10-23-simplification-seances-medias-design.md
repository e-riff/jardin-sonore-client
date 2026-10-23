# Simplification des séances, du répertoire et des médias — conception

## Intention

Composer une séance avec moins de saisie et moins d'étapes, tout en retrouvant facilement les bons médias parmi des titres parfois identiques. Le répertoire reste édité dans ses fiches dédiées ; une séquence libre est propre à sa séance. Les ressources média sont réutilisables depuis leur catalogue.

## Périmètre et ordre

1. Simplifier les séquences et leur compositeur.
2. Revoir le catalogue média et le rattachement des médias au répertoire et aux séquences libres.
3. Harmoniser les sélecteurs de structures dans le backoffice et EasyAdmin, comme chantier distinct.

La lecture et la création de séances via MCP, la publication, les notifications et la page de collaborations suivent le plan de reprise existant. La refonte générale de l'architecture des séances n'appartient pas à ce chantier.

## Approches examinées

- **Évolution progressive des stockages actuels — retenue.** Garder le répertoire et les séquences dans leurs emplacements actuels, enrichir leurs liens média selon leurs besoins, et partager le dialogue de sélection et de création. Les anciennes données restent lisibles pendant la transition. C'est l'approche choisie pour limiter la portée du changement.
- **Structure relationnelle unique pour tous les liens média.** Uniformiser davantage le stockage, au prix d'une migration et d'une refonte plus larges des parcours existants et du contrat MCP.

## Séquences de répertoire

- L'ajout au déroulé choisit une fiche du répertoire. La séance conserve uniquement ses informations locales : repère dans le déroulé, instruments et consignes ajoutées pour cette séance. La fiche source et ses médias restent gérés dans l'édition du répertoire.
- Supprimer de ce parcours les commandes d'ajout de vidéo YouTube, de rattachement d'un média du catalogue et de création d'un média. Retirer la mécanique propre à ces commandes une fois les anciennes séances prises en compte.
- Chaque carte issue du répertoire propose un lien explicite « Modifier la fiche du répertoire », ouvrant sa fiche dans un nouvel onglet. La séance reste ouverte. Le lien apparaît seulement si la source existe encore ; les consignes locales continuent de s'éditer depuis la séance.
- Les paroles et gestes de la fiche restent synchronisés depuis le répertoire. Dans le compositeur, leurs détails longs sont repliés sous un dépliant « Paroles et gestes », avec indication de présence. Les instruments et les consignes locales restent visibles plus haut.

## Séquences libres

- Utiliser le terme « séquence libre » dans l'interface.
- Créer et modifier la séquence depuis un seul formulaire en surimpression, sans créer un brouillon caché puis ouvrir un second formulaire. Le titre est nécessaire ; le repère, les instruments, les consignes, les paroles et les médias sont facultatifs. Le formulaire place les instruments avant les contenus longs et replie les paroles et gestes.
- Présenter un seul champ facultatif « Repère dans le déroulé » pour les séquences libres et celles du répertoire. Il accepte du texte libre et propose quelques exemples comme « Ouverture et recentrage », « Manipulation » ou « Retour au calme ». Les anciens rôles restent affichés dans ce champ. Le type technique nécessaire à la source et au rendu reste déterminé en interne ; il ne constitue plus un second choix demandé à l'utilisateur.
- Une séquence libre rattache des ressources du catalogue par leur identifiant. Leur choix suffit à les inclure dans la séance : aucun réglage supplémentaire de visibilité par lien. Décrocher une ressource ne la supprime pas du catalogue. Modifier une ressource du catalogue actualise les séances qui la référencent.
- Ouvrir la recherche ou la création d'un média depuis une séquence libre non enregistrée conserve les autres champs déjà saisis. L'enregistrement de la séquence est l'action qui l'ajoute au déroulé ; fermer le formulaire avant cet enregistrement ne laisse pas de séquence vide. Un média créé dans son catalogue reste disponible même si la saisie de la séquence est abandonnée.

## Catalogue média et associations

- Conserver un écran dédié à la gestion du catalogue média, avec création et édition également possibles depuis le dialogue d'association.
- Un média porte un titre non unique, un type d'usage, une source facultative, un fichier **ou** une URL principale, les thèmes/tags existants et un état global « actif ». Les types visés sont « Musique », « Fond sonore », « Partition », « Vidéo » et « Autre ». La nature fichier ou lien est distincte du type d'usage. Retirer du formulaire les champs de description, de lien secondaire et de visuel après avoir assuré la lecture des données historiques concernées.
- L'état global inactif coupe l'affichage du média dans tous ses contextes. Dans une fiche du répertoire, chaque association a en plus un choix « Afficher pour cet élément », activé par défaut. Ce choix appartient au lien, pas au média. Il n'existe pas pour les séquences libres.
- Un média masqué sur une fiche du répertoire reste visible comme association dans l'édition interne, avec son état indiqué. Il est exclu des rendus transmis aux structures, y compris l'aperçu destiné au partage et le PDF. Parmi les médias liés, seul un média visible et actif peut devenir le média principal affiché.
- Depuis la fiche du répertoire, un dialogue permet de voir et prévisualiser les médias liés, d'en rattacher, d'en décrocher et d'en créer. Il réutilise le style des dialogues du backoffice. Le dialogue des séquences libres reprend la même recherche, la même prévisualisation et les mêmes actions de rattachement ; seul le réglage propre au répertoire y est absent.
- Les médias déjà liés au répertoire restent visibles lors de la conversion de la liste actuelle d'UUID vers des liens dotés d'un état de visibilité. Les anciennes copies de médias stockées dans les séquences continuent d'être lisibles ; les nouveaux rattachements utilisent la référence au catalogue.
- Le sélecteur présente systématiquement un libellé distinctif du type « 234 — Sui lo ruin — Partition — Source ». L'identifiant numérique lisible distingue les titres identiques ; l'UUID reste l'identifiant technique des liens. Une indication courte du domaine ou du fichier peut compléter le résultat. La recherche couvre titre, type, source, numéro et URL, sans afficher de longues URL dans chaque ligne. Les catégories historiques sont converties vers les nouveaux types sans changer leurs fichiers ou leurs liens.

## Sélecteurs de structures

- Partout où une structure est sélectionnée, viser le même libellé « Nom — code postal — ville » et une recherche sur ces trois éléments. Le code postal vient de la commune ou de l'adresse pertinente, dont le code INSEE identifie la commune ; il ne faut pas afficher le code INSEE à la place du code postal.
- Partager la construction des libellés et la logique de recherche lorsque les règles métier concordent. Les composants EasyAdmin et les formulaires du backoffice peuvent conserver leurs mécanismes d'interface propres. Examiner les cas où plusieurs adresses ou communes rendent le libellé ambigu avant de généraliser.

## Compatibilité et vérifications

- Préserver les séances existantes, les liens de répertoire, l'état global des médias et le contrat des outils MCP déjà exposés pour le répertoire et les médias. Adapter leur lecture et écriture si le stockage des liens évolue, sans perdre les associations actuelles.
- Vérifier la création et l'édition d'une séquence libre en un seul passage, la fermeture et la réouverture du dialogue, les titres de médias identiques, l'ajout et le retrait d'un lien, les réglages de visibilité du répertoire, et l'effet d'un média inactif.
- Vérifier le compositeur, l'aperçu HTML, le PDF et le portail structures pour une séance ancienne et une séance nouvellement créée. Vérifier que modifier une fiche du répertoire dans le nouvel onglet se reflète dans la séance sans écraser les consignes locales.
- Vérifier les sélecteurs de structures dans le backoffice et EasyAdmin avec des noms identiques et plusieurs adresses.

## Choix laissés à l'implémentation

- Choisir une forme de stockage compatible avec les données JSON existantes pour les associations du répertoire et les références des séquences libres.
- Déterminer le petit ensemble initial de suggestions du champ « Repère dans le déroulé » depuis les libellés déjà utilisés, sans imposer une liste fermée.
- Présenter les médias historiques dont les champs retirés contiennent encore des informations utiles, jusqu'à ce qu'une migration de données explicite soit décidée.
