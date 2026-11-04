# P3 — CMS PHP modulaire

**Version de travail du dépôt :** `0.8.3-rc4` (version de test)  
**Version stable publiée :** `0.8.1`  
**Archive source historique :** `0.8.01-beta1-preview5`  
**Licence du projet :** `GPL-3.0-or-later`

## État de cette copie

Cette arborescence contient les sources du moteur, des modules et des thèmes. Les distributions téléchargeables sont publiées sur [GitHub Releases](https://github.com/portix1/P3/releases). La 0.8.3-rc4 est une version de test ; la 0.8.1 reste disponible comme version stable. Les changements et les consignes d’installation figurent dans RELEASE-NOTES.md.

Elle conserve le moteur, les modules, les templates, les ressources de l’éditeur et les tests automatisés. Elle exclut les bases SQLite, les sessions, les sauvegardes, les médias importés, les configurations du site, les fichiers de patch temporaires et les index générés. Les répertoires de données sont conservés vides par des fichiers `.gitkeep`.

La configuration Apache `.htaccess` a été nettoyée des redirections propres au domaine d’origine, tout en conservant les règles communes de routage et d’interdiction d’accès. Les textes « Portix » affichés dans l’administration ont été remplacés par « P3 » ; les noms de classes, namespaces, sélecteurs CSS et identifiants nécessaires au fonctionnement ont été préservés.

## Prérequis indiqués par l’installation

PHP 8.2 ou plus récent ; PDO SQLite, extensions utilisées par P3 (notamment `mbstring`, `dom`, `gd` avec WebP, `fileinfo`, `zip`) et réécriture des URL sur le serveur HTTP. Les prérequis précis sont contrôlés par `src/Installation/EnvironmentPreflight.php`.

## Distribution et vérifications

La validation automatisée couvre PHP 8.2, 8.3, 8.4 et 8.5. Une publication exige la réussite de ces contrôles. La génération de l’archive exclut les données d’exécution et les correctifs séparés ; elle conserve les schémas des modules et les protections des dossiers.

Pour préparer une distribution depuis un checkout Git, exécuter `python3 tools/build_distribution.py`. Le ZIP et son empreinte SHA-256 sont écrits dans `dist/`. Les commits de publication portent le préfixe `Release P3 ` ; le workflow les publie après validation. Les autres commits ne déclenchent aucune publication.

Essayer une version de test sur une copie avant de mettre à jour un site de production. Une publication GitHub ne déploie aucun site. La configuration, les bases, les médias et les adaptations du serveur doivent être conservés lors d’une mise à jour.

## Licence

P3 est destiné à être distribué sous les termes de la GNU General Public License, version 3 ou toute version ultérieure (`GPL-3.0-or-later`). Le fichier `LICENSE` contient le texte de la GPL v3 ; les licences et notices spécifiques aux composants tiers doivent être conservées.

