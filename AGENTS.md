# Règles de développement de P3

## Lecture préalable et décisions en vigueur

Avant toute modification, lire [docs/consignes-p3.md](docs/consignes-p3.md), ROADMAP.md et les contrats concernés, puis consulter l’index et les documents 03, 04 et 05 du référentiel Drive lorsqu’ils sont accessibles. Le document consolidé contient leurs liens. Signaler une source inaccessible ou une contradiction. La dernière décision explicite de Marc prévaut sur un tableau ou une note historique.

Pour une nouvelle fonctionnalité, appliquer la méthode convenue inspirée de Spec Kit. Intention, cadrage, options et brainstorming avec Marc, arbitrages, spécification courte, plan technique, implémentation, validation et gel. Une idée après gel ouvre un nouveau cadrage. Une demande d’étude ou d’ajout à la roadmap reste à ce stade. Pour une correction autorisée, reproduire le défaut et traiter sa cause dans le périmètre convenu, sans redemander l’accord à chaque étape autorisée.

P3 est universel. LWS, Windows, macOS, les serveurs locaux et les environnements automatisés sont des plateformes de validation. Séparer les mécanismes génériques des particularités observées et indiquer les environnements effectivement testés. Les règles d’Univers Guitare ou de Six Cordes ne deviennent pas des valeurs imposées aux autres sites.

P3 1.0.0 est publiée depuis le 20 novembre 2026. Les priorités en cours figurent en tête de ROADMAP.md. Préserver le cadrage ultérieur de la réécriture du seul module Téléchargements et des autres pistes inscrites à la roadmap.

## Organisation obligatoire du back-office

Cette organisation s’applique à toute création ou modification du moteur, d’un module ou d’un plugin. Une évolution ne doit pas réintroduire un écran mélangé ou une navigation indépendante.

- Le tableau de bord présente l’état du moteur, des modules et du thème actif, puis dirige vers trois pages distinctes.
- Rédaction regroupe uniquement les fonctions éditoriales et utilise le bleu. Le rôle Rédacteur ne donne accès qu’aux contenus autorisés.
- Administration regroupe la gestion courante du site, dont les comptes et les droits, la composition de l’accueil et ses résultats. Elle utilise l’orange et est réservée aux administrateurs.
- Configuration regroupe les interventions rares qui définissent le site : réglages du tirage de l’accueil, menus principal et de bas de page, identité, thèmes, modules, diagnostics et réglages techniques. Elle utilise le violet et est réservée aux administrateurs.
- Les thèmes, leur choix et leur installation appartiennent à Configuration.
- Le menu latéral est limité à l’espace ouvert. Les trois accès d’espace restent disponibles selon les droits.
- Chaque entrée possède un libellé et une icône. La couleur accompagne les libellés.
- Les cartes d’état utilisent une présentation commune et une valeur principale en bleu. Un lien de mise à jour apparaît uniquement lorsqu’une version plus récente existe. Une erreur de vérification ne doit jamais être assimilée à une absence de mise à jour.
- Les fonctions d’un même module peuvent appartenir à des espaces différents. Le classement se fait par fonction.
- Les contributions sont centralisées dans src/Navigation/AdminNavigation.php et réutilisées par les pages d’espace et le menu. Un contrôleur fourni par le moteur, comme Actualités, doit rester visible même sans config.php dans son module.
- Le rôle Rédacteur ne peut jamais modifier la structure du site : menus, accueil, catégories et arborescence, séries et ordre, modules ou thèmes. Les contrôles de rôle et de formulaire doivent interdire les opérations structurelles côté serveur, y compris par URL directe, POST ou API.

## Vérification et documentation

Toute modification de navigation doit vérifier administrateur, rédacteur et visiteur, module actif et désactivé, ainsi que les refus d’accès direct GET/POST/API aux opérations structurelles pour le rédacteur. Exécuter tools/smoke_test_admin_navigation.php et les tests touchés.

Actualiser UX-BACK-OFFICE.md et la documentation fonctionnelle concernée dans src/Installation/default-content.json lorsqu’un parcours change. Le guide de développement des modules doit conserver ces règles pour les nouveaux composants.

Le code fait référence sur GitHub. Les décisions et l’état d’avancement sont reportés dans le référentiel Google Drive lorsque cet accès est disponible. Distinguer décision validée, code testé et validation visuelle par Marc.

## Publication des articles selon le rédacteur

