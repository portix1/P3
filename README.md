# P3 — CMS PHP modulaire

**Dernière version stable publiée :** `0.9.3`  
**Version préparée pour signature :** `0.9.4` (non publiée)\
**Version stable précédente :** `0.9.2`  
**Dernière candidate de la série 0.8 :** `0.8.4-rc4`  
**Licence :** `GPL-3.0-or-later`

P3 est un CMS PHP modulaire avec des espaces distincts pour la Rédaction, l'Administration courante et la Configuration du site.

## Distribution

Les versions complètes sont publiées dans [GitHub Releases](https://github.com/portix1/P3/releases). Voir [RELEASE-NOTES.md](RELEASE-NOTES.md) pour les modifications de la branche de consolidation, la procédure de mise à jour et les limites connues.

L'archive complète inclut le moteur, les modules et les thèmes de référence. Elle exclut les bases SQLite d'exploitation, les sessions, sauvegardes, médias importés, configurations privées et données générées. Les archives de modules et le catalogue d'extensions autonomes sont gérés séparément dans [distribution/](distribution/README.md).

## Prérequis

PHP 8.2 ou ultérieur ; PDO SQLite et les extensions requises par P3, notamment `mbstring`, `dom`, `gd` avec WebP, `fileinfo` et `zip`. Les mises à jour signées nécessitent également PHP OpenSSL et une clé publique de confiance. Depuis 0.9.4, la clé officielle est fournie avec le moteur ; une clé locale déjà configurée reste prioritaire. Vérifier l’empreinte de la clé et la provenance du premier paquet par un canal indépendant. La vérification précise est réalisée par `src/Installation/EnvironmentPreflight.php`.

## Publication et tests

```sh
php tools/validate_release.php
python3 tools/test_distribution.py
python3 tools/test_catalogue_distribution.py
python3 tools/build_distribution.py
```

La CI exécute les contrôles sous PHP 8.2 à 8.5 et les tests HTTP/Chromium. Une publication stable exige la réussite de ces contrôles. La CI construit une archive et son SHA-256 après validation, sans publication automatique. La publication stable exige une signature hors dépôt et une autorisation explicite : voir [SIGNED-RELEASES.md](SIGNED-RELEASES.md).

**GitHub ne déploie pas la production automatiquement.** Pour mettre à jour un site existant, réaliser une sauvegarde, vérifier les différences, conserver le `.htaccess` propre à l'hébergeur, le thème personnalisé, la configuration, les médias et la base SQLite. Ne pas relancer l'installateur et ne pas synchroniser des fichiers FTP avec suppression des fichiers absents du ZIP.

Les sessions sont isolées par installation. Après la mise à jour vers 0.9.4, reconnectez-vous au back-office et rouvrez les formulaires ou aperçus restés ouverts.

## Licence

P3 est distribué sous les termes de la GNU General Public License, version 3 ou ultérieure (`GPL-3.0-or-later`). Les licences et notices tierces doivent être conservées.
