<header class="page-heading">
    <p class="eyebrow">{{ menu_public_navigation }}</p>
    <h1>{{ menu_management }}</h1>
    <p>{{ menu_management_help }}</p>
</header>

{% if menu_message %}
<p class="status-message" role="status">{{ menu_message }}</p>
{% endif %}

<form method="post" action="Admin-menu.html" class="menu-settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="menu_action" value="save" />

<section class="admin-section menu-editor-section">
    <div class="section-heading-row">
        <div>
            <h2>{{ menu_displayed_links }}</h2>
            <p>{{ menu_order_help }}</p>
        </div>
        <span class="drag-help">{{ menu_drag_drop }}</span>
    </div>

    <div class="sortable-menu" data-sortable-menu>
    {% for simplemenu in simplemenu %}
        <article class="menu-item" draggable="true" data-menu-item>
            <input type="hidden" name="order[]" value="{{ simplemenu.name }}" data-order-input />
            <input type="hidden" name="name[]" value="{{ simplemenu.name }}" />
            <div class="menu-drag-column">
                <button type="button" class="drag-handle" aria-label="{{ menu_move }} {{ simplemenu.name }}" title="{{ menu_drag }}">☰</button>
                <span class="menu-position" data-position>{{ simplemenu.position }}</span>
                <div class="menu-move-buttons">
                    <button type="button" class="move-button" data-move="up" aria-label="{{ menu_move_up }} {{ simplemenu.name }}">↑</button>
                    <button type="button" class="move-button" data-move="down" aria-label="{{ menu_move_down }} {{ simplemenu.name }}">↓</button>
                </div>
            </div>
            <div class="menu-fields">
                <strong>{{ simplemenu.name }}</strong>
                <label><span>{{ menu_url }}</span><input type="text" name="url[]" value="{{ simplemenu.url }}" placeholder="{{ menu_url_auto_placeholder }}" /></label>
                <label><span>{{ menu_description }}</span><input type="text" name="description[]" value="{{ simplemenu.description }}" /></label>
            </div>
            <label class="delete-menu-item"><input type="checkbox" name="delete[]" value="{{ simplemenu.name }}" /> {{ ui_delete }}</label>
        </article>
    {% endfor %}
    </div>
</section>

<section class="admin-section">
    <h2>{{ menu_add_link }}</h2>
    <div class="new-menu-grid">
        <label><span>{{ menu_name }}</span><input type="text" name="newname" maxlength="100" /></label>
        <label><span>{{ menu_url }}</span><input type="text" name="newurl" placeholder="{{ menu_url_auto_placeholder }}" /></label>
        <label><span>{{ menu_description }}</span><input type="text" name="newdescription" /></label>
    </div>
    <p class="field-help">{{ menu_external_help }}</p>
</section>

<div class="form-actions"><button type="submit">{{ menu_save }}</button></div>
</form>
<script src="var/modules/menu/admin-menu.js?v={{ asset_version }}" defer></script>
