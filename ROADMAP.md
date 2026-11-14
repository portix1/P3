# Feuille de route de P3

## Priorité actuelle — consolidation vers 1.0 (validée le 14 novembre 2026)

Cette séquence prime sur les anciens plans ci-dessous, conservés comme historique à réconcilier. Les versions indiquent des objectifs, non des dates ni des travaux déjà validés.

| Version | Objectif |
| --- | --- |
| 0.9.3 | Réduction de la dette technique |
| 0.9.4 | Audit de sécurité et permissions |
| 0.9.5 | Robustesse de l'installation, des mises à jour et de la restauration |
| 0.9.6 | Accessibilité et homogénéisation des interfaces |
| 0.9.7 | Performances et consommation des ressources |
| 0.9.8 | Consolidation et vérification de la documentation utilisateur et développeur |
| 0.9.9 | Validation par des bêta-testeurs indépendants |
| 1.0.0 | Première distribution publique stable après validation |

Marc prévoit des audits réguliers avec OpenCode et recherchera des bêta-testeurs. La documentation doit être tenue à jour dès chaque intervention ; la 0.9.8 est une étape de vérification globale, pas un report de cette obligation. Avant publication, traiter les défauts bloquants, vérifier les résultats des tests et documenter les limites connues. Aucun nouveau module sans nécessité démontrée et accord explicite. Voir AGENTS.md.

## 0.9.5 — installation, mises à jour et restauration

- Restauration sur installation isolée après interruption simulée : vérifier SQLite, médias, thème, clé publique, maintenance et règles HTTP racine. Les règles racine présentes sont préservées pendant la restauration.
- Un point de restauration à la base SQLite endommagée est refusé avant remplacement. Une restauration interrompue en cours d’écriture conserve la maintenance. La récupération passe par le panneau d’hébergement ou FTP lorsque le back-office répond 503. La reprise autonome depuis le navigateur reste à examiner.
- Examiner les migrations SQLite à chaque requête et les accès concurrents. Vérifier les protections HTTP sur les configurations prises en charge.

## 0.9.4 — sécurité et permissions

La 0.9.3 est publiée et déployée selon le mainteneur. Branche distincte `fix/0.9.4-security` ; aucune fusion ni publication avant autorisation.

- [x] Fournir la clé publique officielle dans la distribution initiale, contrôler son empreinte, préserver les clés configurées lors des mises à jour et restaurations. Code et tests ajoutés ; CI 43593007028 réussie (7 tâches) ; empreinte DER de la clé Drive recalculée et concordante. La clé privée reste hors dépôt.
- [ ] Auditer les contrôleurs et routes côté serveur selon les rôles visiteur, rédacteur et administrateur ; vérifier accès directs GET/POST et droits individuels de publication. Actualités : contrôles côté serveur et tests de routes interdites, publication forgée et liste des brouillons ajoutés. Articles : contrôles existants examinés ; reste des modules à inventorier.
- [ ] Vérifier CSRF, sessions, opérations structurelles et endpoints d’administration ; ajouter tests de refus pour chaque défaut reproduit. Jetons CSRF vides, mode strict de session, isolation du cache et statut 403 anonyme corrigés ; CI 43593521836 réussie (7 tâches). Contrôle des modules Menu, Accueil, Statistiques, Recherche, Téléchargements et Protection des contenus : accès administrateur constatés dans le code. Aperçus liés au compte et à la version des droits ; nettoyage lors des changements d’authentification, CI 43594016586 réussie. Restauration : persistance de la clé intégrée vérifiée dans le fichier local avant remplacement de l’application, préservation du verrou de mise à jour ; test de retour à une version sans clé validé par la CI 43594303706. Contrôle des versions unifié avec CoreUpdater ; interface distinguant erreur réseau et version à jour, tests validés (43594726137). Contact : exception historique pour l’accès rédacteur à la messagerie, conservée à ce stade ; les réglages et la purge sont refusés avant toute opération (CI 43595026216). Médiathèque : analyses globales et usages réservés aux administrateurs ; CI 43597818241 réussie, test explicite de la politique ajouté. Restauration et mise à jour : exclusion mutuelle par verrou commun, test de concurrence validé (43598210925, 7/7). Cache de vérification : entrées anciennes, expirées ou incohérentes refusées ; tests ajoutés.
- Audit OpenCode, suivi des constats 5 à 13. Les protections des répertoires privés sont validées par la CI Apache et incluses dans le ZIP. La vérification sur LWS reste à faire lors d’une intervention autorisée. Sessions, cookies par installation et rotation CSRF sont couverts par des tests. Le diagnostic signale les comptes MD5 et la protection de config.json. Le fichier existant passe en 0600 à sa prochaine écriture sur Unix. Le repli MD5 subsiste jusqu’à conversion ou réinitialisation des comptes concernés.
- CSP : chargements jsDelivr limités aux ressources KaTeX déclarées. Styles en ligne, polices et lecteurs vidéo conservés pour la compatibilité des thèmes et de l’éditeur. Migrations à chaque requête et protections HTTP racine à examiner en 0.9.5.
- Contact 1.1.2 : accès administrateur imposé même si un ancien manifeste demande un accès rédacteur. Le catalogue approuvé distribue Articles 1.4.9 et le thème Six Cordes UX Clair 0.1.7.
- Publication : version 0.9.4 préparée pour la signature locale. Vérifier le commit exact, l’archive et son empreinte. L’updater 0.9.3 exige une clé publique locale. Le test du téléchargement officiel de bout en bout dépend de la mise à disposition de la release signée.
- Les rapports OpenCode complets manquants restent à rapprocher des constats vérifiés.

