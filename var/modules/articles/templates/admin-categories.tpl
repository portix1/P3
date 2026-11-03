<header class="page-heading">
    <p class="eyebrow">{{ articles_admin_title }}</p>
    <h1>{{ articles_categories }}</h1>
    <p>{{ articles_categories_help }}</p>
</header>

<section class="news-admin-summary">
    <a class="button button-quiet" href="Admin-articles.html">{{ articles_back_to_admin }}</a>
    {% if articles_can_configure_menu == "1" %}<a class="button button-quiet" href="Admin-articles-menu.html">{{ articles_manage_menu }}</a>{% endif %}
    <a class="button button-quiet" href="Admin-articles-order.html">{{ articles_manage_order }}</a>
    <a class="button" href="Admin-articles-category-new.html">{{ articles_category_new }}</a>
</section>

<section class="admin-section news-admin-list">
    <div class="news-admin-header" aria-hidden="true">
        <span>{{ articles_category_name }}</span><span>{{ articles_category_count }}</span><span>{{ ui_actions }}</span>
    </div>
    {% for categoryrow in categoryrow %}
    <article class="news-admin-row">
        <div>
            <h2>{{ categoryrow.name }}</h2>
            <p><code>articles-categorie-{{ categoryrow.slug }}.html</code> · <span class="{{ categoryrow.visibility_class }}">{{ categoryrow.visibility }}</span></p>
        </div>
        <div>{{ categoryrow.count }}</div>
        <div class="news-row-actions">
            <a class="button button-quiet" href="Admin-articles-category-edit-{{ categoryrow.id }}.html">{{ ui_edit }}</a>
            <a class="button button-quiet" href="articles-categorie-{{ categoryrow.slug }}.html">{{ ui_view }}</a>
            {% if categoryrow.can_delete == "1" %}
            <form method="post" action="Admin-articles-category-delete-{{ categoryrow.id }}.html" class="inline-action" data-confirm-delete data-confirm-title="{{ categoryrow.name }}">
                <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
                <button class="button-danger" type="submit">{{ ui_delete }}</button>
            </form>
            {% endif %}
        </div>
    </article>
    {% endfor %}
</section>

<dialog class="confirm-dialog" id="confirm-delete-dialog" aria-labelledby="articles-delete-category-dialog-title">
    <form method="dialog">
        <h2 id="articles-delete-category-dialog-title">{{ articles_category_delete_title }}</h2>
        <p>{{ articles_category_delete_description }} « <strong data-confirm-title></strong> ».</p>
        <div class="form-actions dialog-actions">
            <button class="button button-quiet" value="cancel">{{ ui_cancel }}</button>
            <button class="button-danger" value="confirm">{{ articles_delete_confirm }}</button>
        </div>
    </form>
</dialog>
