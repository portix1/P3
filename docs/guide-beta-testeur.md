# Guide du bêta-testeur P3

Installez la version indiquée par le mainteneur sur un site d’essai. Relevez la version de P3, celle de PHP, le type de serveur Web et le navigateur. Utilisez des comptes et des contenus fictifs.

| Parcours | Action | Résultat attendu |
| --- | --- | --- |
| Installation | Suivre le [guide d’installation](installation-et-maintenance.md) et se connecter | Le tableau de bord s’ouvre et le site public répond. |
| Rédaction avec relecture | Créer un compte avec relecture obligatoire. Depuis ce compte, enregistrer un brouillon puis demander sa publication. | L’article attend une relecture. Un visiteur anonyme ne peut pas l’ouvrir. |
| Demande de correction | Renvoyer l’article en brouillon avec un commentaire, puis le soumettre de nouveau depuis le compte rédacteur. | Le rédacteur retrouve le commentaire. La nouvelle demande apparaît dans la liste. |
| Publication directe | Accorder la publication directe à un autre compte rédacteur et publier un nouvel article avec ce compte. | Le visiteur peut ouvrir l’article. Le rédacteur ne peut pas ouvrir Configuration ni changer les menus. |
| Images et mise en page | Ajouter une image avec légende, l’aligner à gauche ou à droite, puis enregistrer et rouvrir l’article. | L’image et sa légende restent associées. L’alignement est conservé dans l’éditeur et sur la page publique. |
| Menus et catégories | Depuis un compte administrateur, créer une catégorie et ajouter au menu un lien vers un article publié. Ouvrir le lien en visiteur, puis vérifier les accès depuis un compte rédacteur. | Le lien ouvre l’article attendu. Le rédacteur retrouve la catégorie si elle lui est accessible et ne peut pas modifier le menu. |
| Personnalisation | Depuis **Configuration**, modifier un réglage du thème actif ou sélectionner un autre thème disponible, puis ouvrir l’accueil et un article. | Le changement apparaît sur les pages publiques et les contenus restent consultables. |
| Ordinateur, tablette et smartphone | Ouvrir l’accueil, un article avec image et le formulaire de rédaction sur chaque appareil disponible. Noter le modèle ou la largeur utilisée. | Le texte reste lisible et les commandes nécessaires restent accessibles. Signaler les débordements qui masquent du contenu ou empêchent une action. |
| Sauvegarde manuelle | Suivre les consignes du [guide d’installation et de maintenance](installation-et-maintenance.md#mettre-à-jour-un-site-existant). Récupérer la copie en dehors de l’hébergement et vérifier son contenu. | La copie contient les fichiers du site et la base SQLite. Noter les étapes qui dépendent du panneau de l’hébergeur. |
| Mobile et clavier | À largeur réduite, ouvrir le menu avec Entrée, le fermer avec Échap et passer de l’éditeur au champ Description avec Tab. | Le focus reste visible et revient au bouton après fermeture du menu. |
| Mise à jour et restauration | Sur le site d’essai sauvegardé, appliquer une mise à jour officielle disponible et restaurer le point créé. | La version attendue, l’article, le média utilisé et les réglages locaux sont présents après chaque étape. |

## Comparer les hébergements et relever les ralentissements

Si vous testez plusieurs hébergements, relevez pour chacun le mode d’installation (FTP ou gestionnaire de fichiers), la version de PHP et le résultat du précontrôle. Indiquez l’étape et le message exact si l’installation bloque.

Si une page semble lente, relevez son adresse, l’action précédente et le temps observé sur plusieurs essais dans les mêmes conditions. Commencez par l’accueil, un article, une recherche et le tableau de bord. Si une erreur apparaît, indiquez sa fréquence et si recharger la page rétablit le fonctionnement.

## Signaler un défaut

Dans les [issues du dépôt](https://github.com/portix1/P3/issues), indiquez la version, l’environnement, l’écran concerné, les étapes exactes, le résultat attendu et le résultat obtenu. Précisez si le problème se reproduit avec un compte administrateur ou rédacteur. Pour une anomalie d’affichage, joignez une capture et donnez la largeur de l’écran.

Joignez un extrait de diagnostic si nécessaire, après en avoir retiré les données privées. Pour un défaut qui pourrait exposer des données ou contourner des droits, utilisez un canal privé convenu avec le mainteneur.
