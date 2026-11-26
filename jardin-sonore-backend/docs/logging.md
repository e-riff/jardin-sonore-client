# Journaux Symfony

Les journaux applicatifs sont dans `var/log/` du backend, en local comme sur le serveur. Le chemin de production vérifié le 26 novembre 2026 est `/home/riem3079/repositories/jardin-sonore-backend/var/log`. Le script de livraison crée le dossier et préserve son contenu.

Tous les fichiers tournent quotidiennement et conservent au maximum 30 fichiers par journal (nettoyage à l'écriture, ce n'est pas une limite de taille).

| Fichier daté | Contenu |
| --- | --- |
| `mail-AAAA-MM-JJ.log` | Départ accepté par le transport ou échec, destinataires, objet, identifiant du message en cas de succès. |
| `messenger-AAAA-MM-JJ.log` | Messages reçus/traités par les workers, reprises et erreurs. |
| `mailing_delivery-AAAA-MM-JJ.log` | Détail métier des campagnes et destinataires. |
| `session_document-AAAA-MM-JJ.log` | Génération des PDF. |
| `portal_security-AAAA-MM-JJ.log` | Événements de sécurité du portail déjà instrumentés. |
| `prod-AAAA-MM-JJ.log` | Avertissements et erreurs des autres domaines, avec contexte tamponné. |
| `prod.deprecations-AAAA-MM-JJ.log` | Dépréciations techniques. |

Chaque ligne porte son horodatage avec fuseau. Le journal mail est branché par attributs `AsEventListener` et `WithMonologChannel` sur `SentMessageEvent` et `FailedMessageEvent` : la mise en file seule ne constitue jamais une preuve de départ. Les envois synchrones (notamment les invitations de compte actuellement) et ceux exécutés par Messenger sont couverts. Un succès indique l'acceptation par le transport configuré, pas la réception en boîte. En développement et en test, un transport factice peut aussi accepter un message sans envoi réseau.

Le corps, les pièces jointes et les diagnostics bruts SMTP ne sont pas enregistrés par ce listener. Les destinataires et objets restent des données personnelles : limiter l'accès aux fichiers.

Les sorties standard et erreurs des commandes cron peuvent toujours être redirigées vers les journaux de l'hébergeur ; cela est indépendant des fichiers Monolog. Aucun changement de crontab n'est nécessaire pour obtenir les fichiers ci-dessus. Les erreurs PHP survenant avant le démarrage de Symfony relèvent aussi de l'hébergement.

Après livraison, le cache Symfony doit être renouvelé et les workers redémarrés (déjà prévus dans le script de déploiement). Pour lire les derniers départs : `tail -n 50 var/log/mail-*.log`. Aucun historique antérieur à cette instrumentation n'est reconstitué. Afficher l'heure sur une fiche nécessiterait une persistance métier dédiée ; ce lot ne lit pas les logs depuis l'interface.
