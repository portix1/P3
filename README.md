# P3 — CMS PHP modulaire

**Version de travail du dépôt :** `0.8.1` (aucune release publiée à ce stade)  
**Archive source historique :** `0.8.01-beta1-preview5`  
**Licence du projet :** `GPL-3.0-or-later`

## État de cette copie

Cette arborescence est une **copie de travail expurgée des données privées d’une installation**. Elle n’est pas encore une distribution autonome testée sur une installation neuve et ne doit pas être déployée telle quelle en production.

Elle conserve le moteur, les modules, les templates, les ressources de l’éditeur et les tests statiques. Elle exclut les bases SQLite, les sessions, les sauvegardes, les médias importés, les configurations du site, les fichiers de patch temporaires et les index générés. Les répertoires de données sont conservés vides par des fichiers `.gitkeep`.

La configuration Apache `.htaccess` a été nettoyée des redirections propres au domaine d’origine, tout en conservant les règles communes de routage et d’interdiction d’accès. Les textes « Portix » affichés dans l’administration ont été remplacés par « P3 » ; les noms de classes, namespaces, sélecteurs CSS et identifiants nécessaires au fonctionnement ont été préservés.

## Prérequis indiqués par l’installation

PHP 8.2 ou plus récent ; PDO SQLite, extensions utilisées par P3 (notamment `mbstring`, `dom`, `gd` avec WebP, `fileinfo`, `zip`) et réécriture des URL sur le serveur HTTP. Les prérequis précis sont contrôlés par `src/Installation/EnvironmentPreflight.php`.

## Points à valider avant une publication publique

- Test d’installation à partir de zéro et vérification des permissions du serveur.
- Vérification des fichiers de configuration et des dépendances sous licence tierce.
- Audit des thèmes propres à Univers Guitare et des droits sur les illustrations / polices / ressources incluses.
- Nettoyage des messages de versions/patchs encore présents dans les contenus initiaux de `src/Installation/default-content.json` ; le travail doit préserver la documentation fonctionnelle souhaitée.
- Vérification de l’installateur, dont le nom du fichier SQLite est actuellement fixé dans le code. Le choix d’un nom personnalisé ou généré aléatoirement reste à développer dans un chantier séparé.
- Confirmation des tests unitaires et fonctionnels avec l’environnement de développement adapté.

## Licence

P3 est destiné à être distribué sous les termes de la GNU General Public License, version 3 ou toute version ultérieure (`GPL-3.0-or-later`). Le fichier `LICENSE` contient le texte de la GPL v3 ; les licences et notices spécifiques aux composants tiers doivent être conservées.
