# Acceptation du devis et confirmation Calendar

## Diagnostic confirmé

L’utilisateur a précisé qu’il acceptait le devis,et non le bouton « Confirmer »
d’une séance. `RecordCommercialQuote::markSigned()` confirmait uniquement le dossier.
Le contrôleur de séances possédait déjà sa propre synchronisation,non appelée dans
ce parcours. Test fonctionnel reproduit : dossier confirmé,séance toujours option,
aucun PATCH Calendar.

## Correction

- Service `ConfirmCommercialProjectInterventions` et repository injectable
  `CommercialInterventionDoctrineRepository` pour les seules options du dossier.
- Lecture fraîche de chaque événement,ETag conditionnel,retrait du marqueur avec
  conservation des date/heure/fuseau/lieu Google. Aucune modification d’une séance
  annulée ou déjà confirmée ; événement absent,annulé ou devenu journée entière
  signalé en erreur sans conversion involontaire.
- Confirmation locale après succès Google. Si le marqueur est encore présent
  dans la réponse,l’option reste en échec. Conflits et erreurs Google visibles.
- Appel dans le recorder partagé backoffice/MCP. Le devis accepté reste accepté
  même en échec partiel ; warning traduit existant dans le backoffice,compteurs
  `calendarSync` dans le MCP. Relance d’une signature déjà connue : options
  restantes seulement,aucun deuxième événement de signature.

## Fichiers

- Application : `src/Application/Commercial/RecordCommercialQuote.php`,
  nouveau `ConfirmCommercialProjectInterventions.php`.
- Infrastructure : nouveau `src/Infrastructure/Doctrine/Repository/CommercialInterventionDoctrineRepository.php`,
  `src/Infrastructure/Doctrine/Commercial/DoctrineProjectQuoteManager.php`.
- Entrées : `src/Application/Controller/CommercialQuoteController.php`,
  description du tool dans `src/Application/Mcp/WriteToolRegistry.php`.
- Tests : `CommercialQuoteControllerTest`,`CommercialQuoteWorkflowTest`,nouveau
  `ConfirmCommercialProjectInterventionsTest` (six cas d’intégration).
- Documentation : `ROADMAP.md` et ce plan. Chemins backend relatifs à
  `jardin-sonore-backend/`.

## Vérification et livraison

- RED/GREEN fonctionnel : acceptation d’un devis avec options,confirmation de
  l’événement Google,ETag mis à jour,séance annulée préservée.
- Intégration : horaire/lieu Google modifiés conservés,conflit partiel puis reprise,
  historique unique,événement manquant/annulé/journée entière,marqueur conservé par
  une réponse Google,événement déjà sans marqueur sans PATCH.
- Suite complète :638 tests,3250 assertions,0 échec,2 dépréciations,4 notices.
- PHPStan,CS et conteneur passent ; revue indépendante sans constat substantiel.
- Commandes : PHPUnit complet avec `memory_limit=1G`,PHPStan1G,CS check,lint:container.
- Pas de migration ni nouvelle dépendance. Tag prévu :
  `deploy-quote-calendar-confirmation-20261112-01`.
- Préflight de production en lecture seule : deux dossiers ciblés par l’utilisateur,
  devis signés et dossier confirmé ; l’un est déjà cohérent,l’autre contient une
  seule option avec `[OPT]` dans Google. Aucune écriture Calendar lors du diagnostic.
- Après commit/tag/push/déploiement : sauvegarde privée du seul événement encore
  incohérent,reprise ciblée via le service,vérification marqueur/statut/horaires et
  lieu. Ne pas retoucher le dossier déjà cohérent.
