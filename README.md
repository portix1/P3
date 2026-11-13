# P3 — CMS PHP modulaire

**Version stable actuelle :** `0.9.1`  
**Version 0.9.2 préparée :** branche `release/0.9.2` (non fusionnée, non publiée)  
**Version stable précédente :** `0.9.0`  
**Dernière candidate de la série 0.8 :** `0.8.4-rc4`  
**Licence :** `GPL-3.0-or-later`

P3 est un CMS PHP modulaire avec des espaces distincts pour la Rédaction, l'Administration courante et la Configuration du site.

## Distribution

Les versions complètes sont publiées dans [GitHub Releases](https://github.com/portix1/P3/releases). Voir [RELEASE-NOTES.md](RELEASE-NOTES.md) pour les modifications prévues de 0.9.2, la procédure de mise à jour et les limites connues.

L'archive complète inclut le moteur, les modules et les thèmes de référence. Elle exclut les bases SQLite d'exploitation, les sessions, sauvegardes, médias importés, configurations privées et données générées. Les archives de modules et le catalogue d'extensions autonomes sont gérés séparément dans [distribution/](distribution/README.md).

## Prérequis

PHP 8.2 ou ultérieur ; PDO SQLite et les extensions requises par P3, notamment `mbstring`, `dom`, `gd` avec WebP, `fileinfo` et `zip`. La vérification précise est réalisée par `src/Installation/EnvironmentPreflight.php`.

## Publication et tests

```sh
php tools/validate_release.php
python3 tools/test_distribution.py
python3 tools/test_catalogue_distribution.py
python3 tools/build_distribution.py
```

La CI exécute les contrôles sous PHP 8.2 à 8.5 et les tests HTTP/Chromium. Une publication stable exige la réussite de ces contrôles. Un commit dont le message commence par `Release P3 ` déclenche la construction de `P3-<version>.zip`, son SHA-256 et la publication officielle.

**GitHub ne déploie pas la production automatiquement.** Pour mettre à jour un site existant, réaliser une sauvegarde, vérifier les différences, conserver le `.htaccess` propre à l'hébergeur, le thème personnalisé, la configuration, les médias et la base SQLite. Ne pas relancer l'installateur et ne pas synchroniser des fichiers FTP avec suppression des fichiers absents du ZIP.

## Licence

P3 est distribué sous les termes de la GNU General Public License, version 3 ou ultérieure (`GPL-3.0-or-later`). Les licences et notices tierces doivent être conservées.
