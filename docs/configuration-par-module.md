# Configuration par module

Demande de Marc du 20 novembre 2026, réalisation autorisée immédiatement.

## Parcours retenu

La page Configuration et son menu latéral présentent une seule entrée par module. Une fonction unique reste accessible directement. Plusieurs fonctions d’un même module ouvrent une page-menu qui donne accès à chacune d’elles.

Accueil présente « Configuration de l’accueil », puis Réglages, Composer et Résultats. La protection des contenus suit le même principe. Le moteur possède une entrée « Configuration du site » regroupant identité, référencement, messagerie, thèmes, gestion des modules, mises à jour et diagnostics.

Le regroupement est générique et reprend les contributions de l’inventaire AdminNavigation. Un module tiers déclarant plusieurs fonctions de Configuration bénéficie du même parcours. Les fonctions d’autres espaces gardent leur classement.

Le fil d’Ariane permet de revenir à Configuration puis à la page-menu du composant. Une fonction déjà ouverte marque son composant dans le menu latéral. Les anciennes adresses des écrans restent utilisables.

## Critères de validation

- Une entrée par module dans la page Configuration et dans sa navigation latérale.
- Chaque fonction reste accessible depuis la page-menu correspondante.
- Les modules désactivés disparaissent, y compris leurs pages-menu.
- Rédacteurs et visiteurs refusés côté serveur sur les pages-menu et les actions de Configuration.
- Navigation clavier, focus visible, absence de débordement à 320 px et titres compréhensibles.
- Tests du moteur, d’Accueil, de la protection des contenus et d’un module tiers à plusieurs fonctions.

Le périmètre initial porte sur Configuration ; la demande suivante étend le même mécanisme à la gestion des articles dans Administration. La réécriture de Téléchargements et les autres pistes gardent leur cadrage propre.

## Administration : gestion des articles — 20 novembre 2026

Administration et son menu latéral présentent une entrée « Gestion des articles ». Elle ouvre une page-menu donnant accès aux catégories, aux séries et à l’ordre des articles. Les adresses et les fonctions existantes sont conservées. Le fil d’Ariane permet de revenir à cette page-menu et son entrée reste sélectionnée dans chaque fonction. Le regroupement partage le mécanisme de Configuration ; une fonction unique reste directe. Les permissions administrateur restent contrôlées côté serveur. Aucun changement de données ni de version propre au module Articles : ce parcours est fourni par le moteur candidat 0.9.10.

Pour les modules tiers, prefix_management dans la langue du module permet de nommer la page-menu Administration ; le nom déclaré dans le manifeste sert de repli. Ce regroupement concerne uniquement plusieurs contributions dans le même espace.
