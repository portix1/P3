# Catalogue officiel des extensions P3 : chaîne de confiance

## Principe appliqué

Les versions du catalogue et les ZIP annoncés sont désormais lus depuis un
**commit Git complet**, identifié par son SHA dans
`src/Maintenance/ExtensionUpdates.php`. Le SHA-256 exact du fichier
`distribution/catalogue.json` figure également dans le moteur.

À chaque chargement, le moteur contrôle ce SHA-256 **avant d'utiliser le
catalogue**. Le même commit sert de base aux URL de téléchargement. Ainsi, un
changement sur `main`, une modification d’un tag ou un catalogue altéré dans
la session ne peut pas substituer un autre ZIP officiel sans modification du
moteur installé.

Les ZIP du catalogue doivent correspondre aux empreintes SHA-256 déclarées.
Le test `tools/smoke_test_extension_integrity.php` vérifie le catalogue
livré avec le code et chaque archive de `distribution/`.

**Il n'existe pas de signature numérique indépendante.** L'authenticité
repose sur les références Git inscrites dans le code installé et sur le
contrôle du contenu, et non sur une clé de signature. GitHub reste la source
d'hébergement ; les imports manuels de ZIP extérieurs au catalogue restent
autorisés et explicitement indiqués comme non vérifiés.

## Publier une nouvelle version d'extension

1. Préparer et tester le ZIP, son manifeste interne et sa compatibilité.
2. Déclarer sa version, son nom de fichier, ses prérequis et son SHA-256
   dans `distribution/catalogue.json`.
3. Valider la correspondance des fichiers, le format du catalogue, les rôles,
   le contrôle d'intégrité et les autres tests de CI.
4. Fusionner le changement de distribution et relever le **SHA du commit**
   final, jamais le nom de la branche `main` ni un tag mobile.
5. Calculer le SHA-256 **exact** du `catalogue.json` de ce commit.
6. Mettre à jour ensemble `TRUSTED_REF` et
   `TRUSTED_CATALOGUE_SHA256` dans le moteur, avec revue et tests.
7. Distribuer cette évolution du moteur sous la forme d'un patch ou d'une
   nouvelle version de P3. Les installations existantes continuent de
   pointer vers l'ancienne distribution tant qu'elles ne sont pas mises à
   jour. Elles ne reçoivent pas de nouvelles annonces de façon implicite.

Ce choix privilégie un catalogue **figé et contrôlable**. Il ne convient
pas à une publication fréquente des extensions sans mise à jour du moteur.
Si ce besoin apparaît, prévoir un **catalogue signé hors ligne** avec une
clé publique ancrée dans P3, une procédure de rotation et un refus des
signatures invalides. Ne jamais mettre une clé privée dans GitHub.

## Incidents et retour arrière

- Si la vérification du catalogue échoue, P3 n'annonce aucune mise à jour
  officielle et affiche un avertissement ; il ne télécharge pas de ZIP depuis
  une référence non vérifiée.
- Si une archive annoncée ne correspond pas au SHA-256 déclaré, elle est
  refusée avant extraction ou analyse.
- Les imports manuels restent distincts, ne deviennent jamais « officiels »
  en raison de leur nom ou de leur emplacement.
- La base SQLite, les contenus et les URLs publiques ne sont pas modifiés
  par ces contrôles. Une ancienne version fiable du moteur peut être
  restaurée si le code a été sauvegardé.

La gestion des versions publiées doit rester cohérente entre le catalogue
figé, les ZIP effectivement accessibles et le canal stable/candidat.
