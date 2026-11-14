# P3 0.9.3-dev — consolidation technique

**Branche de développement non publiée.** La version stable diffusée est 0.9.2. Cette intervention ne constitue pas une autorisation de mise à jour.

- Protection `var/private/.htaccess` obligatoire dans les nouvelles archives ; le nouvel updater peut la déployer. Les règles HTTP racine et les configurations locales restent préservées. Un updater ancien ignore ce fichier : installer la protection manuellement si absente et vérifier le refus HTTP avant toute sauvegarde ou mise à jour. Sur nginx, configurer explicitement un refus d'accès au répertoire privé ; `.htaccess` ne s'y applique pas.
- Empreinte du catalogue réconciliée avec l'ancrage existant ; contrôle automatique ajouté. Aucun changement de catalogue ni de ZIP de module.
- Numéro de développement distinct, garde de publication stable et suppression de l'ancien workflow candidat.
- Tests de distribution et de mise à jour complétés ; résultats CI à vérifier. Aucun résultat dynamique n'est revendiqué.
- Vérification RSA/SHA-256 des mises à jour intégrée : toute signature absente ou invalide et toute clé de confiance manquante entraînent un refus. La CI ne publie plus automatiquement. Procédure de signature hors dépôt et initialisation de la confiance : SIGNED-RELEASES.md. Aucun jeu de clés officiel n'a encore été provisionné ; ne pas publier avant cette étape.
- La restauration conserve la protection privée et la clé publique locale ; tests de non-régression ajoutés. Un test Apache isolé doit vérifier le refus HTTP sur un faux snapshot privé. La configuration LWS réelle reste à vérifier.

## Historique 0.9.2 (publiée le 13 novembre 2026)

- Espaces **Rédaction**, **Administration** et **Configuration** strictement distincts : la rédaction reste limitée au contenu ; la configuration et la structure du site sont réservées aux administrateurs.
- Choix individuel des droits : **relecture obligatoire** ou **publication directe** pour chaque rédacteur. Le choix de publication directe autorise aussi la correction de ses articles publiés.
- Circuit de relecture : demande de publication, validation par l'administrateur, retour motivé en brouillon, visibilité de la demande de correction.
- Recherche et filtres d'articles, pagination à 25 éléments, conservation du contexte de liste après modification.
- Formulaire simplifié, sauvegarde automatique privée toutes les 30 secondes et proposition de récupération d'un brouillon. La sauvegarde automatique **ne change jamais le statut de publication**.
- Migration SQLite de schéma **8 vers 9**, non destructive : ajout du champ `articles.review_note`. La sauvegarde automatique crée sa table privée `article_autosaves` au premier usage.

Le module Articles 1.4.9 est distribué avec le catalogue 0.9.2. La release publiée ne contient pas le CoreUpdater ajouté ultérieurement dans main. Avant toute mise à jour : sauvegarder fichiers et base, préserver les réglages LWS, les thèmes personnalisés et les médias ; ne pas relancer l'installateur. Voir README.md et ROADMAP.md.
