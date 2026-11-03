# Statistiques et journaux 1.0.3

## Installation en développement

Importer directement P3-module-statistiques-1.0.3.zip dans Administration >
Modules > Installer. Mettre à jour le module existant sans le désinstaller.
Sauvegarder l’installation avant essai. Ce paquet contient uniquement le module,
les descriptions de schéma et les tests : aucune base SQLite, aucun réglage de
site, aucun média, aucun journal réel et aucun fichier du moteur.

L’installateur ajoute trois tables à la base existante. Les réglages de collecte
et l’historique restent conservés. Il n’est pas nécessaire de désactiver puis
réactiver la collecte.

## Correction du chargement des libellés (1.0.3)

L’administration du module charge et transmet explicitement ses traductions au
template avant ses valeurs calculées. Elle ne dépend plus uniquement du passage
global du moteur pour exporter les traductions. Cela corrige la variable absente
`insights_cookie_breakdown` lorsqu’un ancien jeu de libellés reste dans le contexte
de rendu. Les données et la méthode de comptage de 1.0.2 sont conservées.

Un jeu de libellés incomplet reproduit exactement l’exception avec 1.0.2 ; le
même test passe avec 1.0.3. Le rendu complet Application → moteur → contrôleur →
interface admin a été vérifié en français et en anglais sur les sources de la
sauvegarde P3 fournie : audience, réglages, journal technique et journal d’audit.
Les essais utilisent une base et un administrateur fictifs, jamais la base ni les
identifiants de production. Tests PHP 8.2 et 8.5 WebAssembly. Le fonctionnement
revenu spontanément sur le site est compatible avec un décalage de cache PHP,
mais ces essais ne prouvent pas la cause du décalage observé chez LWS.

## Mesures corrigées

Un cookie envoyé par le serveur ne prouve pas son acceptation. Désormais, seul
un cookie valide effectivement renvoyé dans une requête identifie un navigateur.
Les premières pages, les cookies refusés ou absents et le mode minimal restent
comptés dans les pages vues, avec une répartition sans cookie confirmé. Un robot
non reconnu qui ne renvoie jamais de cookie ne crée plus un nouvel identifiant
à chaque page.

La nouvelle série « Navigateurs avec cookie confirmé » commence après la mise à
jour. Les anciens identifiants et compteurs sont gardés séparément, signalés dans
le tableau de bord et exclus des nouveaux uniques et ratios : leur confirmation
ne peut pas être reconstruite rétroactivement.

Le ratio « Pages confirmées par navigateur » utilise uniquement les pages reçues
avec un cookie valide. La première page sans cookie reste dans les pages vues,
mais n’est pas réattribuée lors d’un retour. Les lecteurs d’une seule première
page ne figurent donc pas dans les navigateurs confirmés. Aucun taux de rebond ou
comptage exhaustif des personnes n’est revendiqué. Un robot qui renvoie des
cookies peut toujours entrer dans les navigateurs confirmés.

Les référents des pages du domaine courant, avec ou sans www, sont regroupés sous
« Navigation interne », y compris pour les données historiques. L’absence de
référent est libellée « Sans référent (direct ou non communiqué) ». Ce classement
ne prétend pas mesurer uniquement les sources d’entrée des visites. Les données
historiques de référents ne sont pas réécrites.

Le CSV ajoute les colonnes pages_cookie_confirme_depuis_1_0_2 et
pages_sans_cookie_confirme_depuis_1_0_2. Les anciennes journées sans cette
répartition ont des cellules vides, pas de faux zéros.

Les dates et frontières de journées restent en UTC. Les moyennes quotidiennes
portent sur la totalité de la période sélectionnée, même si la nouvelle collecte
n’en couvre qu’une partie.

## Portée

Aucun suivi par IP ni empreinte d’appareil ajouté. Le mécanisme de cookie existant
reste limité au mode audience ; ce correctif ne fournit pas de gestionnaire de
consentement. Les exclusions des administrateurs/rédacteurs connectés, de Do Not
Track, des requêtes POST et des robots identifiés restent appliquées. En mode
minimal aucun cookie d’audience n’est créé ou utilisé ; le mode désactivé ne
collecte rien. Les pages servies entièrement depuis un cache hors PHP ne sont
pas mesurées par ce module.

Le filtrage des robots reste heuristique. Ce paquet corrige l’inflation liée aux
cookies non renvoyés ; il ne garantit pas que les requêtes restantes sont humaines
et ne bloque pas les robots. Les anciens pics de pages restent présents.

## Vérification après installation

1. Garder le mode de collecte déjà choisi. En mode audience, ouvrir une fenêtre
   privée, non connectée à l’administration, sans Do Not Track.
2. Ouvrir une première page : une page sans cookie confirmé, aucun nouveau
   navigateur confirmé. Ouvrir une seconde page : un navigateur confirmé et une
   page confirmée. Ouvrir une troisième : toujours un navigateur, deux pages
   confirmées. Les trois pages sont comprises dans les pages vues générales.
3. Avec les cookies bloqués, consulter plusieurs pages : les pages sans cookie
   confirmé augmentent, les navigateurs confirmés n’augmentent pas.
4. Vérifier le regroupement « Navigation interne » et l’export CSV.

## Tests exécutés le 31 octobre 2026

PHP 8.2, 8.3, 8.4 et 8.5 via WebAssembly, SQLite et composants réels P3 isolés :
installation 1.0.1 vers 1.0.2 additive et répétée, conservation de l’historique et
des réglages, vraies requêtes SAPI pour l’émission/retour/remplacement des cookies,
Secure/HttpOnly/SameSite et chemin /test/, 1000 appels de collecte sans cookie,
exclusions, modes minimal/désactivé, affichage des compteurs et des référents,
périodes, rétention, transaction et intégrité SQLite. Compilation/rendu du
véritable template et export CSV contrôlés sous PHP 8.2. Ce ne sont pas des essais
sur PHP natif LWS ni sur la base de production.

Tests PHP livrés : depuis la racine d’une copie P3, lancer
`php var/modules/insights/tests/smoke_test_confirmed_audience.php`.
Le test historique `smoke_test_audience.php` reste fourni pour les anciennes
mesures. Ils utilisent exclusivement des bases temporaires.

## Retour à la version précédente

Restaurer le dossier de module sauvegardé avant la mise à jour. Les tables ajoutées
peuvent rester présentes sans être utilisées ; ne pas les supprimer manuellement.
Un retour à 1.0.1 réintroduit le défaut de comptage et arrête la nouvelle série.
