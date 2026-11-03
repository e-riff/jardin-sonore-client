# Import des lieux culturels

La préparation et la revue ne modifient pas l'annuaire. Le fichier contient des coordonnées : le conserver dans `jardin-sonore-backend/data/`, exclu de Git et du déploiement, et le sauvegarder avant toute opération. Utiliser un chemin différent pour chaque département et chaque nouveau lot. Un fichier dans `/tmp` peut disparaître avec le conteneur.

Depuis la racine du projet, pour la Loire :

```bash
docker compose exec -T php php bin/console app:directory:cultural-places-prepare 42 /app/data/lieux-culturels-42.json
docker compose exec php php bin/console app:directory:cultural-places-review /app/data/lieux-culturels-42.json
```

La revue interactive sauvegarde chaque choix et peut être relancée avec le même fichier. La revue `--no-interaction` classe les lieux sans candidat et laisse les autres à examiner. Les scores sont des indices, pas une preuve ; une absence de candidat ne garantit pas l'absence de doublon. Le tableau final récapitule les créations proposées. Si l'annuaire change après la revue, certaines lignes sont remises à examiner ; relancer la commande de revue.

Les candidats sont comparés dans un seul tableau, avec une colonne pour le lieu importé puis une colonne numérotée par fiche existante. Saisir le numéro de la colonne puis Entrée pour la rattacher, `0` puis Entrée pour créer une fiche, ou `i` puis Entrée pour ignorer. Les fiches du même type sont présentées en premier ; un type différent reste visible pour permettre la vérification. Aucun pourcentage ne prouve qu'il s'agit du même lieu.

Pour modifier une décision avant application, interrompre la revue avec `Ctrl+C` puis relancer la même commande avec `--reconsider=NUMÉRO`, en utilisant le numéro de ligne affiché avant le nom (par exemple `--reconsider=53` pour `53/256`). Cette ligne est présentée en premier ; les autres décisions restent intactes. Une ligne déjà importée ou possédant déjà un lien de source ne peut pas être modifiée ainsi.

Si la recherche de candidats a été améliorée depuis une revue précédente, la commande réexamine les créations proposées automatiquement en l'absence de candidat. Si de nouvelles correspondances sont détectées, elles sont soumises à validation. Les liaisons choisies et les créations explicitement décidées après comparaison sont conservées ; si leurs candidats changent, l'application demande une nouvelle revue.

Pour un fichier préparé avant l'ajout des codes INSEE, sauvegarder d'abord le fichier, puis exécuter `docker compose exec -T php php bin/console app:directory:cultural-places-refresh-municipalities /app/data/lieux-culturels-42.json`. Cette commande conserve les décisions et récupère les codes à partir des identifiants source ; si un lieu est absent ou que sa commune a changé, elle s'arrête sans modifier le fichier. Les nouveaux lots contiennent déjà les codes.

L'application est une étape distincte qui écrit dans l'annuaire :

```bash
docker compose exec -T php php bin/console app:directory:cultural-places-apply /app/data/lieux-culturels-42.json
```

Elle refuse les lignes non revues et vérifie tous les rapprochements avant d'écrire la moindre nouvelle fiche. Si des candidats ont changé depuis la revue, les lignes concernées repassent à examiner ensemble ; relancer la revue avant de reprendre l'application. Elle conserve les informations déjà renseignées, ajoute les contacts nouveaux sans réactiver les contacts existants désactivés, puis enregistre l'identifiant de source. Les nouvelles adresses suivent le réglage newsletter existant de l'importateur ; les états déjà enregistrés sont conservés. Chaque ligne validée est enregistrée séparément et le fichier permet de reprendre après interruption. L'opération ne fusionne pas les doublons déjà présents.

Le bilan affiché à la fin indique chaque lieu créé ou relié, son identifiant source et le numéro de sa fiche dans l'annuaire. Ces informations restent dans le fichier : relancer la commande affiche à nouveau le bilan sans réimporter les lignes déjà traitées. Si une interruption a eu lieu juste après l'enregistrement d'une fiche mais avant la sauvegarde du fichier, la reprise indique « Lien retrouvé après reprise ».

Le filtre utilise le code de département (`42`, `2A`, `2B`, `971`, etc.). Les catégories ciblées sont bibliothèques et médiathèques, librairies, théâtres, centres culturels et musique. Les librairies explicitement classées jeunesse passent en premier. Les autres lieux ne sont pas réputés programmer du jeune public. L'adresse est reliée à la commune de l'annuaire par son code INSEE ; avant toute écriture, l'application refuse un code absent ou inconnu. La date de modification source est conservée dans le fichier mais ne prouve pas qu'une information est fiable.

La page publique du jeu de données et les métadonnées API ont indiqué des licences différentes. Clarifier cette divergence avant réutilisation effective ou import en production. Une comparaison doit être réalisée sur l'annuaire de destination : les candidats issus de la base locale ne décrivent pas nécessairement la production.