## 0.9.3 — dette technique : suivi de l'audit OpenCode 0.9.2

Source : synthèse transmise par Marc le 14 novembre 2026 ; audit statique, sans exécution des tests PHP. Rapport complet AUDIT-P3-0.9.2.md non fourni à ce stade. Références auditées selon OpenCode : release v0.9.2 au commit b608a8e et main au commit 3c68ae6 (+18 commits). Vérifier l'état réel du dépôt avant intervention. Bilan initial : 5 fermés, 2 partiels (5, 13), 6 ouverts (6, 7, 9, 10, 11, 12), 1 acceptable. Récupérer les rapports complets pour qualifier les constats anciens sans les inventer.

- [ ] N1 : inclure la protection var/private/.htaccess dans la distribution et les mises à jour ; vérifier le refus HTTP effectif des données privées.
- [ ] N2 : distinguer les protections HTTP gérées par P3 des réglages propres à l'hébergeur ; appliquer les correctifs de sécurité sans écraser les redirections ou réglages LWS ; sauvegarde, tests et rollback.
- [ ] N3 : identifier distinctement code de développement et artefact publié ; vérifier tag, version, commit et provenance ; interdire la publication de deux contenus différents sous une même version.
- [ ] N4 : définir et implémenter une authentification indépendante des artefacts du cœur (signature avec clé de confiance ancrée, gestion des clés et procédure de rotation) ; valider les modalités avant activation. SHA-256 de même origine ne garantit pas l'authenticité.
- [x] N5 : régénérer l'empreinte du catalogue et vérifier automatiquement sa cohérence.
- [ ] N6 : réconcilier documentation, versions de modules, notes de version et état de publication ; exclure les documents préparatoires obsolètes de l'archive.
- [x] N7 : supprimer le workflow candidat 0.9.2 après vérification de son absence de dépendances.
- [x] N8 : ajouter un test de distribution des protections privées et des tests de mise à jour, refus HTTP et préservation des règles locales.

Priorité proposée : N1/N2/N3/N8 avant publication ; N4 avant de considérer la mise à jour automatique comme digne de confiance ; N5/N6/N7 dans la même consolidation. Ne pas fusionner ni publier sans accord. Audit statique : aucune preuve d'exploitation distante ni validation dynamique par OpenCode. Les constats anciens restent ouverts jusqu'à preuve de correction. La synthèse présente une incohérence de comptage des sévérités N1–N8 (table : quatre moyens et quatre faibles).

### Première intervention 0.9.3-dev

