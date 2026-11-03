<header class="page-heading">
    <p class="eyebrow">{{ ui_content }}</p>
    <h1>{{ ui_news }}</h1>
    <p>{{ news_admin_introduction }}</p>
</header>

<section class="news-admin-summary" aria-label="{{ news_admin_summary }}">
    <p><strong>{{ news_total }}</strong> {{ news_admin_total }}</p>
    <p><strong>{{ news_published }}</strong> {{ news_admin_published_total }}</p>
    <a class="button" href="Admin-news-new.html">{{ news_admin_new }}</a>
</section>

<section class="admin-section news-admin-list">
    <div class="news-admin-header" aria-hidden="true">
        <span>{{ ui_news }}</span><span>{{ ui_state }}</span><span>{{ ui_actions }}</span>
    </div>
    {% for newsrow in newsrow %}
    <article class="news-admin-row">
        <div>
            <h2>{{ newsrow.title }}{% if newsrow.is_pinned == "1" %} <span class="state-enabled">{{ newsrow.pin_label }}</span>{% endif %}</h2>
            <p><code>{{ newsrow.slug }}.html</code> · {{ news_admin_last_activity }} {{ newsrow.date }}</p>
            <p class="news-internal-meta">{{ news_admin_created_by }} {{ newsrow.created_by }}</p>
        </div>
        <div><span class="{{ newsrow.status_class }}">{{ newsrow.status }}</span></div>
        <div class="news-row-actions">
            <a class="button button-quiet" href="Admin-news-edit-{{ newsrow.id }}.html">{{ ui_edit }}</a>
            {% if newsrow.public_url %}<a class="button button-quiet" href="{{ newsrow.public_url }}">{{ ui_view }}</a>{% endif %}
            {% if newsrow.public_url == "" %}<form method="post" action="Admin-news-preview-{{ newsrow.id }}.html" class="inline-action" target="_blank"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><button class="button button-quiet" type="submit">{{ ui_preview }}</button></form>{% endif %}
            {% if newsrow.can_delete == "1" %}
            <form method="post" action="Admin-news-delete-{{ newsrow.id }}.html" class="inline-action" data-confirm-delete data-confirm-title="{{ newsrow.title }}">
                <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
                <button class="button-danger" type="submit">{{ ui_delete }}</button>
            </form>
            {% endif %}
        </div>
    </article>
    {% endfor %}
</section>

<dialog class="confirm-dialog" id="confirm-delete-dialog" aria-labelledby="news-delete-dialog-title">
    <form method="dialog">
        <h2 id="news-delete-dialog-title">{{ news_admin_delete_title }}</h2>
        <p>{{ news_admin_delete_description }} « <strong data-confirm-title></strong> ».</p>
        <div class="form-actions dialog-actions">
            <button class="button button-quiet" value="cancel">{{ ui_cancel }}</button>
            <button class="button-danger" value="confirm">{{ news_admin_delete_confirm }}</button>
        </div>
    </form>
</dialog>
