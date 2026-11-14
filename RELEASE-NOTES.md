# P3 0.9.3 — consolidation technique

**Version préparée, non publiée.** La version stable diffusée est 0.9.2. Cette intervention ne constitue pas une autorisation de mise à jour.

- Six protections Apache obligatoires dans les nouvelles archives : `database`, `sessions`, `tmp`, `var/sys`, `var/private` et `var/uploads/images`. Le nouvel updater les installe ; il conserve `.htaccess` et `web.config` racine et les règles locales des modules. Les règles personnalisées dans les six fichiers gérés doivent être reportées dans la configuration propre à l’hébergeur. Pour migrer depuis un ancien updater, installer manuellement les protections manquantes avant toute sauvegarde. Sur nginx, appliquer les protections équivalentes dans la configuration du serveur.
- Empreinte du catalogue réconciliée avec l'ancrage existant ; contrôle automatique ajouté. Aucun changement de catalogue ni de ZIP de module.
- Numéro de développement distinct, garde de publication stable et suppression de l'ancien workflow candidat.
- Deux premières passes CI réussies : PHP 8.2–8.5, distribution, Chromium et premier test Apache (runs 43581882348 et 43582976427). Troisième passe réussie (43583479742), incluant les six protections Apache et la construction de l’archive. La fixation de VERSION à 0.9.3 exige une ultime validation du commit exact.
- Vérification RSA/SHA-256 des mises à jour intégrée : toute signature absente ou invalide et toute clé de confiance manquante entraînent un refus. La CI ne publie plus automatiquement. Procédure de signature hors dépôt et initialisation de la confiance : SIGNED-RELEASES.md. La clé officielle RSA 3072 bits a été générée hors dépôt et sa clé publique contrôlée ; empreinte DER SHA-256 : `26aa2418c7795362ef7c258f688f0190707972466deea7631b50f9bb7586cda6`. La signature de l’archive définitive reste à effectuer hors dépôt.
- La restauration conserve les protections système, privées et des images ainsi que la clé publique locale. Les fichiers temporaires de mise à jour sont exclus des sauvegardes. Le test Apache isolé couvre les six protections et vérifie le maintien de l’accès aux fichiers publics autorisés. LWS reste à vérifier sur une installation réelle.

## Historique 0.9.2 (publiée le 13 novembre 2026)

- Espaces **Rédaction**, **Administration** et **Configuration** strictement distincts : la rédaction reste limitée au contenu ; la configuration et la structure du site sont réservées aux administrateurs.
- Choix individuel des droits : **relecture obligatoire** ou **publication directe** pour chaque rédacteur. Le choix de publication directe autorise aussi la correction de ses articles publiés.
- Circuit de relecture : demande de publication, validation par l'administrateur, retour motivé en brouillon, visibilité de la demande de correction.
- Recherche et filtres d'articles, pagination à 25 éléments, conservation du contexte de liste après modification.
- Formulaire simplifié, sauvegarde automatique privée toutes les 30 secondes et proposition de récupération d'un brouillon. La sauvegarde automatique **ne change jamais le statut de publication**.
- Migration SQLite de schéma **8 vers 9**, non destructive : ajout du champ `articles.review_note`. La sauvegarde automatique crée sa table privée `article_autosaves` au premier usage.

Le module Articles 1.4.9 est distribué avec le catalogue 0.9.2. La release publiée ne contient pas le CoreUpdater ajouté ultérieurement dans main. Avant toute mise à jour : sauvegarder fichiers et base, préserver les réglages LWS, les thèmes personnalisés et les médias ; ne pas relancer l'installateur. Voir README.md et ROADMAP.md.
