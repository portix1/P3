# Feuille de route de P3

## Première distribution autonome

Le paquet de base comprend le moteur, les composants indispensables et un thème neutre par défaut. Il doit permettre d’installer P3 sur un serveur vierge.

### Acquis

- [x] Suite automatisée validée sur PHP 8.2, 8.3 et 8.4.
- [x] Premier essai d’installation neuve. Il a révélé deux points corrigés depuis.
- [x] Titre P3 proposé par l’installateur ([PR nº 2](https://github.com/portix1/P3/pull/2)).
- [x] Nom SQLite saisi ou généré à l’installation. P3 conserve le chemin historique pour les sites existants ([PR nº 4](https://github.com/portix1/P3/pull/4)).

- [x] Archive candidate 0.8.1 assemblée depuis `a9376da` et validée après extraction sous PHP 8.4.
- [x] Installation de l’archive candidate confirmée sur le serveur de test.
- [x] Restauration testée sur une copie de l’archive avec un nom SQLite personnalisé. Articles, identifiants, URL, compte et configuration ont été comparés avant et après.

### Ordre des prochains travaux

1. **Préparer une archive candidate.** Définir les composants livrés et vérifier les ressources et leurs licences. Contrôler que l’archive ne contient ni base, ni configuration privée, ni médias d’une installation.
2. **Tester l’installation depuis cette archive.** Sur un serveur vierge, vérifier les prérequis, les permissions, le formulaire et les deux modes de nommage SQLite. Se connecter comme administrateur, publier un article et ouvrir sa page publique. Vérifier les guides fournis au fil de cet essai.
3. **Tester une mise à jour sur une copie existante.** Vérifier la conservation des contenus, comptes, réglages et URL. Tester la sauvegarde et la restauration, y compris avec un nom SQLite personnalisé.
4. **Préparer la publication.** Vérifier que l’archive testée correspond au code validé et consigner les conditions d’installation et de mise à jour.

## Distribution des extensions

Après validation du paquet de base :

1. Préparer le catalogue des versions et les archives vérifiées du moteur, des modules et des thèmes.
2. Préparer le lanceur PHP qui permet de choisir les composants et de les installer.
3. Ajouter la détection et l’installation des mises à jour depuis l’administration.

Le déploiement sur Univers Guitare suit son propre cycle.
