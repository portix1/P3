# P3 0.9.5 — développement

- La restauration préserve les règles Apache ou IIS présentes à la racine du site. Un test reproduit l’état laissé par une interruption et vérifie le rétablissement des données et la levée de la maintenance.

- Une sauvegarde SQLite endommagée est refusée avant le remplacement des fichiers. Si une restauration échoue après avoir commencé, le site reste en maintenance.

# P3 0.9.4 — sécurité et permissions

Version préparée pour signature, non publiée. La version stable 0.9.3 est publiée et installée en production selon le mainteneur.

- Clé publique officielle intégrée au moteur : aucune opération FTP pour les nouvelles installations ; contrôle de son empreinte SHA-256. Une clé locale existante reste prioritaire et ne peut pas être remplacée par le nouvel updater. Une clé locale invalide provoque un refus. La clé privée reste exclusivement hors dépôt.
- Distribution : exclusion des fichiers de clés usuels, sauf la clé publique officielle à son chemin exact ; tests de régression ajoutés. Les installations 0.9.3 nécessitent encore une installation ponctuelle de la clé locale pour effectuer leur première mise à jour signée.
- Actualités : contrôle des droits individuels de création, modification et publication ; filtrage des brouillons inaccessibles ; épinglage réservé aux administrateurs. Les brouillons sont publiés par l’administrateur si le rédacteur n’a pas la publication directe.
- Sessions et formulaires : jetons vides refusés, identifiants de session stricts, réponses avec session non mises en cache, Vary: Cookie et réinitialisation sans référent ; accès anonyme à l’administration renvoie 403.
- Aperçus privés : association au compte authentifié et à la version de ses droits ; suppression lors des changements d’authentification. Les anciens liens d’aperçu deviennent invalides, sans modification des articles ou actualités enregistrés.
- Restauration : préservation de la clé officielle vérifiée dans le fichier local lorsque la version restaurée ne la fournit pas ; conservation de la clé locale et du verrou de mise à jour existants.
- Vérification de version unifiée avec CoreUpdater ; message distinct lorsque la vérification est indisponible. Tests des trois états validés ; cache ancien, expiré, absent ou incohérent refusé, tests de régression ajoutés.
- Contact 1.1.2 : messagerie et réglages réservés aux administrateurs. Un ancien manifeste qui accordait l’accès aux rédacteurs ne peut plus rétablir ce droit.
- Médiathèque : consultation des usages réservée aux administrateurs pour protéger les références aux brouillons et les chemins internes. Ajout, sélection et description des images toujours accessibles aux rédacteurs.
- Restauration et mise à jour ne peuvent plus modifier simultanément l’installation ; verrou partagé et test de refus concurrent.
- Suivi de l’audit OpenCode : rotation des jetons CSRF après connexion et réinitialisation, refus des anciens jetons ; écriture atomique privée de config.json (0600 sur Unix). Un fichier déjà présent passe en 0600 à sa prochaine écriture.

- Cookies de session Secure si HTTPS direct ou URL HTTPS configurée, y compris derrière un proxy ; aucune confiance dans X-Forwarded-Proto pour cette décision. Le serveur doit rediriger HTTP vers HTTPS ; l’environnement local HTTP reste compatible.

- Anciens mots de passe MD5 : détection centralisée et comptage agrégé sans divulgation des empreintes ; conversion au premier login conservée et testée. Compatibilité transitoire maintenue pour éviter de bloquer les comptes anciens ; suppression prévue après migration ou réinitialisation.

- Diagnostics : contrôle des comptes MD5 sans affichage des empreintes et de la protection de config.json sur Unix.

- Sessions isolées par installation, y compris lorsque plusieurs sites partagent un domaine. Une reconnexion est nécessaire après la mise à jour.
- CSP : seuls les fichiers KaTeX utilisés sont autorisés sur jsDelivr. Les styles en ligne restent nécessaires à l’éditeur et aux contenus existants. Les sources vidéo et les polices des thèmes sont conservées.

## Historique 0.9.3

Publiée le 14 novembre 2026 et installation confirmée par le mainteneur. Validation automatisée finale : run 43589468620.

- Six protections Apache obligatoires dans les nouvelles archives : `database`, `sessions`, `tmp`, `var/sys`, `var/private` et `var/uploads/images`. Le nouvel updater les installe ; il conserve `.htaccess` et `web.config` racine et les règles locales des modules. Les règles personnalisées dans les six fichiers gérés doivent être reportées dans la configuration propre à l’hébergeur. Pour migrer depuis un ancien updater, installer manuellement les protections manquantes avant toute sauvegarde. Sur nginx, appliquer les protections équivalentes dans la configuration du serveur.
- Empreinte du catalogue réconciliée avec l'ancrage existant ; contrôle automatique ajouté. Aucun changement de catalogue ni de ZIP de module.
- Numéro de développement distinct, garde de publication stable et suppression de l'ancien workflow candidat.
- Deux premières passes CI réussies : PHP 8.2–8.5, distribution, Chromium et premier test Apache (runs 43581882348 et 43582976427). Troisième passe réussie (43583479742), incluant les six protections Apache et la construction de l’archive. La fixation de VERSION à 0.9.3 exige une ultime validation du commit exact.
- Vérification RSA/SHA-256 des mises à jour intégrée : toute signature absente ou invalide et toute clé de confiance manquante entraînent un refus. La CI ne publie plus automatiquement. Procédure de signature hors dépôt et initialisation de la confiance : SIGNED-RELEASES.md. La clé officielle RSA 3072 bits a été générée hors dépôt et sa clé publique contrôlée ; empreinte DER SHA-256 : `26aa2418c7795362ef7c258f688f0190707972466deea7631b50f9bb7586cda6`. La signature de l’archive définitive a été effectuée hors dépôt par le mainteneur.
- La restauration conserve les protections système, privées et des images ainsi que la clé publique locale. Les fichiers temporaires de mise à jour sont exclus des sauvegardes. Le test Apache isolé couvre les six protections et vérifie le maintien de l’accès aux fichiers publics autorisés. LWS reste à vérifier sur une installation réelle.

## Historique 0.9.2 (publiée le 13 novembre 2026)

- Espaces **Rédaction**, **Administration** et **Configuration** strictement distincts : la rédaction reste limitée au contenu ; la configuration et la structure du site sont réservées aux administrateurs.
- Choix individuel des droits : **relecture obligatoire** ou **publication directe** pour chaque rédacteur. Le choix de publication directe autorise aussi la correction de ses articles publiés.
- Circuit de relecture : demande de publication, validation par l'administrateur, retour motivé en brouillon, visibilité de la demande de correction.
- Recherche et filtres d'articles, pagination à 25 éléments, conservation du contexte de liste après modification.
- Formulaire simplifié, sauvegarde automatique privée toutes les 30 secondes et proposition de récupération d'un brouillon. La sauvegarde automatique **ne change jamais le statut de publication**.
- Migration SQLite de schéma **8 vers 9**, non destructive : ajout du champ `articles.review_note`. La sauvegarde automatique crée sa table privée `article_autosaves` au premier usage.

Le module Articles 1.4.9 est distribué avec le catalogue 0.9.2. La release publiée ne contient pas le CoreUpdater ajouté ultérieurement dans main. Avant toute mise à jour : sauvegarder fichiers et base, préserver les réglages LWS, les thèmes personnalisés et les médias ; ne pas relancer l'installateur. Voir README.md et ROADMAP.md.
