# Réorganisation du back-office

Version de développement 0.9.4-dev — non publiée.

Le menu et le tableau de bord utilisent un inventaire commun. Les fonctions sont classées dans Rédaction, Administration et Configuration. Chaque entrée possède une icône et un libellé. Rédaction est réservée aux opérations sur le contenu ; Administration regroupe la gestion courante et Configuration les choix durables qui définissent le site. Les deux derniers espaces sont réservés aux administrateurs.

| Composant | Rédaction | Administration | Configuration |
| --- | --- | --- | --- |
| Moteur | — | Comptes et droits | Identité, gestion des modules, diagnostics, référencement, messagerie, apparence |
| Articles | Brouillons, demande de publication et édition autorisée | Validation et retour pour correction, catégories, séries et ordre (administrateurs) | Navigation des articles |
| Actualités | Brouillons autorisés selon les droits ; publication directe uniquement si autorisée | Publication des brouillons par un administrateur, suppression et épinglage | — |
| Médiathèque | Fichiers, images, insertion et gestion des médias | — | — |
| Menu principal | — | — | Organisation des liens |
| Menu de bas de page | — | — | Organisation des liens |
| Accueil | — | — | Composition, réglages et résultats pour les administrateurs seulement |
| Téléchargements | Fichiers et catégories | — | — |
| Contact | Messagerie pour les rédacteurs disposant déjà de cet accès | Messagerie pour les administrateurs | Destinataire, notifications, conservation de la corbeille |
| Affiliation | Produits, offres et partenaires, accès administrateur conservé | — | Paramètres de présentation |
| Statistiques | — | Audience, journaux applicatifs, journal d’audit | Mode de mesure et rétention |
| Protection des contenus | — | — | Corbeille, révisions, points de restauration et durées de conservation |
| Recherche | — | État et reconstruction de l’index | — |
| Authentification interne | — | Service interne sans entrée de menu | — |
| Thèmes | — | Alerte de mise à jour dans les cartes du tableau de bord | Choix du thème public, import, validation, installation et restauration |

Les fonctions d’un module désactivé ne sont pas ajoutées au menu. Les composants installés restent consultables dans Gestion des modules. Pour une extension tierce inconnue de l’inventaire, le classement utilise son accès déclaré. Les actions éditoriales autorisées aux rédacteurs figurent dans Rédaction ; les opérations courantes dans Administration et les interventions durables dans Configuration. Le rôle Rédacteur ne confère aucun droit de modifier la structure du site.

## Formulaires et parcours

Les cases à cocher sont placées à gauche de leur libellé. Les libellés longs restent lisibles sur plusieurs lignes.

Les pages Identité, Référencement, Messagerie et Apparence enregistrent leurs propres champs. Les anciens liens Admin-config.html et Admin-config-themes.html restent valides. Les paramètres des modules possèdent leur entrée dans Configuration. La gestion des modules se trouve dans Configuration. Les alertes de mise à jour restent accessibles depuis le tableau de bord.

L’import d’un thème contrôlé et sans conflit déclenche son installation directement. Un conflit avec une personnalisation bloque l’installation et liste les fichiers. Le thème public est choisi séparément depuis Apparence du site. Les protections de sauvegarde, de restauration, de rôle et de formulaire sont conservées.

Depuis 0.9.3, le moteur peut installer une mise à jour signée depuis le back-office. La 0.9.4 fournit la clé publique officielle. Les actualités ne disposent pas encore d’un état intermédiaire « en attente » : le rédacteur sans droit de publication enregistre un brouillon, que l’administrateur publie après vérification.

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

Dans Configuration → Protection des contenus → Rollback, la version affichée provient du fichier VERSION inclus dans chaque point. Les anciens points sans ce fichier utilisent leur manifeste. Les nouveaux points enregistrent la version du code sauvegardé, même après une mise à jour manuelle qui a laissé un ancien suivi release.json.

