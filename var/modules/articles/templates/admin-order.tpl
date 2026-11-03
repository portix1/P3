<header class="page-heading">
    <p class="eyebrow">{{ articles_admin_title }}</p>
    <h1>{{ articles_order_title }}</h1>
    <p>{{ articles_order_introduction }}</p>
</header>

{% if articles_order_message %}<p class="status-message" role="status">{{ articles_order_message }}</p>{% endif %}

<form method="post" action="Admin-articles-order.html" class="article-order-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    {{ articles_order_tree }}
    <div class="form-actions news-form-actions article-order-actions">
        <button type="submit">{{ articles_order_save }}</button>
        <a class="button button-quiet" href="Admin-articles.html">{{ ui_cancel }}</a>
    </div>
</form>

<script src="var/modules/articles/admin-order.js?v={{ asset_version }}" defer></script>
