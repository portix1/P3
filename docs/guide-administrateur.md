# Guide de l’administrateur P3

Connectez-vous à `login.html` avec votre compte administrateur. Le **Tableau de bord** donne accès aux trois espaces.

| Espace | Où intervenir |
| --- | --- |
| **Rédaction** | Créer et relire les articles et les actualités. |
| **Administration** | Gérer les comptes et leurs droits, traiter les messages et suivre l’activité du site. |
| **Configuration** | Régler l’identité du site, composer l’accueil, modifier les menus, gérer les thèmes et les modules, lancer les diagnostics et les mises à jour. |

Les fonctions proposées dépendent des modules activés. Les catégories, séries, menus et autres éléments de structure restent sous la responsabilité de l’administrateur.

## Trouver une fonction dans Configuration

Configuration affiche une seule entrée par module, dans la page et dans le menu latéral. Cliquez sur le composant à régler. S’il propose plusieurs fonctions, une page-menu permet de choisir l’action.

**Configuration de l’accueil** ouvre **Réglages**, **Composer** et **Résultats**. **Protection des contenus** regroupe ses fonctions et ses réglages. **Configuration du site** ouvre les fonctions du moteur, notamment l’identité, le référencement, la messagerie, les thèmes, les modules, les mises à jour et les diagnostics.

Une entrée comportant une seule fonction l’ouvre directement. Le fil d’Ariane permet de revenir à la page-menu du composant, puis à Configuration. Les modules désactivés disparaissent de ce parcours.

## Créer un compte et choisir ses droits

1. Dans **Administration → Comptes et droits**, choisissez **Créer un compte**.
2. Renseignez le pseudonyme et les informations du compte, attribuez le rôle **Rédacteur** et définissez le mot de passe initial.
3. Enregistrez le compte, rouvrez sa fiche et repérez **Droits de rédaction des articles**.
4. Choisissez **Relecture obligatoire** ou **Publication directe**, puis enregistrez les droits dans leur propre formulaire.

Avec la relecture obligatoire, le rédacteur soumet ses articles avant la mise en ligne. Avec la publication directe, il peut aussi corriger ses propres articles déjà publiés. Vérifiez les catégories accessibles et les autres droits accordés dans cette fiche.

## Relire un article

Dans **Rédaction → Articles**, utilisez **Voir les demandes** ou filtrez les articles sur **En attente de relecture**. Ouvrez l’article et vérifiez son aperçu si nécessaire. Dans la liste, **Accepter et publier** met en ligne la version associée à cette liste. Si l’article a changé entre-temps, P3 refuse l’action et vous invite à recharger la liste et à relire la version actuelle. Cette vérification s’applique aussi au retour pour correction. **Renvoyer pour correction** demande un commentaire et remet l’article en brouillon. Le rédacteur pourra soumettre une nouvelle version.

## Installer une mise à jour

1. Sauvegardez les fichiers du site et la base SQLite. Conservez une copie hors de l’hébergement et repérez les règles HTTP propres au site.
2. Ouvrez **Configuration → Mises à jour**. Si P3 propose une version, lancez l’installation et attendez son résultat.
3. Contrôlez la version affichée dans le tableau de bord, puis vérifiez l’accueil, un article et la connexion au back-office. Réouvrez les formulaires commencés avant la mise à jour.

Si la vérification des mises à jour est indisponible, notez le message exact. En cas de refus lié à la signature ou à la clé, vérifiez la distribution avec la [procédure de signature](../SIGNED-RELEASES.md#vérifier-une-distribution-téléchargée).

## Restaurer après une mise à jour

Dans **Configuration → Protection des contenus → Rollback**, choisissez le point voulu et cliquez sur **Restaurer ce point**. Le point complet rétablit les fichiers applicatifs, la base, la configuration et les médias sauvegardés. Les modifications effectuées depuis ce point peuvent être perdues. P3 crée un point de secours de l’état courant avant la restauration.

Si un écran 503 empêche l’accès au back-office après une interruption, utilisez la [procédure de reprise par FTP ou depuis le panneau de l’hébergeur](installation-et-maintenance.md#reprendre-un-site-bloqué-en-maintenance).

## Aider les rédacteurs

Transmettez le [guide du rédacteur](guide-redacteur.md) avec l’adresse de connexion et indiquez si leur compte publie directement ou passe par une relecture.