L’administrateur fixe individuellement le niveau de confiance de chaque rédacteur. Deux options sont présentées en français : « Relecture obligatoire » (par défaut) et « Publication directe ». La seconde active ensemble les permissions internes `publish` et `edit_published`, afin que le rédacteur puisse aussi corriger un article qu’il a publié. La première les désactive ensemble.

Les états possibles d’un article sont brouillon (`draft`), en attente de relecture (`pending`) et publié (`published`). Un rédacteur sans permission de publication ne peut demander la relecture que pour un article qu’il est autorisé à modifier. L’administrateur peut approuver et publier, ou renvoyer en brouillon avec une explication. Modifier un article en attente le replace en brouillon ; il faut soumettre à nouveau. Les permissions sont impérativement vérifiées côté serveur pour chaque transition, y compris si l’utilisateur modifie les valeurs envoyées par son navigateur. Les images et les articles existants ne sont pas modifiés automatiquement par cette évolution.

Une fonctionnalité destinée au rédacteur ne doit pas exiger qu’il comprenne les termes PHP, SEO, slug ou droits binaires. Les messages doivent nommer l’action concrète (« Enregistrer le brouillon », « Demander la publication », « Accepter et publier »), sans jargon inexpliqué.

Ce travail est soumis à un accord explicite de Marc avant toute fusion dans `main` et avant une mise à jour de production. Les branches de test ne sont pas des versions publiées.

## Discipline de développement — consolidation vers 1.0

- Avant toute intervention, consulter ROADMAP.md, les décisions existantes, les contrats entre composants et le code concerné. Vérifier la branche et la version réellement déployée ; ne pas supposer leur identité.
- Identifier la cause des défauts, préférer une correction localisée et réutiliser les composants existants. Éviter les doublons, contournements permanents et refontes hors périmètre. Retirer le code devenu inutile après vérification des compatibilités et migrations.
- Chaque changement fonctionnel ou technique doit inclure, dans la même intervention, les tests pertinents, la mise à jour de la documentation utilisateur et développeur concernée, des notes de version et de la feuille de route si leur état change. Si une documentation n'est pas affectée, le signaler. Aucun travail ne peut être déclaré terminé si ces éléments restent à faire.
- Ajouter un test de non-régression pour chaque défaut reproductible. Vérifier les impacts sur le moteur, les modules, les thèmes, les permissions, les installations existantes et la distribution selon le périmètre. Consigner les tests exécutés, leurs résultats et les limites de validation.
- GitHub est la référence du code et des règles ; synchroniser les documents de suivi sur Drive lorsque possible. Les décisions validées, les travaux en cours, les tests automatisés et les confirmations en production doivent rester distincts. Les audits OpenCode et les retours des bêta-testeurs alimentent une liste de défauts priorisés, sans déclencher de modifications concurrentes non coordonnées.
- Jusqu'à 1.0, privilégier la consolidation. Pas de nouveau module sauf nécessité démontrée et accord explicite. Ne pas fusionner dans main ni publier ou déployer sans autorisation explicite.
- En cas de contexte incomplet, consulter le dépôt et signaler les contradictions ; ne pas inventer d'état, de résultats de tests ni de décisions. Les règles doivent s'appliquer quel que soit l'assistant chargé du travail.

## Configuration regroupée par module — 20 novembre 2026

La page Configuration et son menu latéral affichent une seule entrée par module. Si plusieurs fonctions appartiennent au même module dans cet espace, elles sont accessibles depuis une page-menu dédiée. Une fonction unique reste accessible directement. Le regroupement utilise l’inventaire commun et s’applique aux modules tiers. Accueil présente ses Réglages dans Configuration ; Composer l’accueil et Résultats de l’accueil sont en Administration (classement du code 1.0.0, arbitrage technique délégué par Marc le 26 novembre 2026). Les fonctions du moteur sont regroupées dans Configuration du site. Le fil d’Ariane relie chaque fonction à sa page-menu. Les accès administrateur restent contrôlés côté serveur.

## Administration : gestion des articles — 20 novembre 2026

Administration et son menu latéral présentent une entrée « Gestion des articles ». Elle ouvre une page-menu donnant accès aux catégories, aux séries et à l’ordre des articles. Les adresses et les fonctions existantes sont conservées. Le fil d’Ariane permet de revenir à cette page-menu et son entrée reste sélectionnée dans chaque fonction. Le regroupement partage le mécanisme de Configuration ; une fonction unique reste directe. Les permissions administrateur restent contrôlées côté serveur. Aucun changement de données ni de version propre au module Articles : ce parcours est fourni par le moteur candidat 0.9.10.
