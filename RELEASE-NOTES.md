# P3 0.9.2-rc1 — candidate de consolidation

**Candidate de préparation, non publiée.** La version stable reste **P3 0.9.1**.
La compilation et les tests automatisés ne constituent pas une autorisation de déploiement.

## Changements depuis 0.9.1

- Espaces **Rédaction**, **Administration** et **Configuration** strictement distincts : la rédaction reste limitée au contenu ; la configuration et la structure du site sont réservées aux administrateurs.
- Choix individuel des droits : **relecture obligatoire** ou **publication directe** pour chaque rédacteur. Le choix de publication directe autorise aussi la correction de ses articles publiés.
- Circuit de relecture : demande de publication, validation par l'administrateur, retour motivé en brouillon, visibilité de la demande de correction.
- Recherche et filtres d'articles, pagination à 25 éléments, conservation du contexte de liste après modification.
- Formulaire simplifié, sauvegarde automatique privée toutes les 30 secondes et proposition de récupération d'un brouillon. La sauvegarde automatique **ne change jamais le statut de publication**.
- Migration SQLite de schéma **8 vers 9**, non destructive : ajout du champ `articles.review_note`. La sauvegarde automatique crée sa table privée `article_autosaves` au premier usage.

## Module autonome et provenance

- Module **Articles 1.4.9** inclus dans cette candidate ; compatible avec **P3 0.9.2-rc1 ou version ultérieure**.
- Catalogue de cette candidate et ZIP autonome Articles reconstruits ensemble, avec empreintes SHA-256 et référence Git de confiance figée.
- Il ne faut pas remplacer séparément le catalogue, le module et la référence de confiance.
- Le canal stable de P3 0.9.1 reste inchangé tant que la 0.9.2 n'est pas approuvée et publiée.

## Mise à jour d'une installation existante

**Sauvegarder les fichiers et la base SQLite avant toute mise à jour.** Les sites en production ayant reçu des correctifs individuels ne doivent pas réinstaller P3 ni écraser leur base.

Sur LWS, **conserver le `.htaccess` adapté à l'hébergement**, les redirections canoniques, `var/sys`, `var/private`, `database`, `sessions`, `var/uploads`, les médias et les thèmes personnalisés. Vérifier la file FileZilla avant tout transfert.

L'archive complète est destinée à une installation neuve ou à une mise à jour contrôlée sur copie. Aucun transfert automatique n'est déclenché depuis GitHub.

## Tests de validation prévus

- PHP 8.2, 8.3, 8.4 et 8.5, permissions, migrations, templates, traduction et sauvegarde automatique.
- Navigation, HTTP et Chromium, y compris les droits du rédacteur et de l'administrateur.
- Reproductibilité du ZIP Articles et cohérence catalogue ↔ fichiers ↔ empreintes.
- Distribution sans bases de production, sessions, sauvegardes, configurations privées ni médias importés.
- Contrôle d'une installation neuve et de la conservation des données d'une installation existante.

La publication stable `0.9.2` et la fusion dans `main` demandent une autorisation explicite.
