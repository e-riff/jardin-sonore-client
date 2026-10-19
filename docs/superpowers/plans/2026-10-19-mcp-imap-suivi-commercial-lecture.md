# MCP IMAP et suivi commercial : plan d'implémentation de la lecture

> **Pour l'exécution :** utiliser `superpowers:executing-plans` tâche par tâche. Les cases servent au suivi. Aucun commit sans accord explicite de l'utilisateur.

**Objectif :** rendre le backend Symfony accessible à Mistral Vibe Work par MCP HTTPS, pour rechercher et lire les mails cPanel et trouver les dossiers commerciaux candidats, sans écriture.

**Architecture :** une route Symfony `/api/mcp` parle JSON-RPC sur Streamable HTTP, protégée par un jeton Bearer dédié. Un registre d'outils borne et valide les appels. L'accès IMAP et la recherche de dossiers sont deux services indépendants injectés dans ce registre. Cette livraison est le lot de lecture de la [conception approuvée](../specs/2026-10-17-assistant-mcp-imap-suivi-dossiers-design.md) ; les résumés et brouillons feront l'objet d'un lot ultérieur après recette réelle.

**Technologies :** PHP 8.4+, Symfony 8.1, PHPUnit, Doctrine ORM, IMAP TLS via `webklex/php-imap` 6.x sans extension `ext-imap`.

**Choix IMAP vérifié le 19 octobre :** l'extension `imap` est active sur le PHP 8.5 de cPanel (capture de l'utilisateur et contrôle CLI), mais absente de l'image Docker locale `php:8.5-fpm` fondée sur Debian 13. Depuis PHP 8.4, elle est dépréciée, distribuée via PECL et son dépôt n'est plus maintenu ; la bibliothèque C `c-client` nécessaire à sa compilation n'est pas disponible dans Debian 13. Garder l'image actuelle et employer le même adaptateur PHP pur en local et en production évite de changer de distribution pour cette seule extension. L'extension activée en ligne n'empêche pas ce choix.

**Spécification :** `docs/superpowers/specs/2026-10-17-assistant-mcp-imap-suivi-dossiers-design.md`.

## Contraintes générales

- Le backend reste l'autorité sur les dossiers ; aucun nouvel envoi de mail, aucune mutation de la boîte ou des dossiers dans ce lot.
- HTTPS valide et jeton Bearer révocable en production ; secrets dans `.env.local`, jamais dans Git ou les journaux.
- Recherche et lecture bornées ; corps texte tronqué, pièces jointes signalées sans transfert ; données des mails considérées non fiables.
- Pas de balayage planifié ni de serveur permanent supplémentaire.
- La configuration locale et la production cPanel doivent être éprouvées avant activation dans Mistral.

## Points de revue

- Jeton absent, incorrect ou non configuré : HTTP 401 sans information sensible ; test fonctionnel dans la tâche 1.
- Requête JSON-RPC malformée, méthode inconnue ou argument invalide : erreur conforme et aucun accès IMAP ; tests dans les tâches 1 et 2.
- Message volumineux, HTML seul ou pièce jointe : sortie bornée en texte et métadonnées, sans contenu joint ; tests dans la tâche 3.
- Dossier commercial ambigu ou aucun résultat : candidats séparés, sans choix automatique ; test dans la tâche 4.
- IMAP indisponible ou certificat invalide : erreur métier limitée, sans identifiants ni trace de contenu ; test dans la tâche 3 et recette dans la tâche 5.

---

### Tâche 1 : transport MCP et authentification

**Fichiers :** créer `src/Application/Controller/McpController.php`, `src/Application/Mcp/McpProtocol.php`, `tests/Functional/Application/Controller/McpControllerTest.php` ; modifier `config/packages/security.php`, `config/parameters.yaml.dist` et `.env` dans `jardin-sonore-backend/`.

**Interfaces :** `McpProtocol::handle(array $message): ?array` retourne une réponse JSON-RPC ou `null` pour une notification. `McpController` expose POST et GET sur `/api/mcp` ; GET peut répondre 405 si le serveur ne fournit pas de flux SSE. Le jeton configuré est comparé en temps constant.

