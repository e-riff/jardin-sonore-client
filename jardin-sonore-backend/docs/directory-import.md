# Import Annuaire

## Vue macro

La commande `app:directory:import-establishments` sert a injecter dans l'annuaire des etablissements issus d'un export externe, aujourd'hui principalement un export CAF JSON.

Elle remplit trois objectifs :

- reconnaitre les structures deja connues sans dupliquer l'annuaire ;
- proposer un rapprochement assiste quand le score n'est pas assez fort pour etre automatique ;
- creer de nouvelles organisations quand aucun candidat credible n'existe.

Le flux general est le suivant :

1. charger et valider le JSON source ;
2. transformer chaque ligne en objet d'import ;
3. verifier si la ligne est deja connue via ses identifiants externes ;
4. calculer des candidats de rapprochement si besoin ;
5. auto-lier, demander confirmation, ou creer un nouvel etablissement ;
6. hydrater l'organisation, ses contacts et la trace d'import.

## Usage

Commande :

```bash
php bin/console app:directory:import-establishments <fichier.json>
```

Options principales :

- `--apply` : persiste les changements au lieu d'un dry-run.
- `--source=caf` : identifiant logique de la source.
- `--offset=100` : saute les premieres lignes.
- `--limit=50` : limite le nombre de lignes traitees.

Exemples :

```bash
php bin/console app:directory:import-establishments import-caf-2026-07-01/creches-lyon.json
php bin/console app:directory:import-establishments /data/import-caf-2026-07-01/creches-lyon.json --offset=100 --limit=20
php bin/console app:directory:import-establishments /data/import-caf-2026-07-01/creches-lyon.json --apply
```

## Regles metier

### Source et format

Le dossier de depot standard est `data/imports/`. La commande accepte :

- un chemin absolu ;
- un chemin `/data/...` ;
- un chemin relatif a `data/imports/`.

Le JSON peut exposer les lignes d'import via `results` ou `mainResults`.

### Reconnaissance directe

Avant tout scoring, la commande cherche un lien d'import deja connu via :

- `source + externalId` ;
- puis `source + externalOrganizationId`.

Si un lien existe, l'organisation est reprise directement sans recalcul de score.

### Rapprochement par score

Quand aucune liaison externe n'est connue, le backend calcule des candidats a partir :

- de l'email ;
- du telephone ;
- du site web ;
- du nom ;
- du nom metier normalise ;
- de la commune ;
- de l'adresse.

Seuils actuels :

- `> 90` : liaison automatique ;
- `80 a 90` : la commande te demande quoi faire en mode interactif ;
- `< 80` : le candidat n'est pas retenu, donc la ligne part vers une creation de nouvel etablissement.

L'action `ignorer` n'est jamais decidee automatiquement a cause du score. Elle n'existe que si l'utilisateur la choisit dans le prompt interactif.

### Prompt interactif

Quand plusieurs candidats plausibles existent ou qu'un meilleur candidat est sous le seuil d'auto-match, la commande affiche :

- le rang courant de traitement ;
- les donnees importees ;
- un tableau des candidats avec leur score ;
- le detail des informations connues pour chaque candidat.

Choix possibles :

- lier a un candidat existant ;
- creer un nouvel etablissement ;
- ignorer explicitement la ligne.

### Creation et mise a jour

Si aucun candidat n'est retenu :

- une nouvelle `OrganizationEntity` est creee.

Sinon :

- l'organisation retenue est mise a jour.

Hydratation actuelle :

- nom ;
- site web ;
- type d'organisation selon le type import et quelques heuristiques simples ;
- statut client/prospect par defaut si necessaire ;
- email, telephone et adresse dans le bloc de contact.

### Contacts partages

Les emails et telephones sont dedoublonnes :

- si le contact existe deja, il est reutilise ;
- sinon il est cree ;
- la commande cree ensuite la liaison entre le contact et l'organisation.

Pour les telephones, la comparaison est faite sur une forme normalisee metier, par exemple `04 73 95 59 40` et `+33 4 73 95 59 40` convergent vers la meme valeur stockee.

### Trace d'import

Avec `--apply`, la commande persiste aussi un `DirectoryImportLinkEntity` contenant :

- `source` ;
- `externalId` ;
- `externalOrganizationId` ;
- un hash du payload brut.

Cette trace permet de stabiliser les imports suivants.

## Vue technique

### Points d'entree code

- `src/Application/Command/ImportDirectoryEstablishmentsCommand.php`
  Orchestre la lecture, le dry-run, l'interaction et le reporting.
- `src/Application/Directory/DirectoryImportFileLoader.php`
  Charge et decode le fichier JSON.
- `src/Application/Directory/DirectoryEstablishmentImportItem.php`
  DTO d'import valide par Symfony Validator.
- `src/Application/Directory/DirectoryEstablishmentMatcher.php`
  Recherche les liens externes existants et calcule les scores de rapprochement.
- `src/Application/Directory/DirectoryEstablishmentUpserter.php`
  Hydrate l'organisation et gere les contacts/adresses.

### Details du score

Le score combine des bonus et similarites ponderees, notamment :

- email exact ;
- telephone exact ;
- site exact ;
- nom metier exact ;
- adresse exacte normalisee ;
- combinaisons fortes nom/adresse ou telephone/adresse ;
- penalite si le nom parait proche mais que l'adresse est trop eloignee.

Les candidats sous `80` sont filtres dans le matcher avant meme d'etre proposes a la commande.

### Reporting

En fin d'execution, la commande remonte notamment :

- organisations creees ;
- organisations mises a jour ;
- rattachements via identifiants externes ;
- emails/telephones crees ou reutilises ;
- matchs ambigus ;
- erreurs de validation ;
- lignes ignorees.

### Limitations connues

- le stockage anti-doublon d'import repose sur la table de liens d'import, pas sur une strategie d'historisation complete ;
- le rapprochement reste heuristique et doit etre surveille quand la qualite des exports change ;
- en non interactif, les cas ambigus sont ignores car aucun arbitrage manuel n'est possible.
