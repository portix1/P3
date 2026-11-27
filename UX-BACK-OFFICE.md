# Réorganisation du back-office

Référence fonctionnelle, alignée sur le code 1.0.0 le 27 novembre 2026. État de revue au 20 novembre 2026 dans [les consignes consolidées](docs/consignes-p3.md).

Le menu et le tableau de bord utilisent un inventaire commun. Les fonctions sont classées dans Rédaction, Administration et Configuration. Chaque entrée possède une icône et un libellé. Rédaction est réservée aux opérations sur le contenu ; Administration regroupe la gestion courante et Configuration les choix durables qui définissent le site. Les deux derniers espaces sont réservés aux administrateurs.

| Composant | Rédaction | Administration | Configuration |
| --- | --- | --- | --- |
| Moteur | — | Comptes et droits | Identité, gestion des modules, diagnostics, référencement, messagerie, apparence |
| Articles | Brouillons, demande de publication et édition autorisée | Validation et retour pour correction, catégories, séries et ordre (administrateurs) | Navigation des articles |
| Actualités | Brouillons autorisés selon les droits ; publication directe uniquement si autorisée | Publication des brouillons par un administrateur, suppression et épinglage | — |
| Médiathèque | Fichiers, images, insertion et gestion des médias | — | — |
| Menu principal | — | — | Organisation des liens |
| Menu de bas de page | — | — | Organisation des liens |
| Accueil | — | Composition et résultats, administrateurs seulement | Réglages du tirage et de la mesure |
| Téléchargements | — | Fichiers et catégories | — |
| Contact | — | Messagerie pour les administrateurs | Destinataire, notifications, conservation de la corbeille |
| Affiliation | — | Produits, offres et partenaires, accès administrateur uniquement | Paramètres de présentation |
| Statistiques | — | Audience, provenance, passages des robots, journaux applicatifs, journal d’audit | Mode de mesure, comptage des robots et rétention |
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

Préparer une copie de test sauvegardée et utiliser le paquet exact de la version à valider. Vérifier son origine, son empreinte et sa signature selon son canal. Suivre le guide d’installation ou de mise à jour correspondant au scénario. Consigner l’environnement et distinguer installation neuve, mise à jour et restauration.

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

Administration → Composer l’accueil (`Admin-home.html`, administrateurs seulement) compose les zones, leurs images et leur ordre. Configuration → Réglages de l’accueil (`Admin-home-settings.html`, administrateurs) règle le tirage et la mesure facultative. Administration → Résultats de l’accueil (`Admin-home-report.html`, administrateurs) consulte les compteurs. Composer et Résultats relèvent de la gestion courante ; seuls les réglages, rarement modifiés, restent en Configuration. Les entrées sont centralisées dans AdminNavigation ; elles disparaissent quand le module est désactivé. Le raccordement à la racine utilise le choix existant de page d’accueil dans Configuration → Identité. Les écrans de composition, de réglages et de résultats sont interdits aux rédacteurs, y compris par accès direct.

## Déclarations des modules — socle 0.8.4

Les nouveaux modules déclarent leurs contributions dans `desc.xml`, bloc `AdminNavigation`. `AdminNavigation.php` les lit et réutilise le même inventaire dans les pages d’espace et le menu. Le module peut fournir plusieurs actions, chacune avec espace, rôle, icône et clé de traduction ; ses contrôleurs doivent vérifier les permissions côté serveur, sans se fier au masquage des menus. Une action accessible aux rédacteurs doit appartenir à Rédaction ; les déclarations donnant le rôle editor à une action Administration ou Configuration sont refusées. Les fonctions structurelles nécessitent toujours le rôle administrateur. Les fonctions réservées aux administrateurs peuvent être en Administration ou Configuration. Les modules sans déclaration conservent leur classement historique. Aucun classement particulier à Accueil n’est requis dans le moteur.

## Composition de l’accueil — 1.1.0

Administration → Composer l’accueil conserve son accès administrateur uniquement. La composition propose les derniers articles, un choix visuel de médias, un ordre par glisser-déposer ou boutons clavier, un aperçu sans publication et un enregistrement global atomique. Les raisons de masquage des zones sont visibles uniquement dans cet écran. L’aperçu montre les contenus et styles du module, sans le thème public. Les fichiers à téléverser doivent être enregistrés dans la zone avant l’aperçu. La pondération statistique reste inchangée.

