# Frontieres Applicatives Et Acces Donnees

## Intention

Ce document fixe la doctrine courante du backend pour eviter les acces melanges entre HTTP, modele de domaine, entites Doctrine et lectures d'interface.

Le but n'est pas d'imposer une symetrie parfaite partout, mais de rendre chaque flux lisible :

- ou se trouve la logique metier ;
- quand un objet de domaine doit etre reconstitue ;
- quand une simple projection suffit.

## Regles De Base

- Hors `EasyAdmin`, un controller metier ne recoit pas et n'expose pas d'entites Doctrine.
- Un `Repository` sert a charger ou sauvegarder un vrai objet metier.
- Une `Query`, un `Lookup` ou un service de lecture sert a retourner une projection utile a l'UI, au support de formulaire ou a une lecture technique ciblee.
- Un controller metier prefere des DTO / read models adaptes au flux HTTP plutot qu'un objet de domaine rendu directement a la vue.
- Un cas d'usage d'ecriture charge lui-meme le modele de domaine dont il a besoin a partir d'un identifiant explicite.

## Quand Utiliser Le Domaine

Passer par le domaine est la regle des qu'un flux :

- applique des invariants ou des validations metier ;
- orchestre une creation, une modification ou une transition d'etat ;
- manipule un agregat ou une liste ordonnee avec des regles propres ;
- doit garder une frontiere claire entre intention metier et persistence.

Dans ce cas, le controller transmet surtout :

- des DTO d'entree ;
- des identifiants ;
- des options de navigation HTTP.

Le cas d'usage charge ensuite le modele via un `Repository`.

## Quand Utiliser Une Lecture De Projection

Une lecture de projection est preferee quand l'ecran ou l'API a seulement besoin :

- d'une liste ;
- d'un tableau compact ;
- d'un autocomplete ;
- d'options de formulaire ;
- d'une fiche de lecture ou d'un resume orienté UI ;
- d'un lookup technique.

Dans ces cas, on evite de reconstruire un modele complet "par principe". Une `Query` ou un `Lookup` retourne directement un read model stable.

## Exceptions Assumees

- `EasyAdmin` reste une zone technique autorisee a travailler avec les entites Doctrine.
- Les batchs d'infrastructure ou commandes tres techniques peuvent garder des acces Doctrine / DBAL directs si leur role est clairement technique et non metier.
- Un double niveau `Repository de domaine + lecture technique` est accepte quand les deux roles sont reels et distincts. Il doit etre evite si les deux services font en pratique la meme chose.
