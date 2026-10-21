# Revue des doublons de l’annuaire

La commande `app:directory:find-duplicates` recherche des paires de structures partageant un nom, une adresse active, un e-mail ou un téléphone. Elle affiche les identifiants, les adresses et les indices communs pour permettre une vérification avant toute fusion.

```bash
php bin/console app:directory:find-duplicates
php bin/console app:directory:find-duplicates --limit=200
```

La commande est strictement en lecture seule. Elle ne fusionne et ne modifie aucune fiche. Un nom ou un contact partagé est un indice, pas une preuve : plusieurs sites peuvent porter le même nom ou partager un standard. Comparer les adresses, l’historique, les dossiers et les liens d’import avant de décider.

Les candidats qui partagent plusieurs indices remontent en premier, puis les autres sont triés par identifiants. La limite est comprise entre 1 et 500. Le premier lot n’emploie volontairement pas de score d’IA ; les indices visibles rendent la décision traçable et peuvent ensuite alimenter un écran interne ou des outils MCP de recherche et de prévisualisation.

Un indice présent sur plus de 20 fiches est écarté pour éviter de générer des centaines de faux rapprochements à partir d’un contact collectif ou d’un nom trop générique. Cette première version privilégie donc les groupes exploitables en revue manuelle ; elle ne promet pas une détection exhaustive de toutes les variantes de noms.