## Relecture des articles : choix individuel

Le profil de chaque rédacteur comporte deux choix : **Relecture obligatoire**, sélectionné par défaut, ou **Publication directe**. Ce dernier donne également le droit de corriger ses propres articles publiés. L’administrateur peut choisir un autre mode pour chaque rédacteur sans modifier les droits des collègues.

Le rédacteur prépare un brouillon. S’il est soumis à relecture, il choisit « Demander la publication ». L’article passe en attente de relecture et reste invisible pour le public. L’administrateur peut l’accepter ou le renvoyer avec une explication ; le rédacteur lit celle-ci dans sa liste et son formulaire d’article. Dès qu’un article en attente est modifié, il redevient brouillon, et une nouvelle demande sera nécessaire. La publication et le renvoi sont protégés par des contrôles d’autorisation côté serveur, indépendants de l’affichage des boutons.

La migration du socle SQLite ajoute `review_note` (texte facultatif) à la table des articles sans en supprimer les données. Le numéro du schéma passe de 8 à 9. Les archives officielles d’Articles et de P3 restent à reconstruire avant diffusion ; une branche GitHub n’est pas une mise à jour du serveur.

Critère pratique de vérification : un rédacteur peut créer un brouillon, demander sa publication et retrouver une éventuelle demande de correction sans devoir comprendre des termes techniques. La refonte générale de la fiche utilisateur, l’ordre des champs de l’éditeur, le tableau de bord et les liens des thèmes restent des travaux distincts à vérifier en navigateur.

Sur petit écran, ouvrez le menu avec Entrée ou Espace. Le focus passe au premier lien du menu. Échap referme le menu et replace le focus sur son bouton.

## Relecture et listes restées ouvertes

Les actions de changement d’état portent l’empreinte de la version affichée. P3 compare cette empreinte au contenu enregistré dans la transaction qui effectue la transition. Une modification, une nouvelle soumission ou une action déjà effectuée invalide l’ancien formulaire si la version enregistrée diffère. Un refus HTTP 409 invite à recharger la liste et à relire la version actuelle. Les formulaires ouverts avant cette mise à jour doivent être rechargés.

## Configuration regroupée par module — 20 novembre 2026

La page Configuration et son menu latéral affichent une seule entrée par module. Si plusieurs fonctions appartiennent au même module dans cet espace, elles sont accessibles depuis une page-menu dédiée. Une fonction unique reste accessible directement. Le regroupement utilise l’inventaire commun et s’applique aux modules tiers. Accueil présente ses Réglages dans Configuration ; Composer et Résultats sont en Administration. Les fonctions du moteur sont regroupées dans Configuration du site. Le fil d’Ariane relie chaque fonction à sa page-menu. Les accès administrateur restent contrôlés côté serveur.

Les adresses existantes des écrans restent valides. Les nouvelles pages-menu sont calculées à partir des fonctions accessibles et des modules actifs. Un module désactivé ne possède plus de page-menu accessible. Rédacteurs et visiteurs sont refusés. Les pages-menu sont en lecture seule et refusent POST.

AdminNavigation::sections conserve l’inventaire des fonctions. Chaque entrée porte le préfixe de son module et son libellé de configuration. AdminNavigation::configurationGroups produit la navigation regroupée et les enfants à partir de cet inventaire. Les langues des modules peuvent fournir prefix_configuration pour leur groupe et label_short pour le nom court d’une fonction. Ces clés sont facultatives et possèdent des libellés de repli.

## Administration : gestion des articles — 20 novembre 2026

Administration et son menu latéral présentent une entrée « Gestion des articles ». Elle ouvre une page-menu donnant accès aux catégories, aux séries et à l’ordre des articles. Les adresses et les fonctions existantes sont conservées. Le fil d’Ariane permet de revenir à cette page-menu et son entrée reste sélectionnée dans chaque fonction. Le regroupement partage le mécanisme de Configuration ; une fonction unique reste directe. Les permissions administrateur restent contrôlées côté serveur. Aucun changement de données ni de version propre au module Articles : ce parcours est fourni par le moteur candidat 0.9.10.
