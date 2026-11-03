# Feuille de route de P3

## Première version distribuable

Le paquet de base doit permettre d’installer P3 sur un serveur vierge avec le moteur, les composants indispensables et un thème neutre par défaut.

- [x] Valider la suite de tests sur PHP 8.2, 8.3 et 8.4.
- [x] Effectuer un premier essai d’installation neuve. Cet essai a révélé l’ancien titre proposé et le nom SQLite imposé.
- [ ] Intégrer le titre P3 dans l’installateur après validation de la PR nº 2.
- [ ] Permettre de générer ou de saisir le nom de la base SQLite à l’installation. Enregistrer le nom validé dans la configuration et le résoudre de manière cohérente dans le moteur et les outils de maintenance. Conserver le chemin historique pour les installations déjà configurées.
- [ ] Refaire une installation neuve depuis le paquet, puis vérifier la connexion administrateur, la publication d’un article et son affichage public.
- [ ] Tester une mise à jour sur une copie existante, avec contrôle de la conservation des données et de la restauration.
- [ ] Vérifier les ressources, licences et fichiers inclus dans l’archive avant publication.

## Distribution des extensions

1. Préparer le catalogue des versions et les archives vérifiées du moteur, des modules et des thèmes.
2. Préparer le lanceur PHP qui permet de choisir les composants et de les installer.
3. Ajouter ensuite la détection et l’installation des mises à jour depuis l’administration.

Le dépôt P3 sert à préparer et valider le CMS. Le déploiement sur Univers Guitare suit son propre cycle.
