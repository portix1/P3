# Download Manager 2.0.0 — chantier isolé

## État

Première implémentation technique `2.0.0-dev1`, **non activée par défaut**.
Le module historique `download` de P3 1.0.0 demeure intact ; il n'existe ni migration, ni compatibilité des URL, ni reprise des tables ou du stockage.

L'installation se fera uniquement dans une copie de test. Ne pas activer `download` et `download2` simultanément sur un site public sans revoir les menus.

## Architecture

- Préfixe public et administratif : `dl2` ; tables neuves `dm2_files` et `dm2_categories`.
- Stockage hors espace public : `var/private/download2` ; contrôles MIME, signature et taille de fichier via le service de stockage P3.
- Option « Afficher dans la bibliothèque publique » indépendante de l'état Publié, cochée par défaut et conservée au remplacement du fichier. Un fichier publié masqué reste accessible depuis son lien d'article et le sélecteur Quill. Les compteurs ne sont jamais publics.
- Catégories facultatives (création, édition, suppression des catégories vides) ; description facultative ; brouillon ou publié.
- Adresse de téléchargement fondée sur un identifiant immuable : `dl2-file-ID.html`. Un remplacement conserve l'identifiant.
- Route de téléchargement : refus des fichiers non publiés, de POST et des liens symboliques ; HEAD ne compte pas comme téléchargement.
- **Statistiques strictement administratives** : le compteur ne figure ni dans les données de liste publique, ni dans le catalogue JSON, ni dans son template.\n- Intégration à Quill : bouton « Insérer un téléchargement », recherche dans les fichiers publiés, insertion d’un lien `dl2-file-ID.html`. Le sélecteur ne se charge que si le nouveau module est activé.
- Accès administratif : rôle administrateur, vérification POST + jeton CSRF.

## Travail restant avant une version installable

- Tests HTTP bout à bout des transferts et du back-office sur installation isolée.
- Sélecteur Quill et bloc de téléchargement ancré à l'identifiant du fichier.
- Recette de l’interface de gestion des catégories et de son accessibilité.
- Traitement des erreurs de téléchargement, des fichiers absents et des interruptions d'envoi.
- Contrôle de disponibilité des fichiers et sauvegarde/restauration dans les essais.
- Validation de l'installation et de la désinstallation à partir d'une base vierge.

Aucune activation en production ni fusion dans `main` avant ces validations.
