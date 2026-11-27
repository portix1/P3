# Statistiques P3 2.1

Le tableau de bord affiche les pages vues, les visiteurs journaliers estimés,
les pages par visiteur journalier estimé, les articles consultés, les pages
d’entrée et les domaines référents de la première visite de chaque jour UTC.
Le graphe « Évolution quotidienne » juxtapose les pages vues et les
visiteurs journaliers estimés sur une échelle commune. Les nombres sont
affichés à côté des barres et dans le détail par jour.
Un retour le même jour compte une fois dans l’estimation des visiteurs.
Chaque requête publique admissible ajoute une page vue. Le ratio divise les
pages vues par la somme des visiteurs journaliers estimés sur la période.

## Activation

Importer le ZIP dans Gestion des modules, puis ouvrir Configuration → Réglages
des statistiques et activer la mesure journalière. Les anciens modes de
collecte nécessitent ce nouveau réglage. Le rapport 2.0 démarre avec les
nouvelles mesures. Les anciennes tables restent disponibles dans SQLite.
Les journaux technique et d’audit restent accessibles dans Administration.

## Empreinte journalière

Le serveur utilise l’adresse IP vue par PHP, tronquée à /24 en IPv4 et /48
en IPv6, et le User-Agent. Une clé aléatoire par jour UTC permet de calculer
une empreinte HMAC-SHA-256. Les empreintes et clés des jours précédents sont
retirées des tables actives au premier affichage mesuré d’une nouvelle
journée ou lors d’une purge manuelle. Les rapports et exports contiennent
des agrégats. Le module n’enregistre ni l’IP complète ni le User-Agent brut
et ne crée aucun cookie statistique.

Les administrateurs et rédacteurs connectés, les robots identifiés, les
requêtes marquées DNT et les réponses non admissibles sont exclus des
mesures de lecteurs. Les robots identifiés sont comptés à part (voir plus bas). Une
requête sans IP valide ou User-Agent compte comme page vue, sans estimation
de visiteur ni de page d’entrée. Des visiteurs sur un même réseau avec un
navigateur similaire peuvent partager une empreinte. Les référents se
limitent au domaine transmis. Une réponse servie intégralement par un cache
en dehors de P3 peut ne pas être comptée. Sur un hébergement avec proxy,
la qualité de l’estimation dépend de l’adresse remise à PHP par le serveur.

Le module alimente aussi les compteurs d’articles utilisés par Accueil
pour la sélection pondérée. Les robots n’alimentent jamais ces compteurs.

## Provenance des visiteurs (2.1)

À la première page vue de la journée, le domaine référent est rangé en
moteur de recherche, assistant d’IA, autre site, lien interne ou accès
direct. Le paramètre `utm_source` de l’adresse d’entrée est noté s’il est
présent (lettres, chiffres, point, tiret, soulignement ; 64 caractères au
plus). Un assistant d’IA reconnu par ce paramètre (par exemple
`chatgpt.com`, ajouté par ChatGPT aux liens qu’il cite) compte comme
assistant d’IA même sans référent. Le tableau de bord croise aussi la
provenance et la page d’entrée. Les listes de moteurs et d’assistants se
trouvent dans `src/TrafficSources.php`.

## Robots et assistants d’IA (2.1)

Le User-Agent est classé sans être conservé : navigateur, robot de moteur
de recherche, collecte pour les IA (GPTBot, ClaudeBot, CCBot…), IA
consultant une page pour un utilisateur (ChatGPT-User, Claude-User,
Perplexity-User, OAI-SearchBot…) ou autre robot. Seuls les navigateurs
comptent comme lecteurs. Jusqu’à 2.0.4, les IA consultant une page pour un
utilisateur étaient comptées comme lecteurs ; elles ne le sont plus.

Un passage de robot ajoute un compteur par jour, par type et par page,
sans empreinte, page vue ni compteur d’Accueil. Le tableau de bord affiche
les passages par type et les pages les plus lues par les IA pour un
utilisateur. Le réglage « Compter les passages des robots », actif par
défaut, arrête ce comptage ; chaque passage compté représente une écriture
SQLite. Les listes se trouvent dans `src/AgentClassifier.php` et sont à
entretenir.

Limites : un robot qui se présente comme un navigateur reste compté comme
lecteur ; une application qui n’envoie pas de référent apparaît en accès
direct ; les visites issues des résumés IA de Google restent mêlées à
Google.

## Export CSV

Le fichier contient d’abord les totaux quotidiens (pages vues, visiteurs
journaliers estimés), puis, après une ligne vide, le détail de la période :
date, mesure, élément, titre et total. Mesures : `article`, `entry`,
`referrer`, `source`, `entry_source` (provenance|page), `utm`, `robot`
(type) et `robot_page` (type|page).

## Exclure les visites d’audit

Depuis la version 2.0.4, le module ignore une visite lorsque le User-Agent
contient `UniversGuitare-Audit`, lorsque l’en-tête `X-UG-Audit` vaut `1`,
ou lorsque l’URL porte `ug_audit=1`. Chaque marqueur fonctionne seul.
Pour un audit dans le navigateur, ajoutez le paramètre à chaque page
consultée, avec `?` ou `&` selon l’adresse.

Les exclusions s’appliquent aux mesures à venir. Les agrégats existants
et les journaux de l’hébergeur restent tels quels.
