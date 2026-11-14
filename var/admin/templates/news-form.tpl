<header class="page-heading">
    <p class="eyebrow">{{ ui_news }}</p>
    <h1>{{ news_form_title }}</h1>
    <p>{{ news_admin_form_help }}</p>
</header>

{% if news_message %}<p class="status-message" role="status">{{ news_message }}</p>{% endif %}

<form method="post" action="{{ news_form_action }}" class="news-editor-form" data-unsaved-warning="1">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <div class="news-form-grid">
        <label>{{ news_admin_title }}
            <input type="text" name="title" value="{{ news_title }}" maxlength="250" required />
        </label>
        <label>{{ news_admin_public_url }}
            <span class="slug-field"><span>/</span><input type="text" name="slug" value="{{ news_slug }}" maxlength="180" placeholder="{{ news_admin_slug_placeholder }}" /><span>.html</span></span>
            <small>{{ news_admin_slug_help }}</small>
        </label>
        <label>{{ news_admin_status }}
            <select name="status">
                <option value="draft" {{ news_draft_selected }}>{{ news_admin_draft }}</option>
                {% if news_can_publish == "1" %}<option value="published" {{ news_published_selected }}>{{ news_admin_published }}</option>{% endif %}
            </select>
        </label>
        {% if news_can_pin == "1" %}<label class="news-pin-option">
            <span>{{ news_admin_pin }}</span>
            <span><input type="checkbox" name="is_pinned" value="1" {{ news_pinned_checked }} /> {{ news_admin_pin_help }}</span>
        </label>{% endif %}
    </div>

    <label for="news-intro">{{ news_admin_intro }}</label>
    <p class="portix-editor-help" id="news-intro-help">{{ news_admin_intro_help }}</p>
    <div class="portix-editor portix-editor--compact" data-portix-editor="1" data-editor-label="{{ news_admin_intro }}" data-upload-url="Admin-news-upload-image.html">
        <textarea class="portix-editor-source" id="news-intro" name="intro" rows="7" aria-describedby="news-intro-help">{{ news_intro }}</textarea>
        <div class="portix-editor-surface"></div>
    </div>

    <label for="news-body">{{ news_admin_body }}</label>
    <p class="portix-editor-help" id="news-body-help">{{ news_admin_body_help }}</p>
    <div class="portix-editor" data-portix-editor="1" data-editor-label="{{ news_admin_body }}" data-upload-url="Admin-news-upload-image.html">
        <textarea class="portix-editor-source" id="news-body" name="content" rows="18" aria-describedby="news-body-help">{{ news_content }}</textarea>
        <div class="portix-editor-surface"></div>
    </div>

    <div class="form-actions news-form-actions">
        <button type="submit">{{ ui_save }}</button>
        <button class="button button-quiet" type="submit" formaction="{{ news_preview_action }}" formtarget="_blank" formnovalidate data-preview-submit="1">{{ ui_preview }}</button>
        <a class="button button-quiet" href="Admin-news.html">{{ ui_cancel }}</a>
    </div>
</form>
