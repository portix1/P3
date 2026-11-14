<header class="page-heading"><h1 data-admin-icon="images">Médiathèque</h1><p>Images, PDF et ZIP réutilisables dans les articles et les actualités.</p></header>
<link rel="stylesheet" href="var/modules/media/assets/media.css?v=0.1.2" />
<section data-media-manager data-media-admin="{{ media_admin }}">
    <div class="media-actions">
        <label>Rechercher <input type="search" data-media-search maxlength="200" /></label>
        <label>Type <select data-media-family><option value="">Tous</option><option value="image">Images</option><option value="pdf">PDF</option><option value="zip">ZIP</option></select></label>
        <button type="button" data-media-add title="Ajouter un fichier">Ajouter un fichier</button>
        {% if media_admin %}
        <button type="button" data-media-inventory title="Répertorier les médias existants sans les déplacer">Répertorier les fichiers existants</button>
        <button type="button" data-media-alt-audit title="Examiner les textes alternatifs des images dans les articles, sans modifier les contenus">Auditer les descriptions des images</button>
        <button type="button" data-media-analyse title="Analyser les usages et afficher les candidats à la suppression">Analyser les inutilisés</button>
        <button type="button" data-media-webp title="Convertir les PNG sans perte en conservant les originaux">Migrer les PNG vers WebP</button>
        <button type="button" data-media-recover title="Reprendre une opération de remplacement interrompue">Récupérer une opération interrompue</button>
        {% endif %}
    </div>
    <p data-media-status role="status" aria-live="polite"></p>
    <section class="media-alt-audit" data-media-alt-results hidden aria-label="Images d’articles à décrire">
        <h2>Descriptions des images dans les articles</h2>
        <p data-media-alt-summary role="status"></p>
        <p>Une légende n’est pas automatiquement un texte alternatif. Les images sans description explicite restent à examiner ; aucune correction n’est appliquée par cet audit.</p>
        <div data-media-alt-list></div>
    </section>
    <div class="media-grid" data-media-grid></div>
    <p>Les médias utilisés actuellement sont protégés. L’analyse ne supprime rien. Les usages uniquement historiques exigent une confirmation spécifique. Les 300 résultats les plus récents sont affichés : utilisez la recherche pour affiner.</p>
</section>
<script src="var/modules/media/assets/media.js?v=0.1.3" defer></script>