Chaque point dispose de « Supprimer ce point ». La suppression exige une confirmation explicite et un formulaire administrateur protégé contre les requêtes CSRF. Elle efface uniquement le point sélectionné ; le site et les autres points restent conservés. Il n’y a aucune purge automatique. Création, restauration et suppression sont verrouillées pour éviter leur exécution simultanée. Une restauration remet le suivi de version en accord avec le code restauré.

## Module Accueil

Configuration → Composer l’accueil (`Admin-home.html`, administrateurs seulement) compose les zones, leurs images et leur ordre. Configuration → Réglages de l’accueil (`Admin-home-settings.html`, administrateurs) règle le tirage et la mesure facultative. Configuration → Résultats de l’accueil (`Admin-home-report.html`, administrateurs) consulte les compteurs. Les entrées sont centralisées dans AdminNavigation ; elles disparaissent quand le module est désactivé. Le raccordement à la racine utilise le choix existant de page d’accueil dans Configuration → Identité. Les écrans de composition, de réglages et de résultats sont interdits aux rédacteurs, y compris par accès direct.

## Déclarations des modules — socle 0.8.4

Les nouveaux modules déclarent leurs contributions dans `desc.xml`, bloc `AdminNavigation`. `AdminNavigation.php` les lit et réutilise le même inventaire dans les pages d’espace et le menu. Le module peut fournir plusieurs actions, chacune avec espace, rôle, icône et clé de traduction ; ses contrôleurs doivent vérifier les permissions côté serveur, sans se fier au masquage des menus. Une action accessible aux rédacteurs doit appartenir à Rédaction ; les déclarations donnant le rôle editor à une action Administration ou Configuration sont refusées. Les fonctions structurelles nécessitent toujours le rôle administrateur. Les fonctions réservées aux administrateurs peuvent être en Administration ou Configuration. Les modules sans déclaration conservent leur classement historique. Aucun classement particulier à Accueil n’est requis dans le moteur.

## Composition de l’accueil — 1.1.0

Configuration → Composer l’accueil conserve son accès administrateur uniquement. La composition propose les derniers articles, un choix visuel de médias, un ordre par glisser-déposer ou boutons clavier, un aperçu sans publication et un enregistrement global atomique. Les raisons de masquage des zones sont visibles uniquement dans cet écran. L’aperçu montre les contenus et styles du module, sans le thème public. Les fichiers à téléverser doivent être enregistrés dans la zone avant l’aperçu. La pondération statistique reste inchangée.

## Relecture des articles : choix individuel

Le profil de chaque rédacteur comporte deux choix : **Relecture obligatoire**, sélectionné par défaut, ou **Publication directe**. Ce dernier donne également le droit de corriger ses propres articles publiés. L’administrateur peut choisir un autre mode pour chaque rédacteur sans modifier les droits des collègues.

Le rédacteur prépare un brouillon. S’il est soumis à relecture, il choisit « Demander la publication ». L’article passe en attente de relecture et reste invisible pour le public. L’administrateur peut l’accepter ou le renvoyer avec une explication ; le rédacteur lit celle-ci dans sa liste et son formulaire d’article. Dès qu’un article en attente est modifié, il redevient brouillon, et une nouvelle demande sera nécessaire. La publication et le renvoi sont protégés par des contrôles d’autorisation côté serveur, indépendants de l’affichage des boutons.

La migration du socle SQLite ajoute `review_note` (texte facultatif) à la table des articles sans en supprimer les données. Le numéro du schéma passe de 8 à 9. Les archives officielles d’Articles et de P3 restent à reconstruire avant diffusion ; une branche GitHub n’est pas une mise à jour du serveur.

Critère pratique de vérification : un rédacteur peut créer un brouillon, demander sa publication et retrouver une éventuelle demande de correction sans devoir comprendre des termes techniques. La refonte générale de la fiche utilisateur, l’ordre des champs de l’éditeur, le tableau de bord et les liens des thèmes restent des travaux distincts à vérifier en navigateur.
