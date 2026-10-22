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

Exemple minimal avec plusieurs coordonnees :

```json
{
  "results": [
    {
      "id": "reseau-mjc-du-parc",
      "type": "mjc",
      "name": "MJC du Parc",
      "commune": "Lyon",
      "address": "4 rue du Parc, 69003 Lyon",
      "mail": "accueil@example.org",
      "additionalEmails": ["direction@example.org"],
      "phone": "04 72 00 00 00",
      "additionalPhones": ["04 72 00 00 01"],
      "siteWeb": "https://example.org"
    }
  ]
}
```

`id` et `type` sont obligatoires. Donner aussi un `name` precis a chaque fiche. L'`id` est un identifiant externe stable et unique au sein d'une meme source ; avec `--source`, il permet de retrouver la meme organisation lors d'un nouvel import. Ne pas regenerer cet identifiant a chaque export. `organizationId` est facultatif et ne doit etre partage que par des lignes qui decrivent reellement le meme etablissement : ne pas y mettre l'identifiant d'un reseau qui gere plusieurs lieux. `detailsUuid` est lu, mais ne sert pas au rapprochement actuel.

`mail`, `phone`, `address` et `commune` sont les coordonnees principales. Les listes facultatives `additionalEmails`, `additionalPhones` et `additionalAddresses` ajoutent d'autres coordonnees ; chaque adresse supplementaire est une chaine et reprend la meme commune. Placer le code postal dans `address` pour qu'il soit extrait. Les emails doivent etre valides et les telephones exploitables. `siteWeb` renseigne le site web. Les champs libres comme `sourceUrl` ou `note` restent dans le fichier JSON, mais ne deviennent pas des champs de la fiche annuaire ; la trace d'import ne conserve que le hash du contenu.

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
- du type d'organisation ;
- de la commune ;
- de l'adresse.

Seuils actuels :

- `> 90` avec au moins 15 points d'avance sur le candidat suivant : liaison automatique ;
- `80 a 90`, ou candidats proches : la commande te demande quoi faire en mode interactif ;
- `< 80` : le candidat n'est pas retenu, donc la ligne part vers une creation de nouvel etablissement.

Lorsque les deux types sont connus et differents, le candidat est ecarte si les noms ne sont pas presque identiques (similarite inferieure a 90 %). S'ils le sont, son score est plafonne a 90 : il peut etre propose pour une verification manuelle, jamais lie automatiquement. Une correspondance deja etablie par `--source` et identifiant externe reste prioritaire. La recherche par commune ne ramene que les organisations du meme type lorsque le type de l'import est reconnu.

L'action `ignorer` n'est jamais decidee automatiquement a cause du score. Elle n'existe que si l'utilisateur la choisit dans le prompt interactif.

### Prompt interactif

Quand plusieurs candidats plausibles existent ou qu'un meilleur candidat est sous le seuil d'auto-match, la commande affiche :

- le rang courant de traitement ;
- les donnees importees ;
- un tableau des candidats avec leur score ;
- le detail des informations connues pour chaque candidat ;
- le type connu de chaque candidat, pour comparer avec le type importe.

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
- email, telephone et adresse dans le bloc de contact.

### Type importé et type de l'annuaire

Pour les nouveaux fichiers JSON, utiliser de préférence directement l'une des valeurs de l'enum dans le champ `type` :

| `type` recommandé dans le JSON | Valeur stockée |
|---|---|
| `creche` | `creche` |
| `mairie` | `mairie` |
| `rpe` | `rpe` |
| `mam` | `mam` |
| `mediatheque` | `mediatheque` |
| `centre` | `centre` |
| `mjc` | `mjc` |
| `lieu_parentalite` | `lieu_parentalite` |
| `ludotheque` | `ludotheque` |
| `garderie` | `garderie` |
| `cafe_parents` | `cafe_parents` |
| `librairie_jeunesse` | `librairie_jeunesse` |
| `festival_evenement` | `festival_evenement` |
| `salle_spectacle` | `salle_spectacle` |
| `test` | `test` |

Pour les nouveaux imports, l'outil qui produit le JSON doit choisir le code canonique du tableau, jamais inventer une valeur. `test` est réservé aux données de test et ne doit pas être utilisé pour une structure réelle.

Les anciens libellés ou codes sont acceptés ainsi :

| Valeur source dans `type` ou signal dans `name` | Valeur stockée |
|---|---|
| `EAJE` | `creche` |
| `MPT` | `mjc` |
| `Café` + `parent` dans le type, ou « café poussette » / « café des enfants » dans le nom | `cafe_parents` |
| Type contenant « librairie jeunesse » | `librairie_jeunesse` |
| Type contenant « festival » ou « événement » | `festival_evenement` |
| Type contenant « salle spectacle », « salle de spectacle », « théâtre », « scène nationale » ou « équipement culturel », ou égal à `CDN` | `salle_spectacle` |
| « Ludothèque » dans le type ou le nom | `ludotheque` |
| `Centre social`, `Centre de loisirs`, `centre_loisirs`, `Accueil de loisirs`, `ALSH` dans le type ou expressions correspondantes dans le nom | `centre` |
| `LAEP`, `Lieu de parentalité`, `Lieux enfants parents` dans le type, ou `LAEP` / « lieu de parentalité » dans le nom | `lieu_parentalite` |
| `RAM`, « relais petite enfance », « relais assistantes maternelles » dans le type ou le nom ; `RPE` dans le nom | `rpe` |
| « mairie » ou « ville de » dans le nom | `mairie` |
| `MAM` ou « garderie » dans le nom | `mam` ou `garderie` respectivement |
| `MJC` ou « maison pour tous » dans le nom | `mjc` |
| « médiathèque » dans le type | `mediatheque` |

Les accents, la casse et les séparateurs sont normalisés pour les correspondances textuelles. Si aucune règle ne correspond, l'importeur ne déduit pas de type et le rapprochement ne peut pas s'appuyer sur ce critere. Il ne remplace le type d'une organisation existante que si celle-ci n'en a pas déjà un. Le détail du mapping est dans `DirectoryOrganizationTypeResolver::resolve()`.

Cette conversion ne remplace le type que si l'organisation n'en a pas déjà un. Ainsi, un import qui retrouve une organisation existante conserve son type renseigné ; les nouvelles organisations reçoivent le type déduit. Les valeurs possibles et leurs libellés sont déclarés dans `OrganizationType` et `translations/backoffice+intl-icu.fr.yaml`.

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
- `src/Application/Directory/DirectoryOrganizationTypeResolver.php`
  Convertit le type du JSON en type d'annuaire pour l'hydratation et le rapprochement.
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
- penalite si le nom parait proche mais que l'adresse est trop eloignee ;
- exclusion d'un type different lorsque le nom n'est pas presque identique ; revue manuelle seule lorsque le nom l'est.

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
- un candidat au-dessus de `90` n'est plus auto-lie si le candidat suivant est à moins de 15 points ;
- en non interactif, les cas ambigus sont ignores car aucun arbitrage manuel n'est possible.
