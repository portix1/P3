<header class="page-heading">
    <p class="eyebrow">{{ ui_overview }}</p>
    <h1>{{ ui_dashboard }}</h1>
    <p>{{ dashboard_introduction }}</p>
</header>

<section class="dashboard-grid" aria-label="{{ dashboard_site_status }}">
    {% if admin_news_enabled == "1" %}
    <article class="metric-card">
        <span class="metric-value">{{ admin_news_count }}</span>
        <h2>{{ dashboard_news_count }}</h2>
        <p>{{ admin_published_news_count }} {{ dashboard_published_count }}</p>
    </article>
    {% endif %}
    <article class="metric-card">
        <span class="metric-value">{{ admin_enabled_module_count }}</span>
        <h2>{{ dashboard_active_modules }}</h2>
        <p>{{ admin_module_count }} {{ dashboard_installed_modules }}</p>
    </article>
    <article class="metric-card">
        <span class="metric-value">{{ portix_version }}</span>
        <h2>{{ dashboard_portix_version }}</h2>
        <p>{{ dashboard_current_installation }}</p>
    </article>
</section>

<section class="management-section management-content" id="content-management">
    <div class="management-heading">
        <p class="eyebrow">{{ dashboard_content_eyebrow }}</p>
        <h2>{{ dashboard_content_management }}</h2>
        <p>{{ dashboard_content_management_help }}</p>
    </div>
    <div class="management-grid">
        {% if admin_news_enabled == "1" %}
        <a class="management-card action-content" href="Admin-news.html"><strong>{{ ui_news }}</strong><span>{{ dashboard_news_manage_help }}</span></a>
        {% endif %}
        {% for contentmodules in contentmodules %}
        <a class="management-card action-content" href="Admin-{{ contentmodules.prefix }}.html" data-admin-icon="{{ contentmodules.icon }}"><strong>{{ contentmodules.name }}</strong><span>{{ dashboard_content_module_help }}</span></a>
        {% endfor %}
        {% for admincontentmodules in admincontentmodules %}
        <a class="management-card action-content" href="Admin-{{ admincontentmodules.prefix }}.html" data-admin-icon="{{ admincontentmodules.icon }}"><strong>{{ admincontentmodules.name }}</strong><span>{{ dashboard_content_module_help }}</span></a>
        {% endfor %}
    </div>
</section>

{% if is_admin == "1" %}
<section class="management-section management-admin" id="administration">
    <div class="management-heading">
        <p class="eyebrow">{{ dashboard_admin_eyebrow }}</p>
        <h2>{{ dashboard_admin_management }}</h2>
        <p>{{ dashboard_admin_management_help }}</p>
    </div>
    <div class="management-grid">
        <a class="management-card action-admin" href="Admin-config.html"><strong>{{ ui_general_configuration }}</strong><span>{{ dashboard_configure_site_help }}</span></a>
        <a class="management-card action-admin" href="Admin-users.html"><strong>{{ ui_users }}</strong><span>{{ dashboard_manage_users_help }}</span></a>
        <a class="management-card action-admin" href="Admin-modmgr.html"><strong>{{ ui_module_management }}</strong><span>{{ dashboard_manage_modules_help }}</span></a>
        <a class="management-card action-admin" href="Admin-diagnostics.html" data-admin-icon="activity"><strong>{{ diagnostics_nav }}</strong><span>{{ dashboard_diagnostics_help }}</span></a>
        {% for modulesadmin in modulesadmin %}
        <a class="management-card action-admin" href="Admin-{{ modulesadmin.prefix }}.html" data-admin-icon="{{ modulesadmin.icon }}"><strong>{{ modulesadmin.name }}</strong><span>{{ dashboard_configuration_module_help }}</span></a>
        {% endfor %}
    </div>
</section>
{% endif %}

<section class="dashboard-secondary-actions" aria-label="{{ dashboard_secondary_actions }}">
    <a href="personnal.html">{{ ui_my_account }}</a>
    <a href="{{ base_url }}/">{{ dashboard_open_public_site }}</a>
</section>
