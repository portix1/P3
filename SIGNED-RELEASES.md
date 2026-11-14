# Distribution signée du moteur P3

La CI vérifie le code et construit les artefacts. Elle ne publie plus automatiquement une release et ne possède aucune clé privée. Le responsable de publication signe hors GitHub, sur un poste de confiance, après vérification indépendante du commit et de l'archive. Le checksum seul ne prouve pas la provenance.

## Initialisation (une seule fois)

Sur le poste de signature, hors de tout dépôt et dans un répertoire privé :

    umask 077
    openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:3072 -aes-256-cbc -out p3-release-private.pem
    openssl pkey -in p3-release-private.pem -pubout -out core-release-public.pem
    openssl pkey -pubin -in core-release-public.pem -outform DER | openssl dgst -sha256

La clé privée chiffrée reste hors GitHub, hors hébergement web, hors de toute conversation IA et hors des archives. En conserver une sauvegarde chiffrée sur un support indépendant. Communiquer l'empreinte publique aux administrateurs par un canal indépendant du dépôt. Ne pas télécharger la clé de confiance à partir de la release qu'elle doit authentifier.

Sur chaque installation, déposer la clé publique vérifiée dans var/sys/core-release-public.pem avant d'utiliser les mises à jour intégrées. Le fichier est conservé par les mises à jour et les restaurations de la nouvelle version ; ne pas le remplacer pour contourner un échec de signature. Verifier que var/sys et var/private ne sont pas accessibles par HTTP. Si la clé manque ou si PHP OpenSSL n'est pas disponible, la mise à jour intégrée est refusée ; une mise à jour manuelle reste possible après vérification indépendante.

## Préparer et publier une version stable

1. Fixer VERSION à X.Y.Z et actualiser la documentation. Valider le commit exact et les tests. Récupérer l'artefact CI, dont SOURCE-COMMIT identifie le code construit. Vérifier le commit attendu et reconstruire ou contrôler les fichiers de l'archive à partir de cette source de confiance ; ne pas signer aveuglément un fichier issu de GitHub.
2. Vérifier que le tag vX.Y.Z et la release n'existent pas déjà. Ne jamais remplacer un artefact publié. Dans le répertoire contenant les fichiers contrôlés, lancer (remplacer X.Y.Z par la version réelle) :

       sha256sum -c P3-X.Y.Z.sha256
       openssl dgst -sha256 -sign /chemin/prive/p3-release-private.pem -out P3-X.Y.Z.sig P3-X.Y.Z.sha256
       openssl dgst -sha256 -verify /chemin/verifie/core-release-public.pem -signature P3-X.Y.Z.sig P3-X.Y.Z.sha256

   Le fichier .sha256 doit contenir exactement 64 caractères hexadécimaux minuscules, deux espaces, P3-X.Y.Z.zip et un saut de ligne LF. La signature est binaire, RSA/SHA-256.
3. Après autorisation explicite, créer le tag/release sur le commit contrôlé et y joindre les trois fichiers ZIP, SHA-256 et SIG, avec les notes correspondant à cette version. Contrôler les artefacts téléchargés après publication.

## Rotation et limites

La rotation se fait hors du canal de mise à jour : communiquer la nouvelle empreinte par un canal indépendant et remplacer la clé locale après vérification. En cas de perte ou de compromission, suspendre la publication et les mises à jour jusqu'au remplacement vérifié des clés. Aucune rotation automatique ni infrastructure de révocation n'est actuellement fournie. La version précédente ne vérifie pas les signatures : le premier passage vers cette version doit être effectué manuellement à partir d'une archive vérifiée. La procédure ne protège pas un serveur déjà compromis ni un poste de signature compromis.

## Vérification des protections HTTP

Le test tools/test_private_http.sh utilise un serveur Apache isolé et des fichiers fictifs : un fichier public doit répondre 200 et un faux snapshot privé 403. Il ne prouve pas la configuration de l'hébergeur de production. Sur nginx ou un autre serveur ignorant .htaccess, configurer un refus d'accès à /var/private/ et /var/sys/ avant usage ; ne jamais utiliser un snapshot réel comme sonde.
