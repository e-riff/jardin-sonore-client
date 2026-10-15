# Enquête — assistant conversationnel pour Gmail et le suivi client

**Date de vérification : 15 octobre 2026**
**Contexte :** pouvoir demander dans un chat de parcourir les nouveaux mails, proposer leur rattachement à un dossier Jardin Sonore, préparer une réponse et mettre à jour le suivi commercial, avec validation avant toute écriture.

## Résumé

L’expérience « je demande dans le chat, l’assistant lit Gmail et met à jour mon backoffice » existe désormais presque telle quelle chez **Mistral Vibe, mode Work** : connecteur Gmail, connecteurs MCP personnalisés, actions en plusieurs étapes et confirmations. Gmail permet officiellement de rechercher/lire, créer des brouillons et organiser les messages par libellés ; l’envoi direct n’est pas permis par son connecteur Google. Les fonctions disponibles et le forfait requis pour ajouter un MCP personnel devront être vérifiés dans le compte avant de s’engager.

Avec **ChatGPT Plus**, les apps connectées officielles peuvent aider à lire/rechercher des informations dans une conversation, mais les docs actuelles indiquent que la connexion d’un MCP personnalisé avec droits d’écriture n’est pas disponible sur Plus. Les GPT Actions ont une autre limite : un GPT configuré avec des Actions ne peut pas utiliser les Apps dans ce même GPT. La possibilité de combiner Gmail avec une Action backoffice dans un GPT est donc bloquée par cette contrainte, et le statut actuel de création de GPTs personnels mérite vérification dans l’interface, car la documentation OpenAI présente des indications qui ont évolué.

La solution qui donne le plus de contrôle à long terme est un **petit assistant web privé**, intégré au produit Jardin Sonore. Il parlerait à Gmail et au backend par des API dédiées, afficherait les mails et dossiers candidats, puis présenterait un récapitulatif de modifications à confirmer. Le backend possède déjà le modèle métier de dossiers, tâches et fil d’événements ; il n’expose pas encore, d’après le code consulté, l’API de lecture/écriture nécessaire à un agent externe. Cette solution demande du développement et une gestion OAuth Gmail, mais elle reste indépendante du fournisseur de modèle.

## Comparaison rapide

| Piste | Lire Gmail dans un chat | Mettre à jour le suivi | Validation avant écriture | Effort initial | Conclusion |
|---|---|---|---|---|---|
| ChatGPT Plus : apps Gmail/Drive + GPT Action | Oui, selon les apps/actions offertes au compte | Non dans le même GPT si l’accès métier passe par une Action | Possible pour une Action, selon sa configuration | Faible à moyen, mais architecture bloquée par la limite Apps/Actions | À garder pour lire/analyser, écriture métier bloquée dans le même GPT |
| Assistant web privé Jardin Sonore | Oui, via OAuth Gmail | Oui, via API backend dédiée | Oui, conçue comme étape obligatoire dans l’interface et côté serveur | Moyen à élevé | Meilleure maîtrise, pérenne et multi-modèle |
| Mistral Vibe Work + connecteur MCP métier | Oui, connecteur Gmail | Oui si le MCP métier est publié et disponible au compte | Oui, confirmations Work et permissions par fonction | Faible pour un essai, moyen pour le MCP métier | Meilleur essai immédiat de l’expérience de chat demandée |

## 1. ChatGPT Plus : Gmail/Drive et mise à jour du backoffice

### Ce qui fonctionne

Les apps connectées de ChatGPT sont utilisables depuis les conversations, et ChatGPT peut appeler plusieurs apps dans la même conversation si le compte et les règles du workspace l’autorisent. Les permissions effectives dépendent du connecteur et des droits OAuth accordés. C’est adapté pour rechercher des messages, résumer des échanges et consulter des documents Drive, selon les actions que l’app Gmail/Google actuellement connectée expose.

