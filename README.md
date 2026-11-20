# P3 — CMS PHP modulaire

**Dernière version publiée :** `0.9.9`\
**Version stable préparée :** `1.0.0` (signature et publication en attente)\
**Dernière version publiée avant 1.0.0 :** `0.9.9`\
**Dernière candidate de la série 0.8 :** `0.8.4-rc4`  
**Licence :** `GPL-3.0-or-later`

P3 est un CMS PHP modulaire avec des espaces distincts pour la Rédaction, l'Administration courante et la Configuration du site.

## Distribution de la version 1.0.0

Cette archive complète 1.0.0 regroupe les correctifs validés après 0.9.9. Elle contient le moteur, les modules et les thèmes, avec Articles 1.4.12, Accueil 1.1.2 et Téléchargements 1.0.1. La distribution ne doit être annoncée comme publiée qu'après vérification de son archive et de sa signature.

Pour une installation neuve, extraire le dossier P3 dans le répertoire Web local et ouvrir install.php. Le ZIP inclut les fichiers cachés de protection et de routage, dont le .htaccess corrigé. Pour tester une installation existante, travailler sur une copie sauvegardée et suivre le guide de maintenance, en conservant configuration, base, médias et personnalisations.

Les correctifs regroupés proviennent de la consolidation 0.9.10 (PR #52 à #57), des améliorations du back-office (PR #58) et de la validation 0.9.11 (PR #59). Le module Articles 1.4.12 est fourni avec le moteur ; le catalogue autonome figé continue de proposer Articles 1.4.9, jusqu'à une publication séparée du catalogue. La réécriture complète de Téléchargements, le MCP et les autres pistes futures restent dans la roadmap.

## Documentation

- [Guide de l’administrateur](docs/guide-administrateur.md)
- [Guide du rédacteur](docs/guide-redacteur.md)
- [Installation et maintenance](docs/installation-et-maintenance.md)
- [Guide du bêta-testeur](docs/guide-beta-testeur.md)

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

Lors d’une restauration, les fichiers `.htaccess` et `web.config` déjà présents à la racine sont conservés. Si l’un d’eux manque, P3 le récupère depuis le point de restauration lorsqu’il y figure.

Si une mise à jour ou une restauration laisse le site en maintenance, suivez la [procédure de reprise](docs/installation-et-maintenance.md#reprendre-un-site-bloqué-en-maintenance).

## Licence

P3 est distribué sous les termes de la GNU General Public License, version 3 ou ultérieure (`GPL-3.0-or-later`). Les licences et notices tierces doivent être conservées.
