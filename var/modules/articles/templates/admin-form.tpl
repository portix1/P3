<header class="page-heading">
    <p class="eyebrow">{{ articles_admin_title }}</p>
    <h1>{{ articles_form_title }}</h1>
    <p>{{ articles_form_help }}</p>
</header>

{% if articles_message %}<p class="status-message" role="status">{{ articles_message }}</p>{% endif %}
{% if review_is_pending == "1" %}<p class="review-banner" role="status">{{ articles_review_pending_edit_notice }}</p>{% endif %}
{% if review_note_value %}<p class="review-note-banner">{{ articles_review_returned }} : {{ review_note_value }}</p>{% endif %}

<form method="post" action="{{ articles_form_action }}" class="news-editor-form" data-unsaved-warning="1">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <div class="news-form-grid">
        <label>{{ articles_field_title }}
            <input type="text" name="title" value="{{ articles_title_value }}" maxlength="250" required />
        </label>
        <label>{{ articles_field_url }}
            <span class="slug-field"><span>/</span><input type="text" name="slug" value="{{ articles_slug_value }}" maxlength="180" placeholder="{{ articles_slug_placeholder }}" /><span>.html</span></span>
            <small>{{ articles_slug_help }}</small>
        </label>
        <label>{{ articles_field_status }}
            <select name="status">
                <option value="draft" {{ articles_draft_selected }}>{{ articles_status_draft }}</option>
                
                {% if articles_can_publish == "1" %}<option value="published" {{ articles_published_selected }}>{{ articles_status_published }}</option>{% endif %}
            </select>
        </label>
    </div>
    {% if review_required == "1" %}<p class="field-help">{{ articles_review_next_step }}</p>{% endif %}

    <label>{{ articles_field_category }}
        <select name="category_id">
            {% for categoryoption in categoryoption %}
            <option value="{{ categoryoption.id }}" {{ categoryoption.selected }}>{{ categoryoption.name }}</option>
            {% endfor %}
        </select>
    </label>

    <label for="article-body">{{ articles_field_body }}</label>
    <p class="portix-editor-help" id="article-body-help">{{ articles_body_help }}</p>
    <div class="portix-editor" data-portix-editor="1" data-editor-label="{{ articles_field_body }}" data-upload-url="Admin-articles-upload-image.html">
        <textarea class="portix-editor-source" id="article-body" name="content" rows="18" aria-describedby="article-body-help">{{ articles_body_value }}</textarea>
        <div class="portix-editor-surface"></div>
    </div>

    <label for="article-summary">{{ articles_field_summary }}</label>
    <textarea id="article-summary" name="summary" rows="3" maxlength="170">{{ articles_summary_value }}</textarea>
    <small>{{ articles_field_summary_help }}</small>

    <fieldset>
        <legend>{{ articles_reading_settings }}</legend>
        <input type="hidden" name="reading_settings_present" value="1">
        <label><input type="checkbox" name="toc_enabled" value="1" {{ article_toc_checked }}>{{ articles_heading_toc_enable }}</label>
        <details><summary>{{ articles_related }}</summary>
            <p>{{ articles_related_help }}</p>
            <p class="article-picker-hint">{{ articles_picker_related_order }}</p>
            <div class="article-picker" data-article-picker>
            <div class="article-picker-controls" data-picker-controls hidden>
<label>{{ articles_picker_search }}<input type="search" data-picker-search autocomplete="off" placeholder="{{ articles_picker_search_hint }}"></label>
<label>{{ articles_picker_category }}<select data-picker-category-filter><option value="">{{ articles_picker_all_categories }}</option></select></label>
<p data-picker-count role="status" aria-live="polite" data-format="{{ articles_picker_count }}"></p>
</div>
            <ol data-series-order>{% for relationoption in relationoption %}<li data-picker-row data-picker-title="{{ relationoption.title }}" data-picker-category="{{ relationoption.category }}" data-picker-category-id="{{ relationoption.category_id }}">
                <label><input type="checkbox" name="related[]" value="{{ relationoption.id }}" {{ relationoption.checked }}>{{ relationoption.title }} <small>{{ relationoption.category }} · {{ relationoption.status }}</small></label>
                <span class="article-picker-order" data-picker-order>
                <label>{{ articles_series_position }}<input type="number" name="related_positions[{{ relationoption.id }}]" min="0" value="{{ relationoption.position }}"></label>
                <button type="button" data-series-move="up" title="{{ articles_series_up }}" aria-label="{{ articles_series_up }}">↑</button>
                <button type="button" data-series-move="down" title="{{ articles_series_down }}" aria-label="{{ articles_series_down }}">↓</button>
                </span>
            </li>{% endfor %}</ol>
            <button type="button" data-picker-more hidden>{{ articles_picker_more }}</button>
            </div>
        </details>
    </fieldset>
    <link rel="stylesheet" href="var/modules/articles/article-picker.css?v={{ articles_asset_version }}">
    <script src="var/modules/articles/article-picker.js?v={{ articles_asset_version }}" defer></script>
    <script src="var/modules/articles/series-order.js?v={{ articles_asset_version }}" defer></script>

    <details class="article-optional-metadata">
        <summary>{{ articles_optional_metadata }}</summary>
        <p class="field-help">{{ articles_optional_metadata_help }}</p>
    <div class="news-form-grid">
        <label>{{ articles_field_author }}
            <input type="text" name="author_name" value="{{ articles_author_value }}" maxlength="160" />
            <small>{{ articles_field_author_help }}</small>
        </label>
        <label class="form-grid-wide">{{ articles_field_featured_image }}
            <input type="text" name="featured_image" value="{{ articles_featured_image_value }}" maxlength="500" placeholder="var/uploads/images/image.webp" />
            <small>{{ articles_field_featured_image_help }}</small>
        </label>
    </div>

    </details>

    <div class="form-actions news-form-actions">
        <button type="submit">{% if review_required == "1" %}{{ articles_save_draft }}{% else %}{{ ui_save }}{% endif %}</button>
        {% if articles_is_edit == "1" %}<button class="button button-quiet" type="submit" name="save_continue" value="1">{{ articles_save_continue }}</button>{% endif %}
        <button class="button button-quiet" type="submit" formaction="{{ articles_preview_action }}" formtarget="_blank" formnovalidate data-preview-submit="1">{{ ui_preview }}</button>
        <a class="button button-quiet" href="Admin-articles.html">{{ ui_cancel }}</a>
    </div>
</form>
