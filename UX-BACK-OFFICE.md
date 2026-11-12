# Réorganisation du back-office

Version de test 0.8.3-rc4.

Le menu et le tableau de bord utilisent un inventaire commun. Les fonctions sont classées dans Rédaction, Administration et Configuration. Chaque entrée possède une icône et un libellé. Le classement indique un emplacement et respecte les autorisations existantes.

| Composant | Rédaction | Administration | Configuration |
| --- | --- | --- | --- |
| Moteur | — | Comptes et droits, diagnostics | Identité, gestion des modules, référencement, messagerie, apparence |
| Articles | Articles, catégories, séries, ordre de lecture | — | Navigation des articles |
| Actualités | Rédaction et publication | — | — |
| Médiathèque | Fichiers, images, insertion et gestion des médias | — | — |
| Menu principal | Organisation des liens | — | — |
| Menu de bas de page | — | — | Organisation des liens |
| Accueil | — | — | Composition pour les rédacteurs et administrateurs, réglages et résultats pour les administrateurs |
| Téléchargements | Fichiers et catégories | — | — |
| Contact | Messagerie pour les rédacteurs disposant déjà de cet accès | Messagerie pour les administrateurs | Destinataire, notifications, conservation de la corbeille |
| Affiliation | Produits, offres et partenaires, accès administrateur conservé | — | Paramètres de présentation |
| Statistiques | — | Audience, journaux applicatifs, journal d’audit | Mode de mesure et rétention |
| Protection des contenus | — | Corbeille, révisions et points de restauration | Durées de conservation |
| Recherche | — | État et reconstruction de l’index | — |
| Authentification interne | — | Service interne sans entrée de menu | — |
| Thèmes | — | Alerte de mise à jour dans les cartes du tableau de bord | Choix du thème public, import, validation, installation et restauration |

Les fonctions d’un module désactivé ne sont pas ajoutées au menu. Les composants installés restent consultables dans Gestion des modules. Pour une extension tierce inconnue de l’inventaire, le classement utilise son accès déclaré. Les extensions accessibles aux rédacteurs figurent dans Rédaction, les autres dans Administration.

## Formulaires et parcours

Les cases à cocher sont placées à gauche de leur libellé. Les libellés longs restent lisibles sur plusieurs lignes.

Les pages Identité, Référencement, Messagerie et Apparence enregistrent leurs propres champs. Les anciens liens Admin-config.html et Admin-config-themes.html restent valides. Les paramètres des modules possèdent leur entrée dans Configuration. La gestion des modules se trouve dans Configuration. Les alertes de mise à jour restent accessibles depuis le tableau de bord.

L’import d’un thème contrôlé et sans conflit déclenche son installation directement. Un conflit avec une personnalisation bloque l’installation et liste les fichiers. Le thème public est choisi séparément depuis Apparence du site. Les protections de sauvegarde, de restauration, de rôle et de formulaire sont conservées.

Les liens de mise à jour proposent actuellement les archives à télécharger. L’installation distante en un clic reste à développer.

## Vérification sur le serveur de test

Faire une sauvegarde de l’installation de test. Extraire le ZIP 0.8.3-rc4 à sa racine en remplaçant les fichiers applicatifs. L’archive ne contient pas de base ni de configuration privée.

Ouvrir Admin.html après connexion puis examiner les trois espaces. Tester l’enregistrement d’une page de configuration et contrôler que les réglages des autres pages sont conservés. Vérifier avec un compte rédacteur que seuls ses accès autorisés apparaissent.

La documentation initiale de l’installateur est actualisée. Les articles documentaires déjà enregistrés dans une installation existante conservent leur contenu.

Les trois boutons de l’en-tête ouvrent trois pages distinctes. Rédaction utilise le bleu, Administration l’orange et Configuration le violet. Le bloc de comptage éditorial est retiré du haut du tableau de bord.

## Règle permanente

Cette organisation est obligatoire pour toute création ou modification de module, de plugin ou du moteur. Elle est inscrite dans AGENTS.md et dans le guide de développement des modules. Le menu latéral est limité à l’espace courant. Le tableau de bord reste une page d’état et un carrefour vers les trois espaces.

Le thème actif figure dans une carte d’état avec son nom en bleu. Les liens de mise à jour sont affichés uniquement si une version supérieure a été détectée. Une vérification indisponible doit être distinguée d’une absence de mise à jour.

Actualités est administré par le contrôleur du moteur et ne possède pas de config.php. Ce cas est pris en charge et testé pour les administrateurs, les rédacteurs et les modules désactivés. Tous les modules administrables livrés sont contrôlés par tools/smoke_test_admin_navigation.php.


## Points de restauration

Dans Administration → Protection des contenus → Rollback, la version affichée provient du fichier VERSION inclus dans chaque point. Les anciens points sans ce fichier utilisent leur manifeste. Les nouveaux points enregistrent la version du code sauvegardé, même après une mise à jour manuelle qui a laissé un ancien suivi release.json.

Chaque point dispose de « Supprimer ce point ». La suppression exige une confirmation explicite et un formulaire administrateur protégé contre les requêtes CSRF. Elle efface uniquement le point sélectionné ; le site et les autres points restent conservés. Il n’y a aucune purge automatique. Création, restauration et suppression sont verrouillées pour éviter leur exécution simultanée. Une restauration remet le suivi de version en accord avec le code restauré.

## Module Accueil

Configuration → Composer l’accueil (`Admin-home.html`, administrateurs et rédacteurs) compose les zones, leurs images et leur ordre. Configuration → Réglages de l’accueil (`Admin-home-settings.html`, administrateurs) règle le tirage et la mesure facultative. Configuration → Résultats de l’accueil (`Admin-home-report.html`, administrateurs) consulte les compteurs. Les entrées sont centralisées dans AdminNavigation ; elles disparaissent quand le module est désactivé. Le raccordement à la racine utilise le choix existant de page d’accueil dans Configuration → Identité. Aucun écran séparé ne modifie les autorisations existantes.

## Déclarations des modules — socle 0.8.4

Les nouveaux modules déclarent leurs contributions dans `desc.xml`, bloc `AdminNavigation`. `AdminNavigation.php` les lit et réutilise le même inventaire dans les pages d’espace et le menu. Le module peut fournir plusieurs actions, chacune avec espace, rôle, icône et clé de traduction ; ses contrôleurs restent responsables des permissions. Une fonction accessible aux rédacteurs peut figurer dans Rédaction ou dans Configuration, selon son usage. Dans Configuration, les rédacteurs voient uniquement leurs écrans autorisés. Les fonctions réservées aux administrateurs peuvent être en Administration ou Configuration. Les modules sans déclaration conservent leur classement historique. Aucun classement particulier à Accueil n’est requis dans le moteur.
