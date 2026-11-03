# Provenance des images et polices de P3

Inventaire établi à partir de l’archive candidate P3 0.8.1 construite depuis `a9376da`.

## Bandeaux et motifs des thèmes

- `var/templates/six-cordes/assets/guitar-header.webp` et `var/templates/univers-guitare/assets/guitar-fretboard-header-0703.webp` sont des créations de Marc, attribuées à la licence du projet dans la notice du 19 octobre 2026. Les fichiers de l’archive sont identiques à ceux de la copie des sources qui contient cette notice.
- Les thèmes Six Cordes Lecture et Six Cordes UX Clair embarquent des copies identiques du bandeau WebP de Six Cordes. Le PNG dans Six Cordes représente le même bandeau dans un autre format.
- Les motifs SVG des thèmes Lutherie, Signature, Six Cordes et Valve proviennent des sources du projet et relèvent de sa licence. Le motif `contour.svg` est repris dans Six Cordes Lecture et Six Cordes UX Clair.

## Polices de Six Cordes UX Clair

Les quatre fichiers WOFF2 livrés dans `var/templates/six-cordes-ux-clair/assets/fonts/` sont identiques à ceux des paquets `@fontsource-variable/literata` et `@fontsource-variable/work-sans` en version 5.3.0. Chaque famille est livrée avec les variantes Latin et Latin Extended.

[Literata](https://github.com/googlefonts/literata) et [Work Sans](https://github.com/weiweihuanghuang/Work-Sans) sont distribuées sous SIL Open Font License 1.1. Le fichier `licenses.css` placé avec les polices contient les mentions de droit d’auteur et le texte de la licence pour les deux familles. Conserver cette notice lors de la redistribution des polices.

## Icônes et médias de site

La notice du sous-ensemble d’icônes Lucide et Feather de l’administration figure dans `var/admin/lucide/LICENSE.txt`. Les médias ajoutés par les utilisateurs à leur site ne font pas partie de l’archive candidate.
