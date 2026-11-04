# Import des lieux culturels

La préparation et la revue ne modifient pas l'annuaire. Le fichier contient des coordonnées : le conserver dans `jardin-sonore-backend/data/`, exclu de Git et du déploiement, et le sauvegarder avant toute opération. Utiliser un chemin différent pour chaque département et chaque nouveau lot. Un fichier dans `/tmp` peut disparaître avec le conteneur.

Pour ouvrir l’assistant depuis le terminal backend (`/app`) :

```bash
php bin/console app:directory:cultural-places-prepare
```

Il demande le département, affiche les derniers lots présents dans `data/` pour chaque catégorie, puis permet de choisir plusieurs catégories avec leurs numéros séparés par des virgules. Il propose un fichier daté et demande confirmation avant récupération. Chaque commande affiche ensuite la commande à copier pour poursuivre : revue, application ou préparation d’un nouveau lot. Les dates de récupération et d’application sont conservées séparément ; les lots anciens sans date affichent « inconnue ». L’historique est propre au serveur et aux fichiers encore présents, pas partagé entre développement et production.

Catégories sélectionnables : médiathèques, librairies jeunesse, salles de spectacle, enseignement artistique (conservatoires et écoles de musique), centres culturels, tiers-lieux, MJC et festivals. Les librairies généralistes sont exclues des nouveaux lots. La présence et la couverture de chaque catégorie dépendent des classifications renseignées par la source ; une catégorie sélectionnée peut ne retourner aucun lieu.

L’usage avec arguments reste disponible. Pour restreindre les catégories :

```bash
php bin/console app:directory:cultural-places-prepare 42 /app/data/lieux-42-nouveau.json --types=enseignement_artistique,tiers_lieu
```

Depuis la racine du projet, pour la Loire :

```bash
docker compose exec -T php php bin/console app:directory:cultural-places-prepare 42 /app/data/lieux-culturels-42.json
docker compose exec php php bin/console app:directory:cultural-places-review /app/data/lieux-culturels-42.json
```

La revue interactive sauvegarde chaque choix et peut être relancée avec le même fichier. La revue `--no-interaction` classe les lieux sans candidat et laisse les autres à examiner. Les scores sont des indices, pas une preuve ; une absence de candidat ne garantit pas l'absence de doublon. Le tableau final récapitule les créations proposées. Si l'annuaire change après la revue, certaines lignes sont remises à examiner ; relancer la commande de revue.

Les candidats sont comparés dans un seul tableau, avec une colonne pour le lieu importé puis une colonne numérotée par fiche existante. Saisir le numéro de la colonne puis Entrée pour la rattacher, `0` puis Entrée pour créer une fiche, ou `i` puis Entrée pour ignorer. Les fiches du même type sont retenues et présentées en premier. La recherche complémentaire par nom et code INSEE exclut les fiches dont le type connu est différent ; une ressemblance géographique seule ne suffit donc plus à proposer un RPE pour une médiathèque. Un type différent reste possible lorsqu’il est retenu par le moteur principal, qui examine également les coordonnées et l’adresse. Les types non renseignés restent examinables. Aucun pourcentage ne prouve qu'il s'agit du même lieu.

Pour modifier une décision avant application, interrompre la revue avec `Ctrl+C` puis relancer la même commande avec `--reconsider=NUMÉRO`, en utilisant le numéro de ligne affiché avant le nom (par exemple `--reconsider=53` pour `53/256`). Cette ligne est présentée en premier ; les autres décisions restent intactes. Une ligne déjà importée ou possédant déjà un lien de source ne peut pas être modifiée ainsi.

Si la recherche de candidats a été améliorée depuis une revue précédente, la commande réexamine les créations proposées automatiquement en l'absence de candidat. Si de nouvelles correspondances sont détectées, elles sont soumises à validation. Les liaisons choisies et les créations explicitement décidées après comparaison sont conservées ; si leurs candidats changent, l'application demande une nouvelle revue.

Pour un fichier préparé avant l'ajout des codes INSEE, sauvegarder d'abord le fichier, puis exécuter `docker compose exec -T php php bin/console app:directory:cultural-places-refresh-municipalities /app/data/lieux-culturels-42.json`. Cette commande conserve les décisions et récupère les codes à partir des identifiants source ; si un lieu est absent ou que sa commune a changé, elle s'arrête sans modifier le fichier. Les nouveaux lots contiennent déjà les codes.

L'application est une étape distincte qui écrit dans l'annuaire :

```bash
docker compose exec -T php php bin/console app:directory:cultural-places-apply /app/data/lieux-culturels-42.json
```

Elle refuse les lignes non revues et vérifie tous les rapprochements avant d'écrire la moindre nouvelle fiche. Si des candidats ont changé depuis la revue, les lignes concernées repassent à examiner ensemble ; relancer la revue avant de reprendre l'application. Elle conserve les informations déjà renseignées, ajoute les contacts nouveaux sans réactiver les contacts existants désactivés, puis enregistre l'identifiant de source. Les nouvelles adresses suivent le réglage newsletter existant de l'importateur ; les états déjà enregistrés sont conservés. Chaque ligne validée est enregistrée séparément et le fichier permet de reprendre après interruption. L'opération ne fusionne pas les doublons déjà présents.

Le bilan affiché à la fin indique chaque lieu créé ou relié, son identifiant source et le numéro de sa fiche dans l'annuaire. Ces informations restent dans le fichier : relancer la commande affiche à nouveau le bilan sans réimporter les lignes déjà traitées. Si une interruption a eu lieu juste après l'enregistrement d'une fiche mais avant la sauvegarde du fichier, la reprise indique « Lien retrouvé après reprise ».

Le filtre utilise le code de département (`42`, `2A`, `2B`, `971`, etc.). Les catégories ciblées sont celles sélectionnées lors de la préparation. Les librairies explicitement classées jeunesse passent en premier. Les autres lieux ne sont pas réputés programmer du jeune public. L'adresse est reliée à la commune de l'annuaire par son code INSEE ; avant toute écriture, l'application refuse un code absent ou inconnu. La date de modification source est conservée dans le fichier mais ne prouve pas qu'une information est fiable.

La page publique du jeu de données et les métadonnées API ont indiqué des licences différentes. Clarifier cette divergence avant réutilisation effective ou import en production. Une comparaison doit être réalisée sur l'annuaire de destination : les candidats issus de la base locale ne décrivent pas nécessairement la production.

Les lots manuels peuvent suivre le même format de travail avec `source` et `sourceLabel` à la racine. La revue et l’application conservent cette provenance distincte ; sans `source`, les anciens lots utilisent toujours `culture-lieux`. Les identifiants locaux générés pour une liste manuelle ne sont pas des identifiants officiels de l’API. Garder un identifiant différent pour chaque site et ne pas renseigner un identifiant d’organisation partagé si les antennes doivent rester distinctes. Les lots de coordonnées restent dans `data/`, hors Git.