Code et tests proposés pour N1, N3, N5, N6, N7 et N8 ; N2 étendu aux six .htaccess gérés par P3 (database, sessions, tmp, var/sys, var/private, var/uploads/images) ; tests de conservation des règles racine et locales des modules. Aucun écrasement global des règles HTTP locales. N4 : vérificateur de signature, procédure de signature hors dépôt et arrêt de la publication automatique implémentés ; clé officielle créée hors dépôt le 14 novembre 2026, clé publique RSA 3072 bits vérifiée et archivée sur Drive ; signature de l’archive définitive et validation de bout en bout restent ouvertes. Protection privée et clé locale préservées par la restauration ; test Apache isolé ajouté. La vérification HTTP sur LWS reste ouverte. La première migration depuis un updater antérieur doit installer manuellement la protection privée si elle manque. Première passe validée par la CI (run 43581882348, PHP 8.2–8.5 et Chromium). Deuxième passe CI réussie (run 43582976427, signature, restauration, Apache, PHP et Chromium). Troisième passe CI réussie (43583479742) : six protections Apache, exclusion du staging, matrice PHP, navigateur et archive. VERSION passe à 0.9.3 pour valider l’archive définitive non publiée ; signature, essai sur copie et autorisation de fusion restent requis. Les cases cochées correspondent aux travaux implémentés et déjà couverts par les deux premières passes ; la validation finale demeure distincte. Aucun déploiement confirmé.


## Première distribution autonome

Le paquet de base comprend le moteur, les composants indispensables et un thème neutre par défaut. Il doit permettre d’installer P3 sur un serveur vierge.

### Acquis

