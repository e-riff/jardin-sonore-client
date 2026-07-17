# Import Catalogue Séances

## Objectif

Ce flux sert a preparer un import relisible des contenus pedagogiques issus des fichiers de seances, avant toute execution manuelle en base.

La logique actuelle produit des artefacts separes par domaine :

- `preview.json` pour la revue humaine ;
- `instruments.sql` pour les instruments ;
- `media_resources.sql` pour les fonds sonores, videos et liens.

Les comptines et jeux de doigts ne sont pas encore exportes automatiquement dans ce premier lot, car leur structuration demande encore des arbitrages metier.

## Source actuelle

Les fichiers sources sont cherches manuellement dans `.codex/seances/`.

Le premier echantillon versionne au `17 juillet 2026` repose sur :

- `.codex/seances/Séance Brésil.docx`
- `.codex/seances/Afrique.docx`
- `.codex/seances/Asie 1.docx`

## Script

Depuis la racine du repo :

```bash
python3 scripts/extract-session-catalog-import.py
```

Par defaut, le script ecrit dans :

```text
jardin-sonore-backend/data/imports/seances-2026-07-17/
```

Tu peux aussi fournir une autre cible et une autre liste de fichiers :

```bash
python3 scripts/extract-session-catalog-import.py \
  --output-dir jardin-sonore-backend/data/imports/seances-2026-07-20 \
  ".codex/seances/Séance Brésil.docx" \
  ".codex/seances/Afrique.docx"
```

## Etat actuel des heuristiques

### Instruments

Le script extrait aujourd'hui :

- les lignes `Matériel : ...` ;
- les references instrumentales trouvees dans la section `Découverte des instruments` ;
- quelques suffixes explicites dans des titres comme `Cinq dans le nid – Ukulélé bariton/Tambourin`.

Le dedoublonnage se fait sur une forme normalisee du nom.

### Médias

Le script lit les `.docx` nativement pour conserver les vraies URLs des hyperliens, ce que `pandoc -t plain` ne permet pas seul.

Les ressources sont typées ainsi :

- `video` pour les liens YouTube ;
- `link` pour les autres URLs ;
- `soundtrack` n'est pas encore force automatiquement dans cette premiere passe.

## Relecture attendue

Avant toute execution SQL, relire :

- les faux positifs possibles dans les instruments generiques ;
- les titres de medias trop courts comme `Paroles`, `Musique` ou `à retrouver ici` ;
- les descriptions de contexte quand plusieurs liens se suivent dans une meme section.

## Execution en base

Les fichiers SQL sont volontairement simples et idempotents a minima via `WHERE NOT EXISTS (...)`.

Ils sont prevus pour etre :

1. relus ;
2. ajustes manuellement si besoin ;
3. joues ensuite en local, puis en prod.

Exemple d'execution locale :

```bash
docker compose exec mysql mysql -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" \
  < /app/data/imports/seances-2026-07-17/instruments.sql
```

Adapter la commande au contexte reel d'execution avant usage.
