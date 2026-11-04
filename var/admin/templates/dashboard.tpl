<header class="page-heading"><h1>{{ ui_dashboard }}</h1><p>{{ ux_overview_help }}</p></header>
<section class="dashboard-grid" aria-label="{{ dashboard_site_status }}">
<article class="metric-card"><span class="metric-value">{{ portix_version }}</span><h2>{{ dashboard_portix_version }}</h2>
{% if admin_core_update %}<p><a href="https://github.com/portix1/P3/releases/latest">{{ extension_updates_download }} {{ admin_core_update }}</a></p>{% endif %}
{% if admin_core_status %}<p>{{ admin_core_status }}</p>{% endif %}</article>
<article class="metric-card"><span class="metric-value">{{ admin_enabled_module_count }}</span><h2>{{ dashboard_active_modules }}</h2><p>{{ admin_module_count }} {{ dashboard_installed_modules }}</p>
{% if admin_module_has_updates == "1" %}<p><a href="Admin-modmgr.html#module-list">{{ admin_module_updates }}</a></p>{% endif %}
{% if admin_module_has_updates == "0" %}<p>{{ admin_module_updates }}</p>{% endif %}</article>
<article class="metric-card"><span class="metric-value">{{ admin_public_theme }}</span><h2>{{ ux_active_theme }}</h2>
{% if admin_theme_has_updates == "1" %}<p><a href="Admin-config-themes.html#theme-inventory-title">{{ admin_theme_updates }}</a></p>{% endif %}
{% if admin_theme_has_updates == "0" %}<p>{{ admin_theme_updates }}</p>{% endif %}</article>
</section>
<nav class="management-grid" aria-label="{{ ux_navigation }}">
{% if ux_has_writing == "1" %}<a class="management-card action-content" href="Admin-writing.html" data-admin-icon="pencil"><strong>{{ ux_writing }}</strong><span>{{ ux_writing_help }}</span></a>{% endif %}
{% if ux_has_management == "1" %}<a class="management-card action-management" href="Admin-management.html" data-admin-icon="users"><strong>{{ ux_management }}</strong><span>{{ ux_management_help }}</span></a>{% endif %}
{% if ux_has_configuration == "1" %}<a class="management-card action-configuration" href="Admin-configuration.html" data-admin-icon="settings"><strong>{{ ux_configuration }}</strong><span>{{ ux_configuration_help }}</span></a>{% endif %}
</nav>
