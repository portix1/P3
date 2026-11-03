<header class="page-heading">
    <p class="eyebrow">{{ articles_admin_title }}</p>
    <h1>{{ articles_menu_settings }}</h1>
    <p>{{ articles_menu_introduction }}</p>
</header>

{% if articles_menu_message %}<p class="status-message" role="status">{{ articles_menu_message }}</p>{% endif %}

<form method="post" action="Admin-articles-menu.html" class="settings-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <fieldset class="settings-group">
        <legend>{{ articles_menu_header_title }}</legend>
        <label class="checkbox-label">
            <input type="checkbox" name="header_enabled" value="1" {{ articles_menu_header_checked }} />
            <span><strong>{{ articles_menu_header_enabled }}</strong><small>{{ articles_menu_header_help }}</small></span>
        </label>
        <label class="checkbox-label">
            <input type="checkbox" name="show_articles" value="1" {{ articles_menu_articles_checked }} />
            <span><strong>{{ articles_menu_show_articles }}</strong><small>{{ articles_menu_show_articles_help }}</small></span>
        </label>
    </fieldset>
    <fieldset class="settings-group">
        <legend>{{ articles_menu_navigation_title }}</legend>
        <label>{{ articles_menu_navigation_label }}
            <select name="navigation_mode">
                <option value="off" {{ articles_menu_navigation_off_selected }}>{{ articles_menu_navigation_off }}</option>
                <option value="category" {{ articles_menu_navigation_category_selected }}>{{ articles_menu_navigation_category }}</option>
                <option value="book" {{ articles_menu_navigation_book_selected }}>{{ articles_menu_navigation_book }}</option>
            </select>
            <small>{{ articles_menu_navigation_help }}</small>
        </label>
    </fieldset>
    <div class="form-actions news-form-actions">
        <button type="submit">{{ ui_save }}</button>
        <a class="button button-quiet" href="Admin-articles-order.html">{{ articles_manage_order }}</a>
        <a class="button button-quiet" href="Admin-articles.html">{{ ui_cancel }}</a>
    </div>
</form>
