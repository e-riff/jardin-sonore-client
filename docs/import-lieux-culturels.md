# Import des lieux culturels

La préparation et la revue ne modifient pas l'annuaire. Le fichier contient des coordonnées : le conserver dans `jardin-sonore-backend/data/`, exclu de Git et du déploiement, et le sauvegarder avant toute opération. Utiliser un chemin différent pour chaque département et chaque nouveau lot. Un fichier dans `/tmp` peut disparaître avec le conteneur.

Depuis la racine du projet, pour la Loire :

```bash
docker compose exec -T php php bin/console app:directory:cultural-places-prepare 42 /app/data/lieux-culturels-42.json
docker compose exec php php bin/console app:directory:cultural-places-review /app/data/lieux-culturels-42.json
```

La revue interactive sauvegarde chaque choix et peut être relancée avec le même fichier. La revue `--no-interaction` classe les lieux sans candidat et laisse les autres à examiner. Les scores sont des indices, pas une preuve ; une absence de candidat ne garantit pas l'absence de doublon. Le tableau final récapitule les créations proposées. Si l'annuaire change après la revue, certaines lignes sont remises à examiner ; relancer la commande de revue.

L'application est une étape distincte qui écrit dans l'annuaire :

```bash
docker compose exec -T php php bin/console app:directory:cultural-places-apply /app/data/lieux-culturels-42.json
```

Elle refuse les lignes non revues, conserve les informations déjà renseignées, ajoute les contacts nouveaux sans réactiver les contacts existants désactivés, puis enregistre l'identifiant de source. Les nouvelles adresses suivent le réglage newsletter existant de l'importateur ; les états déjà enregistrés sont conservés. Chaque ligne validée est enregistrée séparément et le fichier permet de reprendre après interruption. L'opération ne fusionne pas les doublons déjà présents.

Le bilan affiché à la fin indique chaque lieu créé ou relié, son identifiant source et le numéro de sa fiche dans l'annuaire. Ces informations restent dans le fichier : relancer la commande affiche à nouveau le bilan sans réimporter les lignes déjà traitées. Si une interruption a eu lieu juste après l'enregistrement d'une fiche mais avant la sauvegarde du fichier, la reprise indique « Lien retrouvé après reprise ».

Le filtre utilise le code de département (`42`, `2A`, `2B`, `971`, etc.). Les catégories ciblées sont bibliothèques et médiathèques, librairies, théâtres, centres culturels et musique. Les librairies explicitement classées jeunesse passent en premier. Les autres lieux ne sont pas réputés programmer du jeune public. La date de modification source est conservée dans le fichier mais ne prouve pas qu'une information est fiable.

La page publique du jeu de données et les métadonnées API ont indiqué des licences différentes. Clarifier cette divergence avant réutilisation effective ou import en production. Une comparaison doit être réalisée sur l'annuaire de destination : les candidats issus de la base locale ne décrivent pas nécessairement la production.
