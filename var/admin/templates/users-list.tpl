<header class="page-heading">
    <p class="eyebrow">{{ users_accounts }}</p>
    <h1>{{ users_management }}</h1>
    <p>{{ users_management_help }}</p>
</header>

{% if users_message %}
<p class="status-message" role="status">{{ users_message }}</p>
{% endif %}

<section class="user-metrics" aria-label="{{ users_overview }}">
    <a class="metric-card user-filter-card {{ users_filter_all_current }}" href="Admin-users.html"{% if users_filter_all_active == "1" %} aria-current="page"{% endif %}>
        <span class="metric-value">{{ users_total }}</span>
        <h2>{{ users_total_label }}</h2>
        <span>{{ users_filter_show }}</span>
    </a>
    <a class="metric-card user-filter-card {{ users_filter_admin_current }}" href="Admin-users.html?role=admin"{% if users_filter_admin_active == "1" %} aria-current="page"{% endif %}>
        <span class="metric-value">{{ users_admin_count }}</span>
        <h2>{{ users_administrators }}</h2>
        <span>{{ users_filter_show }}</span>
    </a>
    <a class="metric-card user-filter-card {{ users_filter_redactor_current }}" href="Admin-users.html?role=redactor"{% if users_filter_redactor_active == "1" %} aria-current="page"{% endif %}>
        <span class="metric-value">{{ users_redactor_count }}</span>
        <h2>{{ users_redactors }}</h2>
        <span>{{ users_filter_show }}</span>
    </a>
</section>

<section class="user-toolbar admin-section">
    <form method="get" action="Admin-users.html" class="user-search-form" role="search">
        <input type="hidden" name="role" value="{{ users_role_filter }}" />
        <label for="users-search"><span>{{ users_search }}</span>
            <input id="users-search" type="search" name="q" value="{{ users_query }}" maxlength="100" placeholder="{{ users_search_placeholder }}" />
        </label>
        <button type="submit" class="button-quiet">{{ users_search_button }}</button>
        {% if users_has_query == "1" %}<a class="button button-quiet" href="{{ users_clear_search_url }}">{{ users_clear_search }}</a>{% endif %}
    </form>
    <a class="button" href="Admin-users-new.html">{{ users_create_account }}</a>
</section>

<section class="admin-section user-admin-list" aria-label="{{ users_account_list }}">
    <div class="user-list-heading">
        <h2>{{ users_list_title }}</h2>
        <span>{{ users_manage_hint }}</span>
    </div>
{% if users_has_items == "0" %}
    <div class="user-empty-state">
        <h2>{{ users_no_results }}</h2>
        <p>{{ users_no_results_help }}</p>
    </div>
{% endif %}
{% if users_has_items == "1" %}
    <div class="user-admin-header" aria-hidden="true">
        <span>{{ users_identity }}</span>
        <span>{{ users_role }}</span>
        <span>{{ ui_actions }}</span>
    </div>
    {% for userrow in userrow %}
    <article class="user-admin-row">
        <a class="user-identity user-identity-link" href="Admin-users-edit-{{ userrow.pseudo_url }}.html">
            <span class="user-avatar" aria-hidden="true">{{ userrow.initials }}</span>
            <span class="user-identity-copy">
                <strong>{{ userrow.display_name }}</strong>
                <span>@{{ userrow.pseudo }}{% if userrow.is_self == "1" %} · {{ users_you }}{% endif %}</span>
                <small>{{ userrow.email }}</small>
            </span>
        </a>
        <div class="user-role-badges">
            {% if userrow.is_admin == "1" %}<span class="role-badge role-admin">{{ users_role_admin }}</span>{% endif %}
            {% if userrow.is_redactor == "1" %}<span class="role-badge role-redactor">{{ users_role_redactor }}</span>{% endif %}
        </div>
        <div class="user-row-actions">
            <a class="button" href="Admin-users-edit-{{ userrow.pseudo_url }}.html">{{ users_manage_account }}</a>
            {% if userrow.can_delete == "1" %}
            <form method="post" action="Admin-users-delete-{{ userrow.pseudo_url }}.html" class="inline-action" data-confirm-delete data-confirm-title="{{ userrow.pseudo }}" data-confirm-prefix="{{ users_delete_prefix }}" data-confirm-suffix="{{ users_delete_suffix }}">
                <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
                <button type="submit" class="button-danger">{{ ui_delete }}</button>
            </form>
            {% endif %}
        </div>
    </article>
    {% endfor %}
{% endif %}
</section>

<dialog class="confirm-dialog" id="confirm-delete-dialog" aria-labelledby="users-delete-dialog-title">
    <form method="dialog">
        <h2 id="users-delete-dialog-title">{{ users_delete_title }}</h2>
        <p>{{ users_delete_confirmation }} <strong data-confirm-title>{{ users_account }}</strong>.</p>
        <p>{{ users_delete_warning }}</p>
        <div class="dialog-actions">
            <button value="cancel" class="button-quiet">{{ ui_cancel }}</button>
            <button value="confirm" class="button-danger">{{ ui_delete }}</button>
        </div>
    </form>
</dialog>
