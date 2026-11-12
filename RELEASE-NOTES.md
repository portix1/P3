# P3 0.9.0 — version stable

**Publication : 12 novembre 2026.** Cette version stabilise le moteur P3 après la série 0.8.4-rc4 et les correctifs validés en production sur Univers Guitare. Elle remplace la 0.8.1 comme dernière version stable.

## Changements depuis P3 0.8.1

- **Back-office :** séparation explicite des espaces Rédaction (contenus), Administration (gestion courante) et Configuration (structure et interventions rares). Gestion des modules, thèmes, diagnostics et paramètres techniques organisée dans Configuration ; contrôle des droits des rédacteurs.
- **Moteur et modules :** accueil configurable (Accueil 1.1.0), Articles 1.4.8, Menu principal 1.0.1, Recherche 1.0.5, Statistiques 2.0.3 et optimisations des lectures de données. L'état des modules installés reste distinct des fichiers distribués.
- **Sécurité HTTP :** règles empêchant l'accès direct aux répertoires internes, aux outils et aux tests, et routage des URL PHP non publiques. Les adaptations de l'hébergement LWS doivent être conservées lors de la mise à jour.
- **Mises à jour d'extensions :** contrôle SHA-256 des ZIP référencés dans le catalogue, refus des rétrogradations ou changements d'identité de module, signalement des imports manuels non vérifiés. Le catalogue officiel et ses archives sont référencés par un commit Git figé dont l'empreinte de catalogue est intégrée dans le moteur.
- **Lecture et performance mobile :** variantes WebP 400/800/1200 px et `srcset/sizes` pour les illustrations des articles, chargement différé et dimensions intrinsèques, figures 25/33/50 % lisibles sur téléphone, lignes de lecture plafonnées sur tablette, en-tête mobile compact, recherche repliable et polices locales préchargées. Les personnalisations du thème ne sont pas écrasées par les correctifs ciblés.

## Installation et migration

La distribution complète contient le moteur, les modules et les thèmes de référence. Elle **n'inclut pas** les bases SQLite en exploitation, sessions, sauvegardes, fichiers confidentiels, médias importés et données générées.

- **Installation neuve :** décompresser `P3-0.9.0.zip`, transférer le contenu du dossier `P3` sur le serveur, puis ouvrir `install.php`. Prérequis : PHP 8.2 ou ultérieur, PDO SQLite et extensions nécessaires, dont GD/WebP.
- **Installation existante :** faire une sauvegarde de la base SQLite et des fichiers personnalisés ; comparer les fichiers avec la distribution, puis essayer la mise à jour sur une copie. **Ne pas synchroniser avec suppression** : cela pourrait effacer des médias ou des fichiers propres au site.
- **Univers Guitare :** les correctifs successifs de P3 0.8.4-rc4 ayant déjà été transférés et validés, la mise à jour du numéro affiché doit rester distincte d'une réinstallation complète. Ne pas remplacer le `.htaccess` adapté à LWS, le thème actif personnalisé, les données ou le module Accueil configuré.

La publication sur GitHub ne déploie rien automatiquement sur un site existant. Le fichier `P3-0.9.0.sha256` permet de contrôler l'intégrité de l'archive complète.

## Vérifications

La validation de publication est réalisée sur PHP 8.2, 8.3, 8.4 et 8.5, avec tests des modules, routage, sécurité, installation, médias, moteur de templates, et tests Chromium de l'accueil et du rendu mobile à plusieurs largeurs.

## Limites connues et suites

- Le catalogue est **figé et vérifié par SHA-256**, mais n'emploie pas de signature externe : l'authenticité repose sur les références embarquées dans le code installé. Une nouvelle publication d'extensions exige de réviser ces références dans une version ultérieure du moteur.
- Les variantes WebP des articles sont générées progressivement. Une génération anticipée dès l'ajout ou le remplacement d'un média reste au programme.
- Les scores LCP et CLS publiés pour Univers Guitare proviennent d'essais de laboratoire, non de statistiques terrain. Une campagne répétée reste à réaliser.
- La version stable n'implique pas que tous les éléments de la feuille de route éditoriale, SEO ou ergonomique soient terminés.

**Historique de la série 0.8 :** `docs/releases/notes-0.8-series.md`.
