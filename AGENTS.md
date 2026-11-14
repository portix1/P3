# Règles de développement de P3

## Organisation obligatoire du back-office

Cette organisation s’applique à toute création ou modification du moteur, d’un module ou d’un plugin. Une évolution ne doit pas réintroduire un écran mélangé ou une navigation indépendante.

- Le tableau de bord présente l’état du moteur, des modules et du thème actif, puis dirige vers trois pages distinctes.
- Rédaction regroupe uniquement les fonctions éditoriales et utilise le bleu. Le rôle Rédacteur ne donne accès qu’aux contenus autorisés.
- Administration regroupe la gestion courante du site, dont les comptes et les droits. Elle utilise l’orange et est réservée aux administrateurs.
- Configuration regroupe les interventions rares qui définissent le site : composition de l’accueil, menus principal et de bas de page, identité, thèmes, modules, diagnostics et réglages techniques. Elle utilise le violet et est réservée aux administrateurs.
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
