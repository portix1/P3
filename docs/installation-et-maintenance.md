# Installer et maintenir P3

## Installer sur un site neuf

1. Sur l’hébergement, sélectionnez PHP 8.2 ou plus récent. Le programme d’installation contrôle PDO SQLite, session, DOM, GD avec WebP, iconv, Fileinfo, mbstring et ZIP. OpenSSL sert à vérifier les mises à jour signées.
2. Téléchargez le ZIP de la [release officielle](https://github.com/portix1/P3/releases), son fichier SHA-256 et sa signature. Vérifiez-les selon la [procédure de signature](../SIGNED-RELEASES.md#vérifier-une-distribution-téléchargée).
3. Décompressez le ZIP et transférez le contenu du dossier `P3/` à la racine Web prévue, fichiers cachés compris. Sous Apache, activez la réécriture et l’application des fichiers `.htaccess`. Sous IIS ou nginx, configurez les règles équivalentes avant d’ouvrir le site au public.
4. Ouvrez `install.php` dans le navigateur. Corrigez les erreurs de précontrôle, puis choisissez le nom du site, le pseudonyme et l’e-mail de l’administrateur, ainsi qu’un mot de passe d’au moins 12 caractères. Le nom de la base est facultatif. P3 peut le générer et crée le fichier SQLite.
5. Ouvrez `login.html`, connectez-vous et vérifiez le tableau de bord. Dans **Configuration**, renseignez l’adresse publique du site et les réglages dont vous avez besoin.

Les dossiers `database/`, `sessions/`, `tmp/`, `var/sys/` et `var/private/` doivent être inaccessibles directement par HTTP. Le dossier `var/uploads/images/` doit autoriser les images publiques prévues par P3. Les [règles et leurs vérifications](../SIGNED-RELEASES.md#vérification-des-protections-http) dépendent du serveur Web.

## Mettre à jour un site existant

Utilisez **Configuration → Mises à jour**. Avant l’opération, sauvegardez les fichiers et la base. Conservez une copie hors de l’hébergement. P3 vérifie l’archive signée avant de l’appliquer et crée un point de restauration. Après l’opération, vérifiez le numéro de version, l’accueil, un article et la connexion au back-office.

Pour une intervention manuelle, conservez les données SQLite, les médias, les thèmes personnalisés, la configuration et les règles HTTP locales. L’installateur est réservé à un site neuf. Évitez toute synchronisation FTP qui supprime les fichiers absents de l’archive.

## Restaurer et diagnostiquer

Le [guide administrateur](guide-administrateur.md#restaurer-après-une-mise-à-jour) décrit la restauration depuis le back-office. En cas de blocage en maintenance, suivez la procédure de secours ci-dessous.

Dans **Configuration → Diagnostics**, commencez par **Tests rapides**. Utilisez **Suite de tests complète** si vous avez besoin du rapport détaillé. Avant de transmettre le rapport, relisez-le et retirez les informations privées.


## Reprendre un site bloqué en maintenance

1. Depuis le panneau d’hébergement ou par FTP, conservez une copie de l’état actuel et récupérez le point voulu dans `var/private/update-backups/`. Un point complet contient `manifest.json`, `application/` et `database/portix2.sqlite`.
2. Repérez le nom réel de la base dans `var/sys/config.json`, champ `database_file`. Dans le point de restauration, la copie SQLite s’appelle toujours `portix2.sqlite`. Lors d’une reprise manuelle, elle doit retrouver le nom attendu par la configuration restaurée.
3. Rétablissez les fichiers applicatifs à partir de `application/`. Rétablissez ensemble la base, la configuration et les médias du même point si les données doivent être restaurées. Conservez les règles HTTP locales de la racine et la clé publique de confiance déjà configurée.
4. Vérifiez la cohérence des fichiers et de la base avant de retirer `var/sys/maintenance.lock`. Une fois le site accessible, contrôlez la version, un article avec image et l’accès au back-office.
