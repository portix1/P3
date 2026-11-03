# Portix3 Search 1.0.4

Module de recherche autonome pour Portix3 0.7.05.

## Fonctions

- route publique `search.html`;
- indexation des articles et actualités publiés;
- SQLite FTS5 avec pondération titre / résumé / catégorie / contenu;
- recherche Unicode avec accents normalisés par FTS5;
- si aucun résultat exact : rapprochement orthographique limité (lettre ajoutée, oubliée, remplacée ou deux lettres voisines inversées), à partir des seuls contenus publiés ;
- message explicite lorsque des résultats approchants sont affichés ;
- extrait autour du passage trouvé;
- liens des résultats avec `#:~:text=` vers un court passage contenant le mot
  trouvé, avec son orthographe réelle (accents et mots complets);
- surlignage de repli géré par P3 via `p3hl`, notamment pour Firefox et les
  navigateurs qui n'appliquent pas les fragments de texte;
- filtres Tout / Articles / Actualités;
- contribution autonome au menu public via `PublicNavigation`;
- champ de recherche directement visible dans le menu, sans popup;
- résultats en liste compacte : titre, type / catégorie, extrait
  (deux lignes sur ordinateur, trois sur mobile);
- formulaire et filtres en haut de page, avec un espacement réduit;
- administration `Admin-search.html`;
- réindexation manuelle;
- synchronisation automatique si les contenus publiés changent;
- fallback fonctionnel si SQLite a été compilé sans FTS5;
- index stocké dans `var/modules/search/data/search.sqlite`, séparé de la base Portix3.

## Installation

Installer `P3-module-recherche-1.0.4.zip` depuis l’administration des modules,
y compris pour mettre à jour une version 1.0.0 ou 1.0.1 déjà installée.

Cette mise à jour modifie uniquement le dossier `var/modules/search/`.
L’index est reconstruit automatiquement à la prochaine recherche ou depuis
« Réindexer maintenant » ; les articles et actualités restent dans la base du site.

La feuille de style du module est chargée par sa contribution à la navigation.
Elle masque uniquement le lien de menu généré pour Search et affiche son
formulaire à la place. Les espacements du contenu sont réduits uniquement sur
`search.html`. Aucun fichier du noyau ni des thèmes n’est à remplacer.

Le dossier `var/modules/search/data/` doit rester inscriptible par PHP.

## Liens vers les passages

Le navigateur peut utiliser le passage indiqué par `#:~:text=` lorsqu’il prend
en charge les fragments de texte. P3 ajoute en parallèle un paramètre temporaire
`p3hl` : un petit script du module retrouve le même passage, le surligne et le
fait défiler dans Firefox et les autres navigateurs. Le paramètre est ensuite
retiré de l’URL sans recharger la page.

Le passage est extrait du contenu publié, dans un seul paragraphe ou bloc HTML.
Pour une actualité, son introduction peut aussi être ciblée. Si la recherche
correspond uniquement au titre, au résumé non affiché ou à la catégorie, le lien
cible le titre de la page. Les extraits visibles ne contiennent aucun marqueur
technique. La fonctionnalité est disponible en mode FTS5 et en mode compatibilité.

## Test

Après installation :

1. ouvrir `Admin-search.html`;
2. vérifier que le moteur annoncé est `SQLite FTS5`;
3. cliquer sur « Réindexer maintenant »;
4. ouvrir `search.html`;
5. rechercher un mot présent dans un article publié.

Test CLI facultatif :

```powershell
& "C:\xampp\php\php.exe" var\modules\search\tests\smoke_test_search.php
& "C:\xampp\php\php.exe" var\modules\search\tests\smoke_test_text_fragments.php
```


## Mise à jour 1.0.4

Installer le ZIP depuis Administration > Modules > Installer. Il contient uniquement
le dossier search et ses sources, sans base du site, configuration, index prérempli
ou médias. L’index local peut être reconstruit automatiquement après remplacement
du module ; une réindexation manuelle reste possible dans Admin-search.html.

La recherche exacte reste prioritaire. La tolérance s’applique seulement en
l’absence de résultats exacts, aux mots d’au moins quatre caractères : une erreur
pour les mots de 4 à 7 caractères, jusqu’à deux à partir de 8 caractères. Ce n’est
pas une prise en charge universelle des difficultés de lecture ou d’orthographe.
Les filtres Articles/Actualités et l’exclusion des brouillons sont conservés.

Vérifications locales : PHP 8.2/8.3/8.4 WebAssembly, FTS5 et compatibilité ;
129 contrôles des fragments de texte sous 8.2 ; recherche existante et essai
fendar -> fender sur les contenus du backup fourni (45 résultats) sous 8.2.
Le fonctionnement sur l’installation cible reste à vérifier.
