<header class="page-heading">
    <p class="eyebrow">{{ ui_overview }}</p>
    <h1>{{ ui_dashboard }}</h1>
    <p>{{ dashboard_introduction }}</p>
</header>

<section class="dashboard-grid" aria-label="{{ dashboard_site_status }}">
    <article class="metric-card">
        <span class="metric-value">{{ admin_enabled_module_count }}</span>
        <h2>{{ dashboard_active_modules }}</h2>
        <p>{{ admin_module_count }} {{ dashboard_installed_modules }}</p>
        {% if admin_module_updates %}<p><a href="Admin-modmgr.html#module-list">{{ admin_module_updates }}</a></p>{% endif %}
    </article>
    <article class="metric-card">
        <span class="metric-value">{{ portix_version }}</span>
        <h2>{{ dashboard_portix_version }}</h2>
        <p>{{ dashboard_current_installation }}</p>
        {% if admin_core_update %}<p><a href="https://github.com/portix1/P3/releases/latest">{{ extension_updates_download }} {{ admin_core_update }}</a></p>{% endif %}
        {% if admin_core_status %}<p>{{ admin_core_status }}</p>{% endif %}
    </article>
    {% if admin_theme_updates %}
    <article class="metric-card">
        <h2>{{ themes_inventory_title }}</h2>
        <p><a href="Admin-config-themes.html#theme-inventory-title">{{ admin_theme_updates }}</a></p>
    </article>
    {% endif %}
</section>

{% if ux_has_writing == "1" %}
<section class="management-section management-content" id="ux-writing" aria-labelledby="ux-writing-title">
    <div class="management-heading">
        <h2 id="ux-writing-title">{{ ux_writing }}</h2>
        <p>{{ ux_writing_help }}</p>
    </div>
    <div class="management-grid">
        {% for item in ux_nav_writing %}
        <a class="management-card action-content" href="{{ item.url }}" data-admin-icon="{{ item.icon }}"><strong>{{ item.name }}</strong></a>
        {% endfor %}
    </div>
</section>
{% endif %}

{% if ux_has_management == "1" %}
<section class="management-section management-admin" id="ux-management" aria-labelledby="ux-management-title">
    <div class="management-heading">
        <h2 id="ux-management-title">{{ ux_management }}</h2>
        <p>{{ ux_management_help }}</p>
    </div>
    <div class="management-grid">
        {% for item in ux_nav_management %}
        <a class="management-card action-admin" href="{{ item.url }}" data-admin-icon="{{ item.icon }}"><strong>{{ item.name }}</strong></a>
        {% endfor %}
    </div>
</section>
{% endif %}

{% if ux_has_configuration == "1" %}
<section class="management-section management-admin" id="ux-configuration" aria-labelledby="ux-configuration-title">
    <div class="management-heading">
        <h2 id="ux-configuration-title">{{ ux_configuration }}</h2>
        <p>{{ ux_configuration_help }}</p>
    </div>
    <div class="management-grid">
        {% for item in ux_nav_configuration %}
        <a class="management-card action-admin" href="{{ item.url }}" data-admin-icon="{{ item.icon }}"><strong>{{ item.name }}</strong></a>
        {% endfor %}
    </div>
</section>
{% endif %}

<section class="dashboard-secondary-actions" aria-label="{{ dashboard_secondary_actions }}">
    <a href="personnal.html">{{ ui_my_account }}</a>
    <a href="{{ base_url }}/">{{ dashboard_open_public_site }}</a>
</section>
