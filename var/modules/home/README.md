# Accueil 1.0.5

Module générique de composition de la page d’accueil, sans catégorie ni domaine imposé.

## Mise en route

Ce ZIP est installé par **Administration → Gestion des modules → Installer un module**, sur **P3 0.8.4-rc4 ou plus récent**. La 0.8.3-rc4 ne possède pas les déclarations génériques nécessaires aux trois espaces et aux actions publiques sans session : mettre d’abord le moteur à niveau avec le socle compatible, une seule fois. Le module ne copie ni fichier moteur, ni traduction globale, ni thème. Il est livré désactivé ; vérifier les éventuelles URL existantes `home` et `home-*`, puis l’activer. Une réinstallation conserve son état actif/inactif et les données existantes.

Dans **Rédaction → Accueil**, ajouter les zones et leur position. Dans **Configuration → Réglages de l’accueil**, régler le tirage et activer éventuellement sa mesure. Choisir ensuite `home.html` comme page d’accueil dans **Configuration → Identité**. La racine et ses métadonnées restent gérées par P3. L’installation du ZIP ne change pas cette cible.

Les rédacteurs et administrateurs composent les zones ; seuls les administrateurs changent les réglages et consultent **Administration → Résultats de l’accueil**. Les écritures exigent POST et un jeton CSRF ; la suppression exige aussi une confirmation.

## Zones et images

Jusqu’à 30 zones ordonnées, de 1 à 6 contenus par zone (au maximum 60 propositions par page). Articles d’une catégorie et, au choix, de ses descendants visibles ; actualités récentes ; produits affiliés ; texte libre sans HTML. Les modules sources doivent être activés. Les contenus non publiés ou datés dans le futur sont exclus. Les zones vides sont masquées. Un article ou produit n’est proposé qu’une fois sur la page.

Compositions stables : article mis en avant, catégorie illustrée, cartes illustrées ou sélection textuelle. Le nombre de catégories n’est pas fixé à trois. Aucun titre général n’est affiché au-dessus des zones ; les titres propres à chaque zone restent disponibles. Les zones se répartissent sur deux colonnes dès 38 rem de largeur disponible, puis trois dès 60 rem. Le calcul utilise la largeur du contenu, y compris dans un thème avec barre latérale. Sur mobile (640 pixels ou moins), une seule colonne est imposée. Les zones de texte libre occupent toute la largeur pour servir notamment d’introduction. Les compositions internes restent variées et l’ordre des zones est conservé. La disposition reprend les couleurs et les polices du thème. Les styles préfixés `p3-home` sont fournis dans `assets/public.css` et insérés par le module uniquement sur sa page publique, comme pour l’affiliation. Aucune compilation ni modification des thèmes, des en-têtes ou du pied de page n’est nécessaire.

L’image principale explicite de l’article sert aussi dans l’accueil. Son absence ne provoque pas l’extraction automatique d’une image du corps. Une zone peut associer une image commune à sa catégorie ; elle est conservée dans `home_sections.image_path` et réutilisable dans d’autres zones via la médiathèque. Un ajout depuis la zone passe par la médiathèque active. Une miniature WebP de 256 × 256 pixels au maximum préserve les proportions. Elle est affichée à gauche dans une boîte haute de trois lignes de texte (`3lh`), sans déformation, avec un habillage flottant : le texte s’écoule à côté puis reprend toute la largeur en dessous. Le même format compact s’applique sur mobile, aux images de catégorie et aux images des zones de texte libre. Les images de catégorie accompagnent la première proposition de leur zone. L’original n’est jamais remplacé. Les originaux référencés dans les zones sont protégés par l’analyse générale des usages. Les miniatures sont dérivées dans `var/uploads/images/home-thumbs/`, indexées par empreinte du contenu, et régénérables ; aucune purge automatique d’images n’est faite. Aucune image distante n’est téléchargée. Seuls les chemins locaux JPEG, PNG et WebP sous `var/uploads/images/` sont acceptés.

Les images de produits existantes peuvent être affichées, mais le nouveau parcours d’ajout d’images dans le module Affiliation reste un chantier séparé. Les offres affichées exigent produit, offre et partenaire actifs ; les liens conservent `sponsored nofollow noopener` et l’ouverture dans un autre onglet, avec la mention d’affiliation configurée.

## Tirage

Valeurs initiales : 28 jours, 50 % d’exploration uniforme, renouvellement commun toutes les 60 minutes. Les vues journalières décroissent avec une demi-vie de 14 jours ; la popularité est atténuée par `log(1 + vues pondérées)`. Le tirage sans remise combine cette popularité et une probabilité uniforme. L’exploration conserve au moins 20 % de la probabilité ; sans observations, sans module Statistiques actif ou avec sa collecte désactivée, le tirage est uniforme. Les produits affiliés sont toujours tirés uniformément. Les actualités sont tirées parmi les douze publications récentes disponibles.

