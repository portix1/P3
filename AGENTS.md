# Règles de développement de P3

## Organisation obligatoire du back-office

Cette organisation s’applique à toute création ou modification du moteur, d’un module ou d’un plugin. Une évolution ne doit pas réintroduire un écran mélangé ou une navigation indépendante.

- Le tableau de bord présente l’état du moteur, des modules et du thème actif, puis dirige vers trois pages distinctes.
- Rédaction regroupe les fonctions éditoriales et utilise le bleu.
- Administration regroupe les comptes, les droits et la maintenance et utilise l’orange.
- Configuration regroupe les paramètres du site et des composants et utilise le violet.
- Les thèmes, leur choix et leur installation appartiennent à Configuration.
- Le menu latéral est limité à l’espace ouvert. Les trois accès d’espace restent disponibles selon les droits.
- Chaque entrée possède un libellé et une icône. La couleur accompagne les libellés.
- Les cartes d’état utilisent une présentation commune et une valeur principale en bleu. Un lien de mise à jour apparaît uniquement lorsqu’une version plus récente existe. Une erreur de vérification ne doit jamais être assimilée à une absence de mise à jour.
- Les fonctions d’un même module peuvent appartenir à des espaces différents. Le classement se fait par fonction.
- Les contributions sont centralisées dans src/Navigation/AdminNavigation.php et réutilisées par les pages d’espace et le menu. Un contrôleur fourni par le moteur, comme Actualités, doit rester visible même sans config.php dans son module.
- Le classement ne modifie pas les autorisations. Les contrôles de rôle et de formulaire doivent être appliqués côté serveur.

## Vérification et documentation

Toute modification de navigation doit vérifier administrateur, rédacteur et visiteur, module actif et désactivé, et liens directs vers les contrôleurs concernés. Exécuter tools/smoke_test_admin_navigation.php et les tests touchés.

Actualiser UX-BACK-OFFICE.md et la documentation fonctionnelle concernée dans src/Installation/default-content.json lorsqu’un parcours change. Le guide de développement des modules doit conserver ces règles pour les nouveaux composants.

Le code fait référence sur GitHub. Les décisions et l’état d’avancement sont reportés dans le référentiel Google Drive lorsque cet accès est disponible. Distinguer décision validée, code testé et validation visuelle par Marc.
