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

## Candidate de distribution — 13 novembre 2026

La branche isolée `release/0.9.2-rc1` fixe `VERSION=0.9.2-rc1` et le module Articles 1.4.9. Le ZIP du module et le catalogue sont reconstruits et committés ensemble au commit `7f5b7f66dc34055dae5f2f4f46575d2c7b723392`.

- Empreinte SHA-256 du ZIP Articles 1.4.9 : `704dc54b27e282e797569b25f5123e259fb22d1ac26d1c3d31edfacf672de83c`.
- Empreinte SHA-256 du catalogue candidat : `aaffc4dbeca57869fdd9510765b9218ebb044bffe4b727b28f82306df8e6dd8f`.
- L'ancrage de confiance de la candidate pointe sur ce commit exact. Le catalogue stable de 0.9.1 conserve sa propre référence.
- La CI est exécutée sur la branche candidate en mode **strict** : aucune exception de version réservée aux pull requests n'est nécessaire, puisque catalogue et sources correspondent.
- Un workflow de construction **en lecture seule** produit l'archive complète et son empreinte comme artefacts CI, sans publication GitHub Release ni déploiement.

La candidate reste distincte d'une version stable et de la production. La fusion dans `main` exige une autorisation explicite.

## Dernière étape — version stable préparée

La version `0.9.2` est préparée sur la branche isolée `release/0.9.2`. Le catalogue et le ZIP Articles sont reconstruits avec `<MinimumP3>0.9.2</MinimumP3>`, puis leur empreinte est fixée dans le noyau. Les tests sous PHP 8.2 à 8.5, le contrôle navigateur et l'archive complète sont relancés. Aucun transfert en production ni modification de `main` n'est autorisé implicitement.