- [x] Suite automatisée validée sur PHP 8.2, 8.3 et 8.4.
- [x] Premier essai d’installation neuve. Il a révélé deux points corrigés depuis.
- [x] Titre P3 proposé par l’installateur ([PR nº 2](https://github.com/portix1/P3/pull/2)).
- [x] Nom SQLite saisi ou généré à l’installation. P3 conserve le chemin historique pour les sites existants ([PR nº 4](https://github.com/portix1/P3/pull/4)).

- [x] Archive candidate 0.8.1 assemblée depuis `a9376da` et validée après extraction sous PHP 8.4.
- [x] Installation de l’archive candidate confirmée sur le serveur de test.
- [x] Restauration testée sur une copie de l’archive avec un nom SQLite personnalisé. Articles, identifiants, URL, compte et configuration ont été comparés avant et après.

- [x] Mise à jour de 0.8.01-rc1 vers 0.8.1 sur une installation de test sous PHP 8.4. Sauvegarde préalable, conservation des articles, comptes, slugs et réglages, maintien du fichier SQLite historique et intégrité SQLite contrôlés.

### Ordre des prochains travaux

1. **Préparer une archive candidate.** Définir les composants livrés et vérifier les ressources et leurs licences. Contrôler que l’archive ne contient ni base, ni configuration privée, ni médias d’une installation.
2. **Tester l’installation depuis cette archive.** Sur un serveur vierge, vérifier les prérequis, les permissions, le formulaire et les deux modes de nommage SQLite. Se connecter comme administrateur, publier un article et ouvrir sa page publique. Vérifier les guides fournis au fil de cet essai.
3. **Tester une mise à jour sur une copie existante.** Vérifier la conservation des contenus, comptes, réglages et URL. Tester la sauvegarde et la restauration, y compris avec un nom SQLite personnalisé.
4. **Préparer la publication.** Vérifier que l’archive testée correspond au code validé et consigner les conditions d’installation et de mise à jour.

## Distribution des extensions

Après validation du paquet de base :

1. Préparer le catalogue des versions et les archives vérifiées du moteur, des modules et des thèmes.
2. Préparer le lanceur PHP qui permet de choisir les composants et de les installer.
3. Ajouter la détection et l’installation des mises à jour depuis l’administration.

Le déploiement sur Univers Guitare suit son propre cycle.

## Réorganisation UX du back-office

- [x] Inventaire des fonctions par module et classement commun au menu et au tableau de bord.
- [x] Trois espaces Rédaction, Administration et Configuration, avec icônes et droits existants.
- [x] Pages séparées pour identité, apparence, référencement et messagerie.
- [x] Réglages des modules actifs accessibles dans Configuration.
- [x] Installation directe des thèmes après validation sans conflit.
- [x] Parcours HTTP vérifiés sous PHP 8.2 et 8.4 sur une installation de test.
- [ ] Retours visuels sur le serveur de test de Marc.

L’inventaire et la procédure de test figurent dans [UX-BACK-OFFICE.md](UX-BACK-OFFICE.md).

## Back-office — règle validée le 4 novembre 2026

L’organisation en trois pages Rédaction, Administration et Configuration est obligatoire pour les modules et plugins à venir. Voir AGENTS.md et UX-BACK-OFFICE.md.

- [x] Tableau de bord limité à l’état du site et aux trois accès.
- [x] Menu limité à l’espace courant, couleurs bleu, orange et violet.
- [x] Thème actif affiché et liens de mise à jour conditionnels.
- [x] Actualités réintégré malgré son contrôleur fourni par le moteur.
- [x] Contrôle de toutes les entrées administrables livré avec la validation automatisée.
- [ ] Retour visuel de Marc sur la nouvelle archive.

## Accueil — priorité P1, conception validée le 8 novembre 2026

- [x] Maquette à compositions variées, images par article ou zone de catégorie, nombre variable de catégories.
- [x] Première implémentation du module, tirage pondéré, cache commun, mesures facultatives de visibilité et de clic.
- [ ] Validation visuelle du module intégré par Marc avant production.
- [ ] Ajustement des poids après constitution de données exploitables.

Voir `var/modules/home/README.md` pour les limites et les réglages. L’ajout d’images dans les fiches Affiliation reste séparé.

## Distribution modulaire — correction du découpage

Le socle 0.8.4 apporte des déclarations génériques de navigation, d’actions publiques sans session anonyme et de version minimale. Il ne connaît pas le module Accueil. Accueil 1.0.1 est distribué séparément sous forme d’un ZIP installable : styles, écrans, tirage, mesures et migration de ses anciennes tables sont contenus dans le module. La 0.8.3-rc4 doit recevoir le socle compatible une seule fois. L’archive complète de test 2713cd56 est remplacée pour les nouveaux essais par cette séparation.

## Migration production — 8 novembre 2026

Cible demandée : 0.8.4-rc4 depuis 0.8.01-beta1-preview5. Archive réelle reçue (223 articles). Reprendre impérativement les correctifs canoniques absents de la branche de développement ; préserver configuration, base et thèmes de production. Livrer une mise à niveau complète, sans installation neuve ni changement automatique de la cible d’accueil.

## Accueil 1.0.3 — disposition demandée

Zones sur deux ou trois colonnes selon la largeur disponible sur ordinateur, une seule sur mobile. Texte libre en pleine largeur ; ordre et compositions préservés. Aucun titre général au-dessus des zones. Mise à jour autonome du module, sans modification du moteur ou du thème.

## Accueil 1.0.4 — miniatures et habillage

Images compactes hautes de trois lignes de texte, alignées à gauche avec habillage réel puis retour du texte sur toute la largeur. Miniatures WebP 256 px maximum sans modifier les originaux. Format commun sur ordinateur/mobile, images d’articles/catégories et texte libre.

## Accueil 1.0.5 — miniatures refusées en production

Corriger les noms des miniatures pour respecter la liste autorisée du .htaccess des images : 32 caractères hexadécimaux et extension WebP. Ne pas assouplir les protections Apache. Régénération automatique à la consultation, sans modifier les originaux.

## Accueil 1.0.6 — hauteur prioritaire des miniatures

Corriger la boîte carrée : image haute de trois lignes de texte avec largeur automatique selon ses proportions, sans limite à 35 % de la colonne. Habillage à gauche conservé. Vérifier portrait, paysage et carré.

## Prochains travaux — décisions du 8 novembre 2026

### Demandes validées, à développer

- [x] **Bloc « Derniers articles »** : proposer les trois publications les plus récentes, titre, court extrait et miniature facultative. Ordre chronologique de publication, sans tirage aléatoire, sans inclure les brouillons ou publications futures. Aucun texte d’annonce à rédiger et aucune dépendance au module Actualités. Conserver les autres sélections semi-aléatoires ; masquer le bloc s’il ne contient rien de publiable.
- [x] **Séparation des trois espaces et des rôles (12 novembre 2026)** : Rédaction ne donne accès qu’au contenu éditorial ; Administration regroupe les opérations courantes (menus principal et de bas de page) ; Configuration les interventions rares qui définissent le site (composition de l’accueil, thèmes, modules, diagnostics). Les rédacteurs ne peuvent modifier la structure du site. Les correctifs #17 et #18 sont intégrés à main et livrés. Les tests PHP 8.2 à 8.5, HTTP et Chromium ont réussi. Marc confirme le 12 novembre la résolution du blocage de déploiement et le regroupement dans Configuration.
- [x] **Sélecteur visuel de médiathèque** : bouton de choix avec miniatures, recherche et aperçu pour les images associées aux zones ; réutiliser le média sans duplication. Livré dans Accueil 1.1.0, avec réutilisation des médias existants.

### Évolutions de l’accueil retenues le 12 novembre 2026

- [x] **Hiérarchie visuelle de l’accueil** : moins de blocs identiques ; alterner article développé, groupes de liens et zones plus larges, dans une composition stable inspirée du principe de découverte de Wikipédia. Conserver le tirage semi-aléatoire que Marc juge utile.
- [x] **Aperçu avant publication de la composition** : vérifier les zones et leur rendu ordinateur/mobile avant de modifier la page publique. Ne pas confondre consultation de l’accueil actuel et prévisualisation de changements non publiés.
- [x] **Ordre des zones par glisser-déposer**, avec commandes clavier équivalentes, pour éviter de saisir manuellement des numéros.
- [x] **Explications des zones vides dans la gestion** : distinguer zone désactivée, catégorie sans contenu publiable et module source désactivé ; conserver une page publique sans blocs vides ni messages techniques.
- [ ] **Évaluation de la circulation depuis l’accueil** : utiliser le rapport d’expositions et de clics existant avant de modifier la pondération. Prévoir un volume de données suffisant ; ne pas changer le tirage sur quelques clics seulement. Ce point rejoint la phase d’ajustement déjà prévue.

Statut : séparation des espaces et fonctions de composition Accueil 1.1.0 livrées. L’évaluation des mesures et l’ajustement de la pondération restent ouverts. Ne pas ajouter de « méta-news » pour alimenter artificiellement l’accueil.

## Regroupement dans Configuration — validation du 12 novembre 2026

- [x] Gestion des modules, Diagnostics et Protection des contenus (si active) regroupés dans Configuration par le [correctif #18](https://github.com/portix1/P3/pull/18), livré dans le patch Back-office 3.
- [x] Gestion des modules accessible par `Admin-config-modules.html`, avec compatibilité de `Admin-modmgr.html`.
- [x] Résolution confirmée par Marc sur Univers Guitare le 12 novembre 2026. Une ancienne file d’attente FileZilla réenvoyait les fichiers locaux et écrasait les fichiers corrigés. Aucun patch supplémentaire n’est nécessaire pour cet incident.

Avant tout nouvel envoi FTP, supprimer les anciens transferts en attente et vérifier que la copie locale contient les correctifs courants. La fermeture de FileZilla ne vaut pas confirmation de suppression de sa file. Cette validation concerne le regroupement dans Configuration, pas une recette exhaustive de tous les écrans du back-office.

## Accueil 1.1.0 — livraison du 12 novembre 2026

Marc demande de terminer les travaux d’accueil, y compris les propositions de composition, d’aperçu, de réorganisation des zones et d’explication des zones vides. Accueil 1.1.0 regroupe ces fonctions, les derniers articles et le sélecteur visuel de médiathèque. La validation automatisée et la livraison sont suivies dans la [proposition #19](https://github.com/portix1/P3/pull/19). L’aperçu utilise les styles du module sans l’habillage du thème. La validation visuelle sur le site de Marc reste à effectuer après import du ZIP. La pondération reste inchangée tant qu’aucune analyse des mesures réelles ne justifie un ajustement.

Marc confirme également que les en-têtes de tableaux illisibles sont corrigés. Ce point est clôturé.