Une GPT Action permet à un GPT d’appeler une API externe documentée avec OpenAPI et authentification par clé ou OAuth. On pourrait donc exposer une API Jardin Sonore pour rechercher un dossier, ajouter une note ou créer un rappel.

### Le point bloquant

La documentation OpenAI dit qu’un GPT utilise **soit des Apps, soit des Actions, mais pas les deux à la fois**. Donc, si Gmail est activé en tant qu’App dans le GPT, la GPT Action nécessaire pour écrire dans le suivi ne peut pas être active dans ce GPT. Une conversation ChatGPT ordinaire peut combiner plusieurs apps, mais ce n’est pas équivalent à ajouter une action API personnelle avec droits d’écriture sur Plus.

La documentation MCP actuelle distingue les forfaits : les utilisateurs Pro peuvent connecter des MCP personnalisés avec accès lecture/recherche ; le MCP complet, incluant l’écriture, est réservé à Business et Enterprise/Edu. Plus est en dessous de Pro sur ce point. Les MCP personnalisés sont également web uniquement. Cela confirme que passer de Plus à un abonnement individuel supérieur ne garantit pas la capacité d’écriture recherchée.

Les GPT Actions ont leur propre configuration, mais l’aide OpenAI indique aussi que la création/publication de nouveaux GPTs personnels a changé selon les forfaits. Il faut vérifier l’éditeur réellement accessible au compte avant d’investir dans ce montage.

### Verdict

ChatGPT Plus peut aider à **lire et analyser** les mails si le connecteur Gmail est actif, mais ne fournit pas actuellement un chemin simple et vérifié pour faire, dans le même assistant personnalisé, lecture Gmail et écriture dans le backoffice. Cette piste ne devient intéressante que si OpenAI rend Apps et Actions compatibles dans le même GPT, ou si un plan/workspace donnant accès au MCP en écriture est acceptable.

