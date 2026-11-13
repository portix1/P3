# Catalogue officiel des extensions P3

Ce répertoire contient les archives autonomes disponibles par le canal vérifié de la version **P3 0.9.2** (préparée, non publiée). Il ne constitue pas une liste exhaustive des modules inclus dans la distribution complète du moteur.

| Extension autonome | Version | Compatibilité | Téléchargement |
| --- | --- | --- | --- |
| Articles | 1.4.9 | P3 0.9.2 ou ultérieur, PHP 8.2+ | [ZIP](P3-articles-1.4.9.zip?raw=true) |
| Six Cordes UX Clair (thème de référence) | 0.1.7 | P3 0.8.1 ou ultérieur, PHP 8.2+ | [ZIP](P3-six-cordes-ux-clair-0.1.7.zip?raw=true) |

Le module **Articles 1.4.9** appelle les fonctions d'images responsives du moteur P3 0.9.2. Sa mise à jour depuis un moteur antérieur est refusée par le manifeste `MinimumP3`.

**Choix explicite pour cette publication :** Accueil 1.1.0, Menu principal 1.0.1, Recherche 1.0.5, Statistiques 2.0.3 et les autres modules de référence restent distribués avec l'archive complète de P3. Leur packaging autonome, leur compatibilité et leur canal de mise à jour devront être testés avant de les ajouter au catalogue. Ils ne sont donc pas annoncés comme téléchargeables séparément par le canal officiel.

## Installer une mise à jour

L'administrateur peut télécharger l'archive référencée dans Configuration → Gestion des modules ou Gestion des thèmes, puis importer le ZIP dans l'interface prévue. **L'installation du moteur P3 n'est pas encore automatisée depuis le tableau de bord.**

Le [catalogue](catalogue.json) contient les versions, les prérequis, les noms de fichiers et les empreintes SHA-256. Les ZIP sont servis depuis un commit Git **figé**, dont l'identifiant complet et l'empreinte du catalogue sont inscrits dans le moteur. Voir [TRUST.md](TRUST.md).

Le catalogue de P3 0.9.1 distribue toujours Articles 1.4.8. Articles 1.4.9 ne sera disponible par le canal 0.9.2 qu'après la publication autorisée du moteur correspondant.

Ne jamais écraser la base SQLite, les médias, les thèmes personnalisés ou le `.htaccess` spécifique au serveur lors d'une mise à jour du moteur.

[Versions complètes de P3](https://github.com/portix1/P3/releases)
