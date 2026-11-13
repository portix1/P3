# Préparation de P3 0.9.2

**Statut : chantier de consolidation — ne pas publier, ne pas fusionner dans main sans approbation.**

## Base et périmètre

- Dernière version stable : **P3 0.9.1** sur `main`.
- Branche de préparation : `feature/relecture-articles-chatgpt-20261113`, proposition GitHub n° 34.
- Module Articles en développement : **1.4.9**. Le catalogue officiel reste à **1.4.8**, livré avec P3 0.9.1. Ne pas annoncer 1.4.9 comme publié.
- Déploiement : les correctifs incrémentaux du back-office ont été vérifiés sur Univers Guitare par le propriétaire, mais la validation d'une archive consolidée reste distincte.

## Fonctionnalités à intégrer

- Trois espaces séparés : Rédaction (contenu), Administration (opérations courantes), Configuration (réglages rares et structure).
- Permissions de publication au cas par cas : relecture obligatoire ou publication directe.
- Brouillons et demandes de relecture, approbation ou retour motivé avec contrôle des droits côté serveur.
- Liste : recherche, filtres, pagination de 25 articles, retour au contexte après édition.
- Formulaire de rédaction simplifié et sauvegarde automatique de récupération toutes les 30 secondes, sans changement de publication.

## Validations de préparation

- `php tools/validate_release.php` sous PHP **8.2, 8.3, 8.4 et 8.5**.
- Test HTTP/navigateur de l'accueil et de la séparation des droits : `.github/workflows/p3-ci.yml`.
- Contrôles de non-régression désormais compris dans le test général : `smoke_test_article_autosave.php`, `smoke_test_article_form_ux.php`, `smoke_test_article_list_context.php`.
- Pour les pull requests uniquement, `P3_DEVELOPMENT_VALIDATION=1` autorise un module Articles plus récent que le catalogue stable ; la publication sur `main` n'a pas cette exception et exige une archive identique aux sources.
- Vérifier une installation neuve et une mise à jour depuis 0.9.1 sur une copie, avec conservation des comptes, articles, médias, URL, réglages et droits.
- Vérifier en navigateur sur un compte rédacteur et administrateur : création, sauvegarde automatique, récupération, validation, filtrage et permissions.

## Avant de publier 0.9.2

1. Confirmer le périmètre et la version définitive d'Articles ; reconstruire le ZIP autonome de module avec son empreinte SHA-256.
2. Produire puis contrôler le catalogue d'extensions stable correspondant ; mettre à jour ensemble l'ancrage du catalogue et son empreinte. Ne pas remplacer le catalogue publié sans cette procédure.
3. Mettre à jour `VERSION` et `RELEASE-NOTES.md` uniquement lorsque le paquet est effectivement prêt.
4. Exécuter toute la CI, les tests de distribution, les tests HTTP et un test d'installation/mise à jour sur copie.
5. Vérifier que la distribution ne contient aucune base, session, sauvegarde, configuration privée ni média d'exploitation.
6. Obtenir une autorisation explicite avant toute fusion dans `main`, création de release ou opération sur la production.

## Nettoyage de la branche

Les huit workflows temporaires générant des ZIP séparés après chaque commit ont été retirés. Les commits et les correctifs déjà livrés restent dans l'historique GitHub. Utiliser désormais la **CI générale P3** pour le contrôle continu ; générer une archive consolidée seulement après validation du périmètre 0.9.2.

## Travaux distincts à ne pas confondre avec cette version

Les anomalies de lecture grand écran, de textes alternatifs et de métadonnées sociales du site Univers Guitare, ainsi que le durcissement des sessions ou la documentation nginx, restent à planifier séparément selon l'audit OpenCode du 13 novembre 2026.
