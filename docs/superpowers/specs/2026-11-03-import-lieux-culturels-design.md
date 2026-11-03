# Import des lieux culturels

L'import est préparé par département depuis la Base des lieux culturels ouverts. Les médiathèques, librairies, théâtres, centres culturels et lieux de musique sont retenus ; les indices jeunesse sont prioritaires sans exclure les autres lieux. La programmation jeune public n'est pas présumée.

Un fichier de travail local persiste les données source et les décisions, permet l'interruption et la reprise, et ne contient aucun secret. L'aperçu distingue les lieux nouveaux, déjà reliés, candidats à examiner et erreurs. Les candidats sont présentés en tableau comparatif clairement étiqueté ; l'utilisateur choisit de lier, créer ou ignorer. Les lieux sans candidat sont proposés à la création. L'application exige une commande explicite distincte et reste relançable après interruption.

La relation source et identifiant externe réutilise `directory_import_link` et sa contrainte d'unicité. Le nom, le site, l'adresse et le type déjà renseignés ne sont pas écrasés ; les coordonnées nouvelles sont ajoutées sans réactiver celles désactivées ni modifier une opposition ou un désabonnement existant. Une adresse nouvelle est éligible à la newsletter suivant les règles existantes. Les sources et identifiants sont visibles sur la fiche annuaire. La date de modification du lieu est conservée dans le fichier de travail si disponible ; elle ne prouve pas la qualité des données et ne déclenche aucune synchronisation automatique.

Deux options ont été examinées : fichier local par département ou file persistante en base. Le fichier a été retenu pour reprendre l'examen dans le CLI existant sans ajouter une interface ou une migration. L'importeur CAF existant n'est pas directement réutilisable pour l'écriture : il peut écraser des informations existantes. Le rapprochement et les liens externes existants restent les points d'appui.

La licence publiée dans la page et les métadonnées API diverge ; aucune réutilisation effective ni import en production avant clarification. Aucun lancement automatique ni modification de production n'est inclus.
