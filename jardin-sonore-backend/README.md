# Jardin Sonore Backend

Backend Symfony du projet Jardin Sonore. Il porte aujourd'hui le backoffice interne, l'annuaire, les imports de referentiel et le module de mailing newsletter.

## Vue macro

Le backend remplit quatre roles principaux :

- administrer les donnees de l'annuaire via une interface interne ;
- importer et rapprocher des etablissements depuis des exports externes ;
- preparer, cibler et envoyer des campagnes de mailing ;
- exposer les routes techniques du backoffice et les routes publiques associees, comme la desinscription newsletter.

La logique metier est volontairement separee de la persistence :

- `src/Domain/` porte les modeles metier purs ;
- `src/Application/` porte les cas d'usage, commandes et controllers Symfony ;
- `src/Infrastructure/` porte Doctrine, Mailer, Twig, stockage de fichiers et integration framework.

## Vue technique

Structure principale :

- `src/Application/Command/` : commandes CLI, import annuaire, dispatch mailing, sync geographie.
- `src/Application/Controller/` : controllers metier du backoffice et routes publiques.
- `src/Application/Mailing/` : cas d'usage du module newsletter.
- `src/Application/Directory/` : import et rapprochement d'etablissements.
- `src/Domain/Model/` : modeles metier purs.
- `src/Infrastructure/Doctrine/` : entites, mappings, mappers et repositories Doctrine.
- `src/Infrastructure/Mailing/` : resolution d'audience, rendu Twig, file de livraison.
- `templates/` : ecrans Twig du backoffice et templates d'e-mail.
- `docs/` : documentation backend maintenue avec le code.

## Documentation

- [Vue d'ensemble de la documentation](docs/README.md)
- [Import annuaire](docs/directory-import.md)
- [Mailing](docs/mailing.md)

## Commandes utiles

Depuis la racine du repo :

```bash
make backend-cs-check
make backend-stan
make backend-lint
make deploy-backend
```

Depuis `jardin-sonore-backend/` :

```bash
php bin/console
composer run cs-check
composer run stan
```

## Verifications

Pour un changement backend significatif :

- lancer au minimum `make backend-cs-check` ou un check cible equivalent ;
- lancer `make backend-stan` si l'environnement de cache/container le permet ;
- lancer la commande metier touchee en dry-run quand c'est pertinent, par exemple pour l'import annuaire.