Références officielles : [Apps dans ChatGPT](https://help.openai.com/en/articles/11487775-apps-in-chatgpt), [connexion de serveurs MCP personnalisés](https://help.openai.com/en/articles/12584461-connecting-custom-mcp-servers-in-chatgpt), [configuration des Actions de GPT](https://help.openai.com/en/articles/9442513-configuring-actions-in-gpts), [FAQ GPTs](https://help.openai.com/en/articles/8554407-gpts-faq).

## 2. Assistant web privé relié à Gmail et au backend

### Expérience proposée

Depuis le backoffice, une page privée « Assistant » permettrait de demander : « Regarde les nouveaux mails de la semaine et propose les mises à jour ». L’assistant rechercherait les messages Gmail pertinents, les résumerait, chercherait les dossiers correspondants et présenterait une liste de propositions :

1. mail source et résumé court ;
2. dossier existant suggéré, ou signalement d’un nouveau contact ;
3. élément de fil à ajouter ;
4. rappel proposé avec titre et échéance ;
5. réponse Gmail éventuellement préparée comme brouillon.

Tu pourrais accepter chaque proposition ou tout le lot. Le serveur vérifierait à nouveau les données avant l’écriture, enregistrerait les références Gmail pour éviter les doublons et garderait la trace de la validation. L’envoi d’un courriel resterait une action manuelle dans Gmail ; la V1 peut se limiter aux brouillons.

### Ce qu’il faut construire

- Connexion Google OAuth dédiée au compte Gmail et stockage chiffré/renouvellement des jetons, avec les scopes minimaux. Les scopes de lecture et de création de brouillon sont distincts.
- Un service backend qui recherche/lit les mails sélectionnés, sans aspirer la boîte entière ni indexer tout l’historique.
- Une API métier limitée : recherche de dossiers/contacts, détail d’un dossier, préparation d’une proposition, confirmation transactionnelle d’un ajout de note/tâche et création d’un brouillon.
- Une interface de revue et d’approbation. Les mutations doivent rester explicites, idempotentes et limitées aux objets affichés dans la proposition confirmée.
- Une couche modèle interchangeable (Mistral, OpenAI, autre fournisseur ou modèle local), qui reçoit le contexte strictement nécessaire et ne décide jamais seule de la mutation finale.

Le dépôt a déjà les entités et flux métier de dossiers, personnes, actions/tâches et événements (`CommercialProjectEntity`, `CommercialActionEntity`, `CommercialEventEntity`) ainsi que les services de création et de qualification. Les contrôleurs commerciaux existants servent le backoffice Symfony ; je n’ai pas repéré dans la recherche ciblée une API agent dédiée pour les lire et les modifier. La nouvelle API devrait réutiliser les services applicatifs existants, et non écrire directement dans les entités.

### Risques et protections utiles

- Un mail peut contenir des instructions malveillantes destinées au modèle : le contenu doit être traité comme une donnée, jamais comme une consigne système.
- Limiter le périmètre Gmail à la recherche demandée ou aux mails reçus depuis un dernier point de contrôle.
- Afficher les expéditeurs, dates et extraits qui justifient chaque rattachement ; demander une confirmation si plusieurs dossiers sont plausibles.
- Rechercher les doublons par identifiant de message Gmail et prévoir une clé d’idempotence côté backend.
- Journaliser qui a confirmé quoi, et à partir de quel mail ; éviter de conserver les contenus complets plus longtemps que nécessaire.
- Protéger l’API par l’authentification du backoffice, des scopes précis, une protection CSRF si l’authentification est par cookie, et une limitation des actions permises.

### Verdict

C’est la solution la plus robuste pour un usage quotidien et la plus adaptée à l’évolution du suivi. Elle demande cependant de construire l’interface, le flux OAuth et l’API métier. Il est raisonnable de commencer par une V1 lecture + propositions + validation manuelle, sans envoi de mail ni automatisation en arrière-plan.

Références : [Google OAuth 2.0](https://developers.google.com/identity/protocols/oauth2), [Gmail API scopes](https://developers.google.com/gmail/api/auth/scopes), [Gmail drafts](https://developers.google.com/gmail/api/guides/drafts).

## 3. Mistral Vibe Work : Gmail + MCP du suivi

### Ce qui est proposé aujourd’hui

Mistral a renommé Le Chat en **Vibe** ; le mode conversationnel et agentique est appelé **Work**. Work est disponible dans les applications web et mobiles. Il peut enchaîner plusieurs connecteurs dans une même tâche, afficher les appels d’outils et demander des précisions quand la demande est ambiguë.

Le connecteur Google documente précisément Gmail : rechercher des fils, lire les messages, lister les brouillons, créer un brouillon et gérer les libellés. Il demande les scopes `gmail.readonly`, `gmail.compose` et `gmail.modify`. Le brouillon n’est pas envoyé automatiquement : il doit être relu et envoyé dans Gmail. Le connecteur Drive permet de rechercher des fichiers, lire leur contenu (dont Docs et Sheets) et leurs métadonnées ; il est documenté en lecture seule. Ainsi, Mistral peut consulter Gmail et Drive dans le même travail, mais le connecteur Drive ne met pas à jour les fichiers. Les données des connecteurs Google temps réel sont récupérées pour la demande ; Mistral indique ne pas les stocker sur ses serveurs et ne pas les utiliser pour entraîner ses modèles. Les conversations qui y font référence suivent toutefois les règles générales du forfait.

Les connecteurs MCP personnalisés peuvent pointer vers un serveur privé exposé en HTTPS. Work détecte l’authentification, dont OAuth 2.1 ou Bearer, et permet de régler les confirmations par fonction. Il est conseillé de laisser les lectures préautorisées si souhaité et de conserver une approbation manuelle pour chaque écriture.

### Application au suivi Jardin Sonore

Un MCP minimal pourrait fournir des fonctions métier étroites :

- `search_projects` : retrouver les dossiers candidats par structure, personne, adresse mail ou termes ;
- `get_project_context` : lire le statut, contacts, dernières notes et tâches ouvertes ;
- `prepare_project_update` : retourner une proposition structurée sans écrire ;
- `confirm_project_update` : ajouter une note/événement et éventuellement une tâche, uniquement après confirmation explicite.

Pour la création d’un nouveau dossier, il faudrait demander les champs manquants et présenter la structure/personne proposée avant l’écriture. La recherche anti-doublon doit précéder toute création. Le connecteur Gmail peut ensuite créer un brouillon de réponse si tu le demandes ; il ne faut pas laisser le modèle envoyer le message.

### Limites à vérifier

- La documentation indique que l’ajout d’un MCP est une fonction administrateur, puis précise que sur Free, Pro et Student, le propriétaire du compte est administrateur par défaut. Cela suggère qu’un compte individuel Pro peut ajouter un MCP personnalisé et que Team n’est pas requis pour le simple usage personnel. La page tarifaire présente les connecteurs personnalisés dans la grille, mais son rendu public n’est pas parfaitement explicite sur les cases de forfait ; vérifier la présence de l’option dans le compte reste le contrôle final.
- La documentation officielle précise les capacités Gmail à travers le connecteur Google Studio ; l’écran Connectors de Vibe peut présenter une liste de fonctions ou des modalités légèrement différentes. Il faut vérifier les scopes affichés au moment du consentement OAuth et la liste réelle de fonctions Gmail dans Work.
- Le serveur MCP personnalisé doit être joignable sur Internet en HTTPS et maintenu. L’API peut changer (Connectors Studio est en préversion publique).
- Mistral avertit que les serveurs MCP tiers ne sont pas des produits Mistral : sécurité, disponibilité et traitement des données du serveur restent à la charge de son propriétaire.

### Verdict

C’est la meilleure piste pour **tester rapidement** l’expérience « un chat lit mes mails, prépare les mises à jour, puis me demande confirmation ». Avant d’écrire un MCP, commencer par connecter Gmail dans Vibe Work et lui demander de résumer quelques mails, puis vérifier le panneau des fonctions Gmail. Ensuite, vérifier dans le compte si un MCP personnalisé est activable. Si oui, le même backend métier pourra servir Mistral Vibe et, plus tard, une interface web privée.

Références officielles : [Vibe Work et connecteurs](https://docs.mistral.ai/vibe/work/connectors), [intégrations Google et scopes Gmail](https://docs.mistral.ai/studio/connectors/providers/google), [serveurs MCP personnalisés](https://docs.mistral.ai/vibe/work/connectors/mcp-connectors), [approbations et permissions](https://docs.mistral.ai/vibe/work/safety-and-approvals), [grille des forfaits](https://mistral.ai/pricing/), [préversion Connectors Studio](https://docs.mistral.ai/studio/connectors).

## Hébergement d’un chatbot dédié sur cPanel

Un chatbot hébergé sur le cPanel **n’oblige pas à héberger le modèle d’IA sur le cPanel**. Le site peut servir une interface de chat et un petit endpoint PHP/Symfony qui appelle une API distante (Mistral ou un autre fournisseur), puis appelle les services métier du backoffice. Le modèle tourne chez le fournisseur ; cPanel ne fait que gérer l’interface, l’authentification et les échanges API. C’est beaucoup plus réaliste qu’un modèle local, qui demanderait des ressources GPU et un environnement d’exécution que l’hébergement mutualisé ne fournit généralement pas.

La faisabilité dépend du forfait cPanel : version PHP, accès HTTPS, tâches longues/limites d’exécution, stockage des secrets, capacité à installer les dépendances, configuration des URLs OAuth Google et appels sortants HTTPS. Le dépôt possède déjà un backend Symfony et une API BFF ; ajouter l’assistant dans ce backend est probablement plus cohérent qu’héberger un second service séparé. Il faudra toutefois vérifier où se trouve l’hébergement de production et ses contraintes avant de retenir cette option. Une interface dédiée apporte un contrôle fin sur les validations, mais c’est bien plus de développement qu’un connecteur Mistral.

## Comparaison décisionnelle : Mistral ou chatbot privé

| Critère | Mistral Vibe Work + MCP Jardin Sonore | Chatbot privé sur le site / backoffice |
|---|---|---|
| Où se passe le chat ? | Dans l’interface Mistral, avec Gmail, Drive et le MCP activés dans le chat | Dans une page privée du site Jardin Sonore |
| Gmail | Connecteur Mistral prêt à l’emploi : recherche, lecture, brouillons et libellés ; pas d’envoi automatique | À intégrer avec Google OAuth et l’API Gmail ; V1 possible en lecture et brouillons |
| Google Drive | Connecteur prêt à l’emploi, lecture seule | À intégrer séparément si nécessaire ; peut rester hors V1 |
| Suivi commercial | Le MCP appelle l’API sécurisée du backend pour rechercher et mettre à jour les dossiers | Le backend appelle directement les services commerciaux existants |
| Confirmation d’écriture | Mistral peut demander confirmation par action ; garder une confirmation manuelle pour toutes les écritures | L’interface et le backend imposent le récapitulatif et la confirmation avant mutation |
| Effort technique estimé | **Moyen** : API métier exposée proprement, endpoint MCP HTTPS, authentification et essais. Pas de chat ni d’intégration Gmail à construire | **Moyen à élevé** : interface chat, connexion OAuth Gmail, appels au modèle, orchestration, API métier, confirmations et journalisation |
| Coût récurrent | Abonnement Mistral Pro/Team selon compte et limites ; le MCP lui-même tourne sur votre hébergement. Vérifier si des coûts API s’ajoutent à l’usage Vibe | Hébergement du site déjà existant, plus consommation d’une API de modèle et éventuels coûts d’intégration Gmail |
| Dépendance fournisseur | Interface Work et accès aux connecteurs dépendent de Mistral ; le MCP peut rester compatible avec d’autres clients | L’interface et les données restent chez vous ; le fournisseur du modèle peut être remplacé si la couche API reste abstraite |
| Limite principale | Le modèle décide du moment et de l’usage des outils ; il faut rendre les actions du MCP étroites et vérifiables. Connecteurs MCP en préversion et serveur MCP public HTTPS requis | Davantage de code et de maintenance ; le cPanel peut imposer des limites d’exécution ou compliquer OAuth et les dépendances |
| Idéal pour | Tester vite le travail dans un chat généraliste avec Gmail/Drive | Créer une expérience sur mesure, stable et entièrement contrôlée par Jardin Sonore |

### Ce qu’il faut vérifier avant de choisir

| Question | Vérification simple | Pourquoi |
|---|---|---|
| Le MCP personnalisé est-il disponible dans Mistral Pro ? | Ouvrir Connectors et vérifier la présence de « Custom MCP Connector » ; la documentation dit que le propriétaire d’un compte Pro est admin | Évite de construire un MCP avant de confirmer son ajout au compte |
| Quelles actions Gmail Mistral expose-t-il dans Work ? | Connecter Gmail et examiner les fonctions/scopes avant d’autoriser ; faire un essai de lecture et de brouillon | Les pages produit et l’interface peuvent différer dans le détail |
| Quelle est l’adresse et la capacité de l’hébergement ? | Vérifier le backend Symfony de production, PHP, HTTPS, appels sortants et possibilités OAuth | Le MCP doit être joignable sur HTTPS ; le chatbot dédié dépend aussi de ces capacités |
| Quel parcours est le plus confortable ? | Comparer Mistral Work sur quelques mails/dossiers réels, puis décider si une interface dédiée apporterait un gain | Le coût de développement d’un chat maison n’est utile que si Work ne convient pas |

### Estimation d’effort relative

- **Essai Mistral sans écriture métier :** très faible, configuration Gmail/Drive puis quelques prompts de lecture.
- **MCP métier en lecture seule :** moyen, car il faut exposer les recherches de dossiers et le contexte utile avec une authentification solide.
- **MCP métier avec écritures confirmées :** moyen à soutenu, surtout pour le contrôle anti-doublon, les confirmations fiables, l’idempotence et les traces d’audit.
- **Chatbot privé sur le site :** soutenu, car il faut aussi bâtir le chat et le flux OAuth Gmail. Le backend existant réduit toutefois le travail de persistance métier ; le modèle lui-même reste fourni par une API distante.

## Décision et prochaine étape

Le choix est fait de prendre un compte Mistral et d'essayer **Vibe Work + Gmail + Drive**, puis de créer un MCP personnalisé connecté au backend Jardin Sonore. La comparaison avec ChatGPT et le chatbot privé n'est plus le prochain travail ; ces pistes restent des alternatives si l'essai Mistral ne convient pas.

1. Dans Vibe Work, tester sur quelques mails et un dossier Drive connus : retrouver les échanges, résumer les informations et proposer le dossier ou le rappel correspondant. Vérifier les fonctions Gmail, les autorisations OAuth et le comportement des sources Drive.
2. Dans l'offre choisie, confirmer que l'ajout d'un connecteur MCP personnalisé est disponible. Les capacités peuvent différer selon l'offre et évoluer ; ne pas déduire l'accès à l'écriture de la seule présence de MCP.
3. Si le connecteur est disponible, développer le MCP Jardin Sonore en lecture seule d'abord : rechercher des dossiers et présenter les candidats sans mutation. Ajouter ensuite des actions métier restreintes, chacune avec une confirmation explicite, une protection contre les doublons et une trace d'audit.
4. Explorer les tâches planifiées pour un récapitulatif périodique en lecture seule. Garder les modifications de dossiers dans une conversation avec confirmation plutôt que d'autoriser des écritures sans surveillance.

Une page privée hébergée dans le backend existant reste une solution de repli si l'interface Work ou l'accès MCP ne convient pas ; il n'est pas nécessaire d'héberger le modèle sur cPanel.

La V1 idéale reste : **Gmail en lecture → recherche anti-doublon → dossier candidat → proposition d’événement et de rappel → confirmation explicite → écriture backend → brouillon Gmail facultatif**. Drive sert à retrouver et consulter les documents liés ; sa mise à jour n’est pas nécessaire pour la première version. Pas d’envoi automatique, pas de création silencieuse, et pas d’accès général à toute la boîte.

## Sources consultées

- OpenAI : [Apps dans ChatGPT](https://help.openai.com/en/articles/11487775-apps-in-chatgpt), [serveurs MCP personnalisés](https://help.openai.com/en/articles/12584461-connecting-custom-mcp-servers-in-chatgpt), [Actions de GPT](https://help.openai.com/en/articles/9442513-configuring-actions-in-gpts), [FAQ GPTs](https://help.openai.com/en/articles/8554407-gpts-faq).
- Mistral : [Vibe Work Connectors](https://docs.mistral.ai/vibe/work/connectors), [Google/Gmail OAuth scopes](https://docs.mistral.ai/studio/connectors/providers/google), [MCP personnalisé](https://docs.mistral.ai/vibe/work/connectors/mcp-connectors), [Safety and approvals](https://docs.mistral.ai/vibe/work/safety-and-approvals), [tarifs](https://mistral.ai/pricing/), [Studio Connectors](https://docs.mistral.ai/studio/connectors).
- Google : [OAuth 2.0](https://developers.google.com/identity/protocols/oauth2), [scopes Gmail](https://developers.google.com/gmail/api/auth/scopes), [brouillons Gmail](https://developers.google.com/gmail/api/guides/drafts).
