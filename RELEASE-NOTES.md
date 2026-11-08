# P3 0.8.4-rc4

Mise à niveau depuis la production preview5 : reprise des correctifs canoniques déjà installés, contrat générique des modules et Accueil 1.0.2 compatible avec cette candidate. Le paquet de mise à jour de production conserve le thème actif, les données et les réglages ; il est distinct de la distribution neuve.

# P3 0.8.4 — socle générique pour les modules

Version préparée pour validation, sans déploiement automatique.

- Les modules peuvent déclarer leurs écrans dans les trois espaces du back-office, avec rôle, icône et traduction.
- Ils peuvent déclarer des actions publiques POST sans création d’une session anonyme ; les sessions déjà présentes restent chargées.
- Le gestionnaire vérifie la version minimale avant remplacement des fichiers et conserve l’état actif/inactif lors d’une réinstallation.
- L’accueil est distribué séparément en ZIP (Accueil 1.0.1), avec ses styles et mesures propres ; le socle ne contient aucune intégration spécifique à ce module.
- Aucun thème, média, contenu ou choix de page d’accueil n’est modifié par l’installation d’un ZIP de module.

## Notes historiques

# P3 0.8.3-rc4

Version de test complète du moteur, des modules et des thèmes. La version stable 0.8.1 reste disponible séparément.

## Back-office

Le tableau de bord présente l’état du site et dirige vers trois espaces distincts : Rédaction en bleu, Administration en orange et Configuration en violet. Le menu latéral suit l’espace ouvert. Les thèmes et les paramètres des modules se trouvent dans Configuration. Actualités reste accessible lorsqu’il est activé.

## Points de restauration

- Les nouveaux points enregistrent la version du code sauvegardé, même si une mise à jour manuelle a laissé un ancien fichier de suivi.
- Les points existants affichent la version de leur propre fichier VERSION, sans modifier leurs archives. Leur manifeste reste utilisé si ce fichier est absent.
- Chaque point peut être supprimé individuellement dans Administration → Protection des contenus → Rollback, après confirmation explicite.
- La suppression conserve le site actuel et les autres points. Aucune purge automatique n’est activée.
- Création, restauration et suppression partagent un verrou. Une restauration remet le suivi de version en accord avec le code restauré.

Le refus LF/CRLF du correctif séparé de back-office est également corrigé dans les sources du dépôt. Cette distribution complète n’utilise pas ce correctif.

## Installation et mise à jour

Télécharger **P3-0.8.3-rc4.zip**, puis extraire le dossier P3. L’archive contient les fichiers applicatifs et les protections des dossiers de données, sans base SQLite, configuration privée, sauvegardes ni médias importés.

Pour une installation neuve, transférer le contenu du dossier P3 à la racine du site puis ouvrir install.php. Pour mettre à jour une installation existante, sauvegarder le site et sa base puis copier les fichiers applicatifs à sa racine. Conserver la base, la configuration et les données déjà présentes ; ne pas utiliser une synchronisation qui efface les fichiers absents de l’archive et ne pas relancer l’installation.

La publication sur GitHub ne modifie aucun site existant. Cette version doit être essayée sur une copie avant son installation en production. Le fichier SHA-256 permet de vérifier le téléchargement.

Lors d’une mise à jour, conserver les adaptations propres au serveur et au domaine dans .htaccess et web.config ; comparer ces fichiers avant de les remplacer.

## Vérifications

La publication est conditionnée à la validation automatisée sous PHP 8.2, 8.3, 8.4 et 8.5. Les tests des points de restauration couvrent la version sauvegardée, les anciennes métadonnées, une restauration complète avec nom SQLite personnalisé, le point de secours, la suppression ciblée, les droits administrateur, POST, CSRF, la confirmation et les modèles français et anglais.
