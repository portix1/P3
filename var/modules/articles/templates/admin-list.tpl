{% for tocbatchnotice in tocbatchnotice %}<p class="status-message" role="status">{{ tocbatchnotice.message }}</p>{% endfor %}
<header class="page-heading">
    <p class="eyebrow">{{ ui_content }}</p>
    <h1>{{ articles_admin_title }}</h1>
    <p>{{ articles_admin_introduction }}</p>
</header>

<section class="news-admin-summary" aria-label="{{ articles_admin_summary }}">
    <p><strong>{{ articles_total }}</strong> {{ articles_admin_total }}</p>
    <p><strong>{{ articles_published }}</strong> {{ articles_admin_published_total }}</p>
    <p><strong>{{ articles_pending }}</strong> {{ articles_admin_pending_total }}</p>
    <div class="news-admin-actions">
        {% if articles_can_configure_menu == "1" %}<a class="button button-quiet" href="Admin-articles-series.html">{{ articles_series }}</a>{% endif %}
        {% if articles_can_configure_menu == "1" %}<a class="button button-quiet" href="Admin-articles-menu.html">{{ articles_manage_menu }}</a>{% endif %}
        {% if articles_can_configure_menu == "1" %}<a class="button button-quiet" href="Admin-articles-order.html">{{ articles_manage_order }}</a>{% endif %}
        {% if articles_can_configure_menu == "1" %}<a class="button button-quiet" href="Admin-articles-categories.html">{{ articles_manage_categories }}</a>{% endif %}
        {% if articles_can_create == "1" %}<a class="button" href="Admin-articles-new.html">{{ articles_new }}</a>{% endif %}
    </div>
</section>

<section class="admin-section news-admin-list">
    <div class="news-admin-header" aria-hidden="true">
        <span>{{ articles_admin_title }}</span><span>{{ ui_state }}</span><span>{{ ui_actions }}</span>
    </div>
    {% for articlerow in articlerow %}
    <article class="news-admin-row">
        <div>
            <h2>{{ articlerow.title }}</h2>
            <p>{{ articlerow.category }} · <code>{{ articlerow.slug }}.html</code></p>
            <p class="news-internal-meta">{{ articles_last_update }} {{ articlerow.date }}</p>
        </div>
        <div><span class="{{ articlerow.status_class }}">{{ articlerow.status }}</span>{% if articlerow.review_note %}<p class="review-note">{{ articles_review_returned }} : {{ articlerow.review_note }}</p>{% endif %}</div>
        <div class="news-row-actions article-row-actions">
            {% if articlerow.can_publish == "1" %}<form method="post" action="Admin-articles-status-{{ articlerow.id }}.html" class="inline-action article-action-slot">
                <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
                <input type="hidden" name="status" value="{{ articlerow.next_status }}" />
                <button class="button {{ articlerow.status_action_class }}" type="submit">{{ articlerow.status_action }}</button>
            </form>{% endif %}
            {% if articlerow.can_send_back == "1" %}<form method="post" action="Admin-articles-status-{{ articlerow.id }}.html" class="article-review-form">
                <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
                <input type="hidden" name="status" value="draft" />
                <label>{{ articles_review_note_label }}
                    <textarea name="review_note" rows="2" maxlength="2000" required></textarea>
                </label>
                <button class="button button-quiet button-review" type="submit">{{ articles_review_send_back }}</button>
            </form>{% endif %}
            <span class="article-action-slot"><a class="button button-quiet" href="Admin-articles-edit-{{ articlerow.id }}.html">{{ ui_edit }}</a></span>
            <span class="article-action-slot">{% if articlerow.public_url %}<a class="button button-quiet" href="{{ articlerow.public_url }}">{{ ui_view }}</a>{% endif %}{% if articlerow.public_url == "" %}<form method="post" action="Admin-articles-preview-{{ articlerow.id }}.html" class="inline-action" target="_blank"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><button class="button button-quiet" type="submit">{{ ui_preview }}</button></form>{% endif %}</span>
            <span class="article-action-slot">{% if articlerow.can_delete == "1" %}
            <form method="post" action="Admin-articles-delete-{{ articlerow.id }}.html" class="inline-action" data-confirm-delete data-confirm-title="{{ articlerow.title }}">
                <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
                <button class="button-danger" type="submit">{{ ui_delete }}</button>
            </form>
            {% endif %}</span>
        </div>
    </article>
    {% endfor %}
</section>
{% if articles_has_rows == "0" %}<p class="empty-state">{{ articles_empty_help }}</p>{% endif %}

<dialog class="confirm-dialog" id="confirm-delete-dialog" aria-labelledby="articles-delete-dialog-title">
    <form method="dialog">
        <h2 id="articles-delete-dialog-title">{{ articles_delete_title }}</h2>
        <p>{{ articles_delete_description }} « <strong data-confirm-title></strong> ».</p>
        <div class="form-actions dialog-actions">
            <button class="button button-quiet" value="cancel">{{ ui_cancel }}</button>
            <button class="button-danger" value="confirm">{{ articles_delete_confirm }}</button>
        </div>
    </form>
</dialog>
