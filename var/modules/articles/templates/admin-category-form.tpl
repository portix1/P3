<header class="page-heading">
    <p class="eyebrow">{{ articles_categories }}</p>
    <h1>{{ articles_category_form_title }}</h1>
    <p>{{ articles_category_form_help }}</p>
</header>

{% if articles_message %}<p class="status-message" role="status">{{ articles_message }}</p>{% endif %}

<form method="post" action="{{ articles_category_form_action }}" class="settings-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <div class="form-grid">
        <label>{{ articles_category_name }}
            <input type="text" name="name" value="{{ articles_category_name_value }}" maxlength="150" required />
        </label>
        <label>{{ articles_field_url }}
            <span class="slug-field"><span>/articles-categorie-</span><input type="text" name="slug" value="{{ articles_category_slug_value }}" maxlength="180" placeholder="{{ articles_slug_placeholder }}" /><span>.html</span></span>
        </label>
        <label>{{ articles_category_parent }}
            <select name="parent_id">
                {% for parentoption in parentoption %}
                <option value="{{ parentoption.id }}" {{ parentoption.selected }}>{{ parentoption.name }}</option>
                {% endfor %}
            </select>
        </label>
        <label class="checkbox-line"><input type="checkbox" name="is_visible" value="1" {{ articles_category_visible_checked }} /> <span>{{ articles_category_visible }}</span></label>
    </div>
    <p class="field-help">{{ articles_category_visible_help }}</p>
    <label for="category-description">{{ articles_category_description }}</label>
    <textarea id="category-description" name="description" rows="3" maxlength="170" aria-describedby="category-description-help">{{ articles_category_description_value }}</textarea>
    <small id="category-description-help">{{ articles_category_description_help }}</small>
    <div class="form-actions news-form-actions">
        <button type="submit">{{ ui_save }}</button>
        <a class="button button-quiet" href="Admin-articles-categories.html">{{ ui_cancel }}</a>
    </div>
</form>
