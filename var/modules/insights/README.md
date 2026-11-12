# Statistiques P3 2.0

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
requêtes marquées DNT et les réponses non admissibles sont exclus. Une
requête sans IP valide ou User-Agent compte comme page vue, sans estimation
de visiteur ni de page d’entrée. Des visiteurs sur un même réseau avec un
navigateur similaire peuvent partager une empreinte. Les référents se
limitent au domaine transmis. Une réponse servie intégralement par un cache
en dehors de P3 peut ne pas être comptée. Sur un hébergement avec proxy,
la qualité de l’estimation dépend de l’adresse remise à PHP par le serveur.

Le module alimente aussi les compteurs d’articles utilisés par Accueil
pour la sélection pondérée. L’export CSV fournit les agrégats quotidiens.
