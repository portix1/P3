# Portix3 — Menu de bas de page 1.1.2

Module autonome pour Portix3 0.7.x.

## Fonctionnalités

- 2 ou 3 colonnes, avec uniquement les colonnes actives affichées ;
- titre éditable pour chaque colonne ;
- bouton `+ Ajouter un lien` dans chaque colonne, sans rechargement de page ;
- glisser-déposer fonctionnel via un script externe compatible avec la CSP Portix3 ;
- déplacement par glisser-déposer d’une colonne à une autre ;
- ordre recalculé automatiquement lors de l’enregistrement ;
- visibilité et ouverture éventuelle dans un nouvel onglet ;
- stockage dans la base SQLite centrale via le schéma de module Portix3 ;
- rendu public via le slot `footer` déjà fourni par les thèmes Portix3 ;
- aucune modification du noyau nécessaire ;
- footer pleine largeur du contenu, sans espace inférieur et sans coins arrondis ;
- les colonnes publiques sans lien visible ne réservent aucune place ;
- affichage responsive sur une colonne en petit écran.

## Installation

Dans l'administration : Configuration > Modules > Installer un module, puis envoyer l'archive ZIP du module.

L'administration du module est ensuite disponible via `Admin-footer.html`.

Le menu public n'est affiché qu'après création d'au moins un lien visible.


## 1.1.2
- Initialisation explicite des libellés du template d’administration avec valeurs de repli, afin d’éviter les variables de template non définies lors d’une mise à jour de module.