La sélection d’identifiants est conservée dans `analytics_home_cache`. Les contenus, dates, catégories et modules sont revérifiés à l’affichage, y compris lorsqu’une sélection existe : retirer un article ne le laisse pas visible jusqu’au prochain tirage. Le retrait peut laisser temporairement moins de propositions. Le cache HTTP du moteur reste de cinq minutes ; un cache externe peut donc retarder ce retrait jusqu’à sa prochaine validation. Les tableaux de séries ne sont pas modifiés ; la série est indiquée et son début proposé si publié.

## Mesure facultative

La mesure est désactivée initialement. Elle exige son activation dans les réglages d’accueil et le module Statistiques avec une collecte active. Personnel connecté, DNT et robots reconnus sont exclus. Un cookie renvoyé n’est pas exigé : une première page est mesurable. Aucun cookie ni identifiant navigateur persistant supplémentaire n’est créé.

Le navigateur envoie un événement de chargement, puis les cartes articles visibles à au moins 50 % pendant 800 ms et leurs clics. Il groupe les événements de visibilité et n’intercepte pas la navigation. Un clic sur le lien « Commencer la série » n’est pas attribué à l’article proposé. Sans JavaScript ou IntersectionObserver, les observations sont partielles. Les appels bloqués ou perdus ne sont pas reconstitués.

`analytics_home_metrics` agrège jour / zone / article / position. La ligne de zone 0 représente les chargements observés et ceux suivis d’au moins un clic ; ce ne sont ni des sessions ni des visiteurs uniques. Le rapport présente les cartes visibles, clics et leur ratio, pas une preuve de satisfaction. Un clic rapide peut précéder les 800 ms d’exposition et le ratio peut dépasser 100 %. Les petits effectifs ne doivent pas être interprétés comme un classement fiable.

L’envoi utilise des emplacements signés par le serveur, valables deux heures, un nonce aléatoire par page et une déduplication dans `analytics_home_events` conservée deux jours. Les compteurs sont conservés 90 jours. Le renouvellement de sélection réalise la purge. Le point d’entrée `home-events.html` accepte uniquement POST JSON, au maximum 32 Kio et 61 événements par appel. Il refuse les signatures/slots inconnus et les appels intersites identifiés. Une limite persistante de 180 appels par dix minutes et par adresse réseau pseudonymisée borne les répétitions. Ces contrôles limitent les abus ; un robot capable de charger la page peut simuler des événements. Aucune certification d’audience humaine ni conformité juridique globale n’est revendiquée.

Les statistiques historiques restent intactes. Le taux de clic n’alimente pas automatiquement le tirage : son utilisation correspond à la phase d’ajustement après observation.

## Validation et retour arrière

`php tools/smoke_test_home.php`, `node tools/test_home_browser.cjs` avec Playwright, puis `php tools/validate_release.php`. Les tests HTTP s’exécutent sur une installation isolée. Vérifier ensuite la présentation dans le thème actif, sur ordinateur et téléphone. Aucun déploiement automatique sur un site existant. Pour revenir à l’ancien accueil, rétablir sa cible dans Configuration et désactiver le module ; les tables et médias ne sont pas supprimés.

## Mise à jour depuis la première version de développement

Les zones et le fichier de réglages sont conservés. Les tables de mesure de la 1.0.0 (`home_metrics`, `home_events`, `home_cache`) sont migrées transactionnellement uniquement lorsqu’elles appartiennent au module dans le registre du schéma. Les compteurs et déduplications sont recopiés, puis les anciennes tables propres au module sont remplacées par les nouvelles ; le cache de tirage est renouvelé. Un conflit annule la migration plutôt que de perdre une mesure. Les statistiques historiques du site ne sont pas modifiées.

Licence : GPL-3.0-or-later ; voir LICENSE dans le ZIP du module.

## Accès HTTP aux miniatures

Les noms des dérivés utilisent 32 caractères hexadécimaux suivis de `.webp`, conformément à la protection Apache héritée de `var/uploads/images/.htaccess`. Les anciennes versions utilisaient `mini256-` suivi de 64 caractères et recevaient une réponse 403. Après mise à jour, recharger l’accueil génère automatiquement les nouvelles copies autorisées ; aucun changement de `.htaccess`, aucune suppression des originaux ou des anciennes copies n’est nécessaire.
