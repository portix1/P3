# P3 0.9.1 — catalogue d'extensions cohérent et figé

**Version stable corrective du 13 novembre 2026.** Cette distribution remplace P3 0.9.0. Le moteur, les modules de référence et les thèmes sont inclus.

## Correctifs

- **Articles 1.4.8 officiellement téléchargeable** : le canal de mises à jour des extensions propose désormais la version 1.4.8 au lieu de 1.4.6, avec son ZIP autonome contrôlé par SHA-256. Le module embarqué et l'archive officielle portent la même version.
- **Compatibilité obligatoire** : Articles 1.4.8 exige au minimum **P3 0.9.0**, car il utilise le moteur des images responsives. L'import du ZIP est refusé sur les noyaux plus anciens, avant remplacement des fichiers.
- **Chaîne de confiance actualisée** : catalogue et ZIP publiés depuis le commit Git figé `c8b0db7f35da9a02a51ebe1c45c8319469beabda`. Le moteur vérifie l'empreinte SHA-256 exacte du catalogue (`6f91dd6af62fe91d62068eb65a7c99bce6d60df309073b9d41041fc5fc50d4e2`) avant d'utiliser ses annonces. Les URL de téléchargement visent le même commit.
- **Reproductibilité** : le constructeur de ZIP de modules emploie des horodatages et permissions normalisés ; la CI reconstruit deux fois Articles 1.4.8, compare les octets, les sources et les empreintes.
- **Documentation corrigée** : le catalogue distingue les extensions autonomes (Articles 1.4.8 et thème Six Cordes UX Clair 0.1.7) des modules inclus dans l'archive complète (dont Accueil 1.1.0, Menu 1.0.1, Recherche 1.0.5 et Statistiques 2.0.3).

Aucune modification éditoriale ou de mise en page n'est introduite dans ce correctif. Les correctifs mobiles validés dans 0.9.0 restent présents.

## Important pour les installations existantes

Le catalogue est intentionnellement **figé** dans le moteur. Une installation en P3 0.9.0 continue d'utiliser l'ancien catalogue (Articles 1.4.6) tant qu'elle n'a pas reçu les fichiers moteur de 0.9.1. Les anciennes installations ne reçoivent pas silencieusement un nouveau catalogue.

**Sur Univers Guitare :** Articles 1.4.8 est déjà installé et validé. Il est inutile de réinstaller le module ou de remplacer le thème. Pour afficher la version 0.9.1 et accéder au nouveau catalogue, mettre à jour uniquement les fichiers nécessaires, après sauvegarde, et conserver `.htaccess` adapté à LWS, la configuration, la base SQLite, les médias et le thème personnalisé.

Les mises à jour du noyau depuis le tableau de bord restent **à développer** ; cette version ne les automatise pas.

## Installation neuve

Décompresser `P3-0.9.1.zip`, transférer le contenu du dossier `P3` à la racine du serveur et ouvrir `install.php`. PHP 8.2 ou plus récent et PDO SQLite sont requis, ainsi que les extensions précisées par le contrôle d'environnement. Le fichier `P3-0.9.1.sha256` permet de vérifier le téléchargement.

## Tests et sécurité

La validation automatique couvre PHP 8.2, 8.3, 8.4, 8.5, les installations et mises à jour des modules, les archives des extensions, la chaîne de confiance, les accès, les tests HTTP et Chromium.

La chaîne de confiance **n'emploie pas encore de signature externe**. Une clé publique intégrée au noyau et un catalogue signé devront être étudiés pour autoriser de nouvelles publications d'extensions sans mise à jour du noyau.

Historique des corrections de la version précédente : `docs/releases/notes-0.9.0.md`.
