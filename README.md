# P3 — CMS PHP modulaire

**Version stable actuelle :** `0.9.0`  
**Version stable précédente :** `0.8.1`  
**Dernière version candidate de la série 0.8 :** `0.8.4-rc4`  
**Archive source historique :** `0.8.01-beta1-preview5`  
**Licence :** `GPL-3.0-or-later`

## État du dépôt

Ce dépôt contient le moteur P3, les modules, les thèmes de référence, les scripts de validation et les outils de distribution. La version `0.9.0` stabilise les corrections de sécurité, de gestion des modules et d'ergonomie mobile intégrées après `0.8.4-rc4`.

Les distributions complètes sont publiées dans les [versions GitHub](https://github.com/portix1/P3/releases). Les changements et la procédure de mise à jour figurent dans [RELEASE-NOTES.md](RELEASE-NOTES.md).

La distribution exclut les bases SQLite d'exploitation, les sessions, sauvegardes, médias importés, configurations privées et fichiers générés. Les protections des répertoires sont conservées, mais les `.htaccess` propres à l'hébergeur et les thèmes personnalisés doivent être comparés et préservés lors de tout transfert.

## Prérequis

PHP 8.2 ou plus récent ; PDO SQLite et les extensions utilisées par P3, notamment `mbstring`, `dom`, `gd` avec WebP, `fileinfo` et `zip`. Les prérequis précis sont contrôlés par `src/Installation/EnvironmentPreflight.php`.

## Distribution et tests

La validation automatisée couvre PHP 8.2, 8.3, 8.4 et 8.5, les tests de modules et de sécurité, les tests HTTP ainsi que des contrôles Chromium de l'accueil et du rendu mobile.

Pour construire une distribution à partir des sources :

```sh
python3 tools/test_distribution.py
python3 tools/build_distribution.py
```

L'archive complète et son empreinte SHA-256 sont écrites dans `dist/`. Un commit de publication commence par `Release P3 ` ; GitHub Actions ne publie la version qu'après réussite des contrôles.

**Une publication GitHub n'installe ni ne met à jour automatiquement un site.** Pour une installation existante, sauvegarder les fichiers et la base, essayer la mise à jour sur une copie et conserver les adaptations locales. Ne pas relancer l'installateur et ne jamais supprimer les fichiers absents de l'archive lors d'une synchronisation FTP.

## Licence

P3 est distribué sous les termes de la GNU General Public License, version 3 ou ultérieure (`GPL-3.0-or-later`). Conserver les notices de licence propres aux composants tiers.