- [x] Écrire les tests : `initialize` retourne version négociée, `serverInfo` et capacité `tools` ; `notifications/initialized` reçoit HTTP 202 ; jeton absent ou faux reçoit 401 ; GET authentifié reçoit 405 ; JSON invalide et méthode inconnue retournent des erreurs JSON-RPC ; aucun secret n'apparaît.
- [x] Lancer le test d'abord et constater l'échec attendu.
- [x] Implémenter la route, la configuration du secret et le noyau JSON-RPC selon [Streamable HTTP](https://modelcontextprotocol.io/specification/2025-06-18/basic/transports). Accepter `2025-06-18`, refuser les versions non prises en charge, respecter les en-têtes MCP requis.
- [x] Relancer le test ; `composer cs-check` et `composer stan` passent.

### Tâche 2 : découverte et validation des outils

**Fichiers :** créer `src/Application/Mcp/ReadToolRegistry.php`, `tests/Unit/Application/Mcp/ReadToolRegistryTest.php` ; modifier `McpProtocol.php` et son test.

**Interfaces :** `ReadToolRegistry::definitions(): array` déclare `search_mail`, `read_mail_thread`, `search_projects` avec schémas JSON précis. `ReadToolRegistry::call(string $name, array $arguments): array` valide les arguments puis délègue aux services des tâches 3 et 4.

- [x] Écrire les tests de découverte et d'appel, outil inconnu, paramètre invalide et limites ; vérifier qu'aucune définition d'écriture ou d'envoi n'est exposée.
- [x] Lancer les tests ciblés et constater l'échec attendu.
- [x] Implémenter les schémas et la validation serveur des arguments ; les erreurs de l'outil deviennent des résultats MCP contrôlés, les erreurs de protocole restent JSON-RPC.
- [x] Relancer les tests ciblés et les contrôles PHP.

### Tâche 3 : recherche et lecture IMAP

**Fichiers :** créer `src/Application/Mcp/MailReader.php`, `src/Infrastructure/Mail/ImapMailReader.php`, `tests/Unit/Infrastructure/Mail/ImapMailReaderTest.php` ; modifier `composer.json`, `composer.lock`, `config/services.php`, `config/parameters.yaml.dist`, `.env` et `ReadToolRegistry.php`.

**Interfaces :** `MailReader::search(?string $from, ?string $since, ?string $query, int $limit): array` et `MailReader::readThread(string $mailReference, int $limit): array`. La référence opaque inclut boîte, UIDVALIDITY et UID ; le `Message-ID` est retourné séparément lorsqu'il existe. Les réponses donnent date, expéditeur, destinataires, objet et extrait ou corps texte borné.

- [x] Écrire des tests avec client IMAP simulé pour recherche filtrée, référence obsolète, corps trop long, pièce jointe et panne IMAP ; vérifier qu'aucune lecture ne marque le mail comme lu.
- [x] Lancer les tests ciblés et constater l'échec attendu.
- [x] Ajouter `webklex/php-imap` 6.x et l'adaptateur TLS avec validation du certificat, délai court et configuration serveur locale. Mapper les erreurs en messages sans secrets.
- [x] Relancer les tests et les contrôles PHP. Le test de connexion réel reste hors de la suite automatisée.

### Tâche 4 : recherche de dossiers commerciaux candidats

**Fichiers :** créer `src/Application/Mcp/ProjectSearch.php`, `src/Infrastructure/Doctrine/Commercial/DoctrineProjectSearch.php`, `tests/Integration/Infrastructure/Commercial/DoctrineProjectSearchTest.php` ; modifier `config/services.php` et `ReadToolRegistry.php`.

**Interfaces :** `ProjectSearch::search(?string $email, ?string $organization, ?string $terms, int $limit): array` retourne des dossiers candidats avec identifiant, titre, organisation, contact correspondant, état et URL du détail. Aucun candidat n'est choisi automatiquement.

- [x] Écrire les tests de recherche par mail d'un contact, organisation et termes, de candidats multiples, d'absence de résultat et de limite ; la réponse reste limitée aux données utiles au suivi.
- [x] Lancer le test ciblé et constater l'échec attendu.
- [x] Implémenter une requête Doctrine composée, bornée et lisible sur les relations commerciales existantes ; conserver le service en lecture seule.
- [x] Relancer le test, puis les contrôles PHP.

### Tâche 5 : recette et activation progressive

**Fichiers :** modifier `ROADMAP.md` pour consigner le résultat effectif et, si nécessaire, documenter la configuration dans `docs/` sans secrets.

**Interfaces :** endpoint HTTPS `/api/mcp`, configuration Mistral avec Bearer, compte IMAP cPanel en lecture.

- [x] Exécuter les tests backend, `composer cs-check` et `composer stan`. Les 458 tests et 2 510 assertions passent avec une limite PHP de 768 Mo ; les avertissements PHPUnit restants concernent deux dépréciations et un mock de logger dans des tests existants de séance.
- [x] Vérifier la route locale `/api/mcp` et son accès par tests fonctionnels.
- [ ] Tester localement `initialize`, `tools/list` et les erreurs via HTTP ; puis vérifier HTTPS et le dialogue depuis Mistral Vibe Work avec le compte gratuit.
- [ ] Avec les identifiants fournis via le canal de configuration local, éprouver une recherche et une lecture IMAP de messages connus, puis un dossier trouvé et un dossier ambigu. Confirmer que le mail reste non lu et qu'aucun corps n'est journalisé.
- [ ] Consigner les limites rencontrées (forfait Mistral, réseau cPanel, dossier Brouillons) et préparer le lot d'écriture uniquement après validation de cette recette.

## Livraison

Ne créer aucun commit, tag ou déploiement sans l'accord explicite requis par `AGENTS.md`. Proposer un message de commit conventional commit en fin d'implémentation. Pour une mise en ligne ultérieure, appliquer les vérifications, commit, tag et pushes exigés par le dépôt.
