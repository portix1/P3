<header class="page-heading">
    <p class="eyebrow">{{ search_admin_eyebrow }}</p>
    <h1>{{ search_admin_title }}</h1>
    <p>{{ search_admin_help }}</p>
</header>

{% if search_admin_message %}<p class="status-message" role="status">{{ search_admin_message }}</p>{% endif %}

<div class="module-list">
    <div class="module-row">
        <strong>{{ search_admin_mode_label }}</strong>
        <span>{{ search_admin_mode }}</span>
    </div>
    <div class="module-row">
        <strong>{{ search_admin_indexed_label }}</strong>
        <span>{{ search_admin_indexed }}</span>
    </div>
    <div class="module-row">
        <strong>{{ search_admin_articles_label }}</strong>
        <span>{{ search_admin_articles }}</span>
    </div>
    <div class="module-row">
        <strong>{{ search_admin_news_label }}</strong>
        <span>{{ search_admin_news }}</span>
    </div>
    <div class="module-row">
        <strong>{{ search_admin_last_sync_label }}</strong>
        <span>{{ search_admin_last_sync }}</span>
    </div>
</div>

<form method="post" action="Admin-search.html">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <input type="hidden" name="search_action" value="reindex" />
    <button type="submit">{{ search_admin_reindex }}</button>
</form>
