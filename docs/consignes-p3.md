# Consignes de P3 et revue de leur application

Consolidation du 20 novembre 2026. Les décisions les plus récentes de Marc prévalent sur les passages historiques contradictoires. Une proposition, un développement, un test automatisé et une validation d’usage sont des états distincts.

## Références à consulter

Avant une intervention, lire AGENTS.md, le présent document, ROADMAP.md et les contrats concernés. Consulter également l’index et les documents 03, 04 et 05 du référentiel Drive lorsqu’ils sont accessibles. Vérifier la branche, les fichiers actifs, les versions, les paquets et le périmètre réellement autorisé. Signaler les sources indisponibles et les contradictions restantes.

| Source | Contenu |
| --- | --- |
| [Index du référentiel](https://docs.google.com/document/d/1pnVf37YLFqc33iqIuoS5fAoWoqErHurkrYKMPVJestc/edit) | Statuts des décisions et protocole de reprise |
| [Architecture et fonctions](https://docs.google.com/document/d/1SDUenmTk7G2oFu70MxD6sDLATWSWJAZ1xlsEY-7znPQ/edit) | Architecture, données, thèmes et distribution |
| [WYSIWYG et UX](https://docs.google.com/document/d/1nWT5XN-Ldc_ic6F3Z4GMsdJoWzOhXIs9g64su-SnYxI/edit) | Exigences d’édition et de lecture |
| [Roadmap et anomalies](https://docs.google.com/document/d/1sDjN_Deyid4oSVxJOnSA_78DdjjtayevhGm04psqwes/edit) | Décisions datées, incidents et travaux futurs |
| [AGENTS.md](../AGENTS.md) et [UX-BACK-OFFICE.md](../UX-BACK-OFFICE.md) | Règles du dépôt et parcours administratifs |
| [SIGNED-RELEASES.md](../SIGNED-RELEASES.md) et [distribution/TRUST.md](../distribution/TRUST.md) | Signature du moteur et confiance dans les extensions |

Les documents Drive contiennent de l’historique. Leurs anciennes mentions des menus en Administration, de Contact accessible au rédacteur ou d’un hébergeur comme cible du produit doivent être rapprochées des décisions ultérieures. Le code courant constitue une preuve d’implémentation, sans modifier à lui seul les exigences convenues.

## Méthode de travail et autorisations

- Pour une nouvelle fonctionnalité, appliquer la méthode convenue inspirée de Spec Kit. Intention, cadrage, possibilités, brainstorming et arbitrages avec Marc, spécification courte, plan technique, implémentation, validation, puis gel. L’outillage Spec Kit n’est pas imposé.
- Définir le besoin, les critères de fin, les cas limites, les exclusions, les données, le mobile, la sécurité, les tests et le retour arrière avant de développer. Une idée nouvelle après gel ouvre un nouveau cadrage.
- Une demande d’étude, de spécification ou d’ajout à la roadmap reste à ce stade. Elle n’autorise pas à coder une solution supposée acquise.
- Pour un défaut dont la correction est autorisée, analyser la cause, reproduire le cas et corriger dans ce périmètre. Réutiliser les services existants et éviter les ajouts annexes. Continuer les étapes autorisées sans solliciter une nouvelle confirmation à chaque étape.
- La consolidation vers 1.0 couvre stabilité, recherche, installation et mise à jour, sauvegarde et restauration, droits, sécurité, documentation et maintenabilité. Les nouveaux chantiers fonctionnels majeurs sont différés.
- Les corrections ont repris sur instruction de Marc le 20 novembre. Ses essais sont prévus sur une version regroupée plus avancée. Distinguer ces essais futurs des tests automatisés déjà exécutés.
- Une fusion dans main, une publication et une intervention en production exigent l’accord explicite de Marc. Une branche ou une PR en brouillon reste une préparation.
- Le code et les règles sont suivis sur GitHub. Consigner les décisions et l’avancement dans Drive quand l’accès le permet. Regrouper les interventions et communiquer en français clair.

## Produit universel et architecture

- P3 sert à des sites génériques. LWS, Windows, macOS, les serveurs locaux et les environnements automatisés sont des plateformes de validation. Traiter les mécanismes et compatibilités du serveur, puis documenter les particularités observées.
- Conserver une faible consommation de ressources et la compatibilité avec PHP 8.2+, SQLite et les hébergements mutualisés. Les extensions prévues n’imposent pas de service externe payant, de processus permanent ni de CRON obligatoire.
- Séparer moteur, modules, plugins et thèmes. Versionner leurs paquets indépendamment et contrôler leurs prérequis et identités.
- Réutiliser les fonctions communes de sécurité, données, médias, édition, navigation et rendu. Une fonction autonome doit appartenir à son module. L’objectif du contrat d’extension est d’installer un module sans modifier le noyau.
- La consolidation générale du contrat modulaire est ciblée après la 1.0, vers la 1.1.0. Les interfaces proposées dans Drive restent à concevoir et valider avant de les considérer disponibles.
- Respecter la propriété des tables, les migrations transactionnelles et les sauvegardes. Une désactivation conserve les données et retire les contributions publiques du module.
- Les règles visuelles de Six Cordes et les paramètres d’Univers Guitare concernent ce thème ou ce site. Le moteur et les autres sites gardent leur configuration propre.

## Trois espaces et droits

| Espace | Fonctions convenues |
| --- | --- |
| Rédaction, bleu | Articles, actualités et médias nécessaires à la rédaction, selon les droits individuels |
| Administration, orange | Gestion courante, comptes, droits, messages, statistiques, affiliation et téléchargements |
| Configuration, violet | Structure et réglages rares, accueil, menus principal et de bas de page, identité, thèmes, modules, diagnostics et protection des contenus |

Le tableau de bord présente l’état du moteur, des composants et du thème actif et ouvre les trois espaces. Le menu latéral se limite à l’espace courant. Les accès, libellés, icônes et couleurs reposent sur l’inventaire commun AdminNavigation. Classer chaque fonction selon son usage, même lorsqu’un module contribue à plusieurs espaces.

Un rédacteur peut choisir une catégorie existante autorisée pour son contenu. Il ne peut pas créer ou réorganiser la structure, les catégories, séries, menus, accueil, modules ou thèmes. Les autorisations sont contrôlées côté serveur pour GET, POST et API. Masquer une commande ne suffit pas.

L’administrateur choisit individuellement Relecture obligatoire ou Publication directe. La seconde active ensemble publish et edit_published. Un article en attente modifié retourne en brouillon et doit être soumis de nouveau. Le retour pour correction demande une explication. L’approbation doit porter sur la version relue. Le critère de simplicité reste qu’un rédacteur puisse publier sans vocabulaire technique inexpliqué.

## Éditeur et médias

- Conserver Quill et rapprocher le traitement des objets et tableaux de Word ou LibreOffice. Chaque bouton natif ou ajouté possède une info-bulle.
- Les images conservent alt, choix décoratif explicite lorsqu’il est utilisé, légende, liens et ancrage. Légende et image se déplacent et se sauvegardent ensemble.
- Alignements gauche et droite avec habillage carré dans le flux, centrage sur sa propre ligne, quatre poignées de redimensionnement et déplacement fiable. Le déplacement vertical reste conditionné à la conservation du contenu adjacent.
- Tableaux éditables avec opérations sur lignes et colonnes, dimensions et cellules fusionnées. Vérifier le collage de texte, les images et légendes, les liens et la restitution après sauvegarde, fermeture et réouverture.
- Encadrés génériques, images visibles et supprimables dans ces encadrés, comportement cohérent du collage et des sauts de ligne.
- Vidéos redimensionnables. Liens externes collés ouverts dans un nouvel onglet par défaut, avec choix désactivable par lien et attributs de sécurité adaptés.
- PDF et ZIP insérables depuis la médiathèque dans Quill. Ce parcours reste indépendant du module Téléchargements.
- Préserver les médias originaux et les références existantes. Les variantes responsives, le chargement différé et le zoom doivent respecter les petites images, les icônes, les légendes et l’habillage.
- Vérifier les nouveaux contenus et les contenus existants dans l’interface chargée. La présence du code et un succès historique ne prouvent pas le comportement de la version courante.

## Thèmes, lecture et navigation

- Respecter ThemeContract, ThemeManifest, les chemins, dépendances et limites du gestionnaire de thèmes. Un ZIP autonome comporte un seul dossier correspondant à son identifiant et seulement les fichiers déclarés.
- Conserver l’identifiant d’un thème mis à jour et augmenter sa version. Créer un identifiant différent nécessite une décision explicite. Examiner les erreurs d’inspection avant de proposer un contournement.
- Modifier la source CSS, produire une feuille minifiée cohérente et versionnée, puis recalculer les empreintes du manifeste. Examiner la cascade et les styles des modules avant d’ajouter des surcharges.
- Pour Six Cordes UX Clair, respecter les couleurs et images validées, notamment #FCF9F3 et #8A8A88, les panneaux clairs, les titres de tableaux lisibles et les angles demandés. Préserver la justification choisie sans césure automatique. L’ancien plafonnement forcé et l’alignement à gauche ne doivent pas être réintroduits à partir d’un rapport historique.
- Le clic sur le nom d’une entrée ouvre sa page. Une flèche séparée déplie ses enfants, avec une branche ouverte à la fois. Préserver menu replié, sommaire compact, navigation de série et liens utiles.
- Respecter l’absence de titre général Accueil, les miniatures compactes et proportionnelles et la composition validée. Attendre des données suffisantes avant d’ajuster la pondération.
- Contrôler lecture, zoom et commandes au clavier et à 320/375 px, sur mobile et grand écran. Les références d’usage comprennent iPhone 12 mini et 1920 × 1080, avec Samsung S23+ en contrôle secondaire. Une émulation ne vaut pas essai sur l’appareil physique.

## Données, sécurité et distribution

- Préserver contenus, identifiants, URL existantes, relations, ordre des séries et catégories, médias et personnalisations. Une opération éditoriale ou structurelle doit rester dans le périmètre demandé.
- Proposer un nom SQLite choisi ou généré, validé et centralisé dans la configuration. Préserver le fonctionnement des bases antérieures, interdire l’écrasement et l’accès HTTP aux données privées.
- Protéger sessions, CSRF, opérations administratives, fichiers transférés et contenus privés. Exclure les brouillons des sorties publiques et des caches. Échapper les données et désinfecter le HTML riche.
- Vérifier les archives, chemins, empreintes, prérequis et rétrogradations. Distinguer le catalogue d’extensions actuellement ancré à un commit et la signature indépendante du moteur.
- Préparer un point de restauration cohérent, contrôler l’intégrité SQLite, verrouiller les opérations concurrentes et prévoir la reprise d’un échec. Préserver les règles locales du serveur et les clés de confiance.
- Les mises à jour normales passent par le back-office. Les transferts manuels ciblés restent un recours adapté au cas concerné. Un site existant conserve ses données et n’utilise pas l’installateur pour se mettre à jour.
- Respecter GPL-3.0-or-later, les licences tierces et la provenance des ressources. Les paquets publics excluent données privées, secrets, bases d’exploitation et fichiers temporaires.
- Pour les requêtes contrôlées sur les sites de Marc, respecter au moins 500 ms d’intervalle et limiter le nombre de pages. Employer les marqueurs d’audit convenus lorsque le moyen utilisé le permet. Cette limite ne s’applique pas aux fixtures locales isolées.

## Documentation et preuve de validation

La documentation fonctionnelle reste disponible dans P3. Son contenu initial est maintenu avec le code et fourni aux nouvelles installations. Une mise à jour ne doit pas ajouter ces articles à un site existant. Les guides administrateur, rédacteur, installation et maintenance et bêta-test restent cohérents avec les parcours réels.

Chaque changement documente les fichiers, versions, migrations éventuelles, comportement attendu, tests effectués, résultats, limites et retour arrière. Utiliser une correspondance exigence, réalisation, test et résultat. Distinguer tests statiques, tests fonctionnels, rendu automatisé, essai de la distribution exacte et validation par Marc.

Les textes d’interface donnent les informations nécessaires à l’action. Les notes de patch, détails de développement et promesses de fonctions futures restent dans le suivi du projet. Le diagnostic d’un site existant ne contrôle pas la présence des articles initiaux de documentation.

## Chantiers futurs et limites décidées

- Reprendre de zéro le seul module Téléchargements. Son périmètre sera défini dans un nouveau cadrage. Les propositions précédentes de fonctionnalités et de compatibilité ne valent pas spécification acceptée.
- Explorer un MCP P3 pour l’administration et la rédaction. L’inscription de cette piste ne lance pas son développement.
- Préparer une documentation exhaustive module par module, avec captures annotées des parcours validés.
- Étudier à faible priorité l’installation mutualisée. L’installation autonome actuelle reste la base de travail jusqu’à un choix d’architecture.
- Consolider la modularité après 1.0 et étudier Partage social, FAQ, Galerie, Annuaire, Agenda et Newsletter selon leurs cadrages. Ces modules ont leurs propres versions et échéances.
- AMP, export PDF spécifique côté site, ajout des séries au menu et contrôles interactifs sans usage concret restent différés ou hors périmètre selon leur décision d’origine.

## Écarts constatés le 20 novembre 2026

La revue porte sur les références accessibles et les points de code examinés. Elle ne remplace pas une recette exhaustive de chaque écran et fonctionnalité.

| Exigence | Constat | Suite nécessaire |
| --- | --- | --- |
| Lecture préalable et méthode de cadrage | AGENTS.md ne reprenait pas la méthode Spec Kit ni l’ensemble du protocole Drive. Ces lectures n’ont pas été systématiques lors des derniers correctifs. | Règles consolidées ici et rappelées dans AGENTS.md |
| Source actuelle des trois espaces | Les documents Drive 03 et 04 gardent les menus en Administration, alors que les décisions ultérieures et AGENTS.md les placent en Configuration | Reporter une rectification datée dans Drive et signaler les anciens tableaux |
| Téléchargements en Administration | Le code de main et de la candidate #54 le classe encore dans Rédaction. Le déplacement existe dans #53, en brouillon | Traiter ce classement dans le prochain lot de correction autorisé et le distinguer de la future réécriture |
| Affiliation en Administration | AdminNavigation le classe dans Rédaction, avec accès administrateur | Corriger le classement et sa documentation dans un lot fonctionnel dédié |
| Documentation UX cohérente | Tableau UX daté 0.9.4-dev, ancien scénario de test 0.8.3-rc4 et Téléchargements en Rédaction | Actualiser ces passages et conserver le défaut du code explicitement identifié |
| Consolidation des décisions | Les ajouts bêta-test, Téléchargements et MCP sont dans #53. Modularité 1.1 et installation mutualisée se trouvent dans Drive. La documentation exhaustive n’était pas encore inscrite dans la roadmap du dépôt | Rapprocher les décisions dans la proposition documentaire issue de cette revue |
| Documentation tenue au fil des changements | Les lots #52 et #54 actualisent guides, contenu initial et roadmap. Leur résultat n’avait pas encore été reporté dans Drive | Ajouter les références de validation dans Drive |
| Versions et publication | README de main annonce encore 0.9.8. #52 et #54 sont des branches de test. Articles 1.4.10 n’est pas encore publié au catalogue | Réconcilier README, catalogue, archive et signatures lors de la préparation de la version regroupée |
| Validation des derniers correctifs | #52 et #54 passent chacune les sept tâches CI sous Linux, dont PHP 8.2–8.5, Apache et navigateur | Essais utilisateur regroupés ultérieurs. Windows, macOS, IIS et nginx restent à valider |
| Éditeur, rendu et modules dans leur ensemble | Exigences relues. Les derniers lots ne constituent pas une nouvelle recette de toutes leurs fonctions | Suivre une matrice module par module lors de la validation 1.0 et de la documentation illustrée |

La revue documentaire ne modifie pas le comportement du CMS. La restauration signalée, les mises à jour de thèmes, la hiérarchie de Configuration et les confirmations restantes de recherche conservent leur suivi propre.
