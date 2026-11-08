# Feuille de route de P3

## Première distribution autonome

Le paquet de base comprend le moteur, les composants indispensables et un thème neutre par défaut. Il doit permettre d’installer P3 sur un serveur vierge.

### Acquis

- [x] Suite automatisée validée sur PHP 8.2, 8.3 et 8.4.
- [x] Premier essai d’installation neuve. Il a révélé deux points corrigés depuis.
- [x] Titre P3 proposé par l’installateur ([PR nº 2](https://github.com/portix1/P3/pull/2)).
- [x] Nom SQLite saisi ou généré à l’installation. P3 conserve le chemin historique pour les sites existants ([PR nº 4](https://github.com/portix1/P3/pull/4)).

- [x] Archive candidate 0.8.1 assemblée depuis `a9376da` et validée après extraction sous PHP 8.4.
- [x] Installation de l’archive candidate confirmée sur le serveur de test.
- [x] Restauration testée sur une copie de l’archive avec un nom SQLite personnalisé. Articles, identifiants, URL, compte et configuration ont été comparés avant et après.

- [x] Mise à jour de 0.8.01-rc1 vers 0.8.1 sur une installation de test sous PHP 8.4. Sauvegarde préalable, conservation des articles, comptes, slugs et réglages, maintien du fichier SQLite historique et intégrité SQLite contrôlés.

### Ordre des prochains travaux

1. **Préparer une archive candidate.** Définir les composants livrés et vérifier les ressources et leurs licences. Contrôler que l’archive ne contient ni base, ni configuration privée, ni médias d’une installation.
2. **Tester l’installation depuis cette archive.** Sur un serveur vierge, vérifier les prérequis, les permissions, le formulaire et les deux modes de nommage SQLite. Se connecter comme administrateur, publier un article et ouvrir sa page publique. Vérifier les guides fournis au fil de cet essai.
3. **Tester une mise à jour sur une copie existante.** Vérifier la conservation des contenus, comptes, réglages et URL. Tester la sauvegarde et la restauration, y compris avec un nom SQLite personnalisé.
4. **Préparer la publication.** Vérifier que l’archive testée correspond au code validé et consigner les conditions d’installation et de mise à jour.

## Distribution des extensions

Après validation du paquet de base :

1. Préparer le catalogue des versions et les archives vérifiées du moteur, des modules et des thèmes.
2. Préparer le lanceur PHP qui permet de choisir les composants et de les installer.
3. Ajouter la détection et l’installation des mises à jour depuis l’administration.

Le déploiement sur Univers Guitare suit son propre cycle.

## Réorganisation UX du back-office

- [x] Inventaire des fonctions par module et classement commun au menu et au tableau de bord.
- [x] Trois espaces Rédaction, Administration et Configuration, avec icônes et droits existants.
- [x] Pages séparées pour identité, apparence, référencement et messagerie.
- [x] Réglages des modules actifs accessibles dans Configuration.
- [x] Installation directe des thèmes après validation sans conflit.
- [x] Parcours HTTP vérifiés sous PHP 8.2 et 8.4 sur une installation de test.
- [ ] Retours visuels sur le serveur de test de Marc.

L’inventaire et la procédure de test figurent dans [UX-BACK-OFFICE.md](UX-BACK-OFFICE.md).

## Back-office — règle validée le 4 novembre 2026

L’organisation en trois pages Rédaction, Administration et Configuration est obligatoire pour les modules et plugins à venir. Voir AGENTS.md et UX-BACK-OFFICE.md.

- [x] Tableau de bord limité à l’état du site et aux trois accès.
- [x] Menu limité à l’espace courant, couleurs bleu, orange et violet.
- [x] Thème actif affiché et liens de mise à jour conditionnels.
- [x] Actualités réintégré malgré son contrôleur fourni par le moteur.
- [x] Contrôle de toutes les entrées administrables livré avec la validation automatisée.
- [ ] Retour visuel de Marc sur la nouvelle archive.

## Accueil — priorité P1, conception validée le 8 novembre 2026

- [x] Maquette à compositions variées, images par article ou zone de catégorie, nombre variable de catégories.
- [x] Première implémentation du module, tirage pondéré, cache commun, mesures facultatives de visibilité et de clic.
- [ ] Validation visuelle du module intégré par Marc avant production.
- [ ] Ajustement des poids après constitution de données exploitables.

Voir `var/modules/home/README.md` pour les limites et les réglages. L’ajout d’images dans les fiches Affiliation reste séparé.

## Distribution modulaire — correction du découpage

Le socle 0.8.4 apporte des déclarations génériques de navigation, d’actions publiques sans session anonyme et de version minimale. Il ne connaît pas le module Accueil. Accueil 1.0.1 est distribué séparément sous forme d’un ZIP installable : styles, écrans, tirage, mesures et migration de ses anciennes tables sont contenus dans le module. La 0.8.3-rc4 doit recevoir le socle compatible une seule fois. L’archive complète de test 2713cd56 est remplacée pour les nouveaux essais par cette séparation.

## Migration production — 8 novembre 2026

Cible demandée : 0.8.4-rc4 depuis 0.8.01-beta1-preview5. Archive réelle reçue (223 articles). Reprendre impérativement les correctifs canoniques absents de la branche de développement ; préserver configuration, base et thèmes de production. Livrer une mise à niveau complète, sans installation neuve ni changement automatique de la cible d’accueil.

## Accueil 1.0.3 — disposition demandée

Zones sur deux ou trois colonnes selon la largeur disponible sur ordinateur, une seule sur mobile. Texte libre en pleine largeur ; ordre et compositions préservés. Aucun titre général au-dessus des zones. Mise à jour autonome du module, sans modification du moteur ou du thème.

## Accueil 1.0.4 — miniatures et habillage

Images compactes hautes de trois lignes de texte, alignées à gauche avec habillage réel puis retour du texte sur toute la largeur. Miniatures WebP 256 px maximum sans modifier les originaux. Format commun sur ordinateur/mobile, images d’articles/catégories et texte libre.

## Accueil 1.0.5 — miniatures refusées en production

Corriger les noms des miniatures pour respecter la liste autorisée du .htaccess des images : 32 caractères hexadécimaux et extension WebP. Ne pas assouplir les protections Apache. Régénération automatique à la consultation, sans modifier les originaux.

## Accueil 1.0.6 — hauteur prioritaire des miniatures

Corriger la boîte carrée : image haute de trois lignes de texte avec largeur automatique selon ses proportions, sans limite à 35 % de la colonne. Habillage à gauche conservé. Vérifier portrait, paysage et carré.

## Prochains travaux — décisions du 8 novembre 2026

### Demandes validées, à développer

- [ ] **Bloc « Derniers articles »** : proposer les trois publications les plus récentes, titre, court extrait et miniature facultative. Ordre chronologique de publication, sans tirage aléatoire, sans inclure les brouillons ou publications futures. Aucun texte d’annonce à rédiger et aucune dépendance au module Actualités. Conserver les autres sélections semi-aléatoires ; masquer le bloc s’il ne contient rien de publiable.
- [ ] **Classement plus logique du back-office** : thèmes, choix et installation dans Configuration ; composition et gestion de l’accueil dans Administration, et non dans Rédaction ; réglages techniques de l’accueil dans Configuration. Cette cible remplace le classement de la composition d’Accueil 1.0.x en Rédaction. Examiner les autres fonctions une par une et corriger les incohérences du menu, des cartes et des liens directs. Distinguer classement et permissions : les droits actuels ne changent pas du seul fait de cette décision de roadmap ; leur cohérence devra être examinée lors de l’implémentation. Actualiser alors AGENTS.md, UX-BACK-OFFICE.md, les déclarations des modules, guides et tests.
- [ ] **Sélecteur visuel de médiathèque** : bouton de choix avec miniatures, recherche et aperçu pour les images associées aux zones ; réutiliser le média sans duplication. Cette demande déjà enregistrée dans Drive reste à développer.

### Suggestions à examiner, non encore validées

- [ ] **Hiérarchie visuelle de l’accueil** : moins de blocs identiques ; alterner article développé, groupes de liens et zones plus larges, dans une composition stable inspirée du principe de découverte de Wikipédia. Conserver le tirage semi-aléatoire que Marc juge utile.
- [ ] **Aperçu avant publication de la composition** : vérifier les zones et leur rendu ordinateur/mobile avant de modifier la page publique. Ne pas confondre consultation de l’accueil actuel et prévisualisation de changements non publiés.
- [ ] **Ordre des zones par glisser-déposer**, avec commandes clavier équivalentes, pour éviter de saisir manuellement des numéros.
- [ ] **Explications des zones vides dans la gestion** : distinguer zone désactivée, catégorie sans contenu publiable et module source désactivé ; conserver une page publique sans blocs vides ni messages techniques.
- [ ] **Évaluation de la circulation depuis l’accueil** : utiliser le rapport d’expositions et de clics existant avant de modifier la pondération. Prévoir un volume de données suffisant ; ne pas changer le tirage sur quelques clics seulement. Ce point rejoint la phase d’ajustement déjà prévue.

Statut : planification uniquement ; aucune de ces évolutions n’est livrée par cette mise à jour de roadmap. Ne pas ajouter de « méta-news » pour alimenter artificiellement l’accueil.
