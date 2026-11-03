<!doctype html>
<html lang="{{ language_code }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="color-scheme" content="light" />
<base href="{{ base_url }}/" />
<title>{{ document_title }}</title>
{% if meta_has_description == "1" %}<meta name="description" content="{{ meta_description }}" />{% endif %}
{% if meta_has_canonical == "1" %}<link rel="canonical" href="{{ meta_canonical }}" /><meta property="og:url" content="{{ meta_canonical }}" />{% endif %}
<meta property="og:title" content="{{ document_title }}" />
{% if meta_has_description == "1" %}<meta property="og:description" content="{{ meta_description }}" />{% endif %}
<meta property="og:type" content="{{ meta_og_type }}" />
<meta property="og:site_name" content="{{ title }}" />
{% if meta_has_image == "1" %}<meta property="og:image" content="{{ meta_image }}" />{% endif %}
<meta name="twitter:card" content="{% if meta_has_image == "1" %}summary_large_image{% endif %}{% if meta_has_image == "0" %}summary{% endif %}" />
<meta name="twitter:title" content="{{ document_title }}" />
{% if meta_has_description == "1" %}<meta name="twitter:description" content="{{ meta_description }}" />{% endif %}
{% if meta_has_image == "1" %}<meta name="twitter:image" content="{{ meta_image }}" />{% endif %}
<meta name="robots" content="{{ meta_robots }}" />
{% if meta_has_json_ld == "1" %}<script type="application/ld+json">{{ meta_json_ld }}</script>{% endif %}
<link rel="stylesheet" href="var/templates/editorial/style.min.css?v={{ asset_version }}" media="screen" />
<script src="var/editor/portix-ui.js?v={{ asset_version }}" defer></script>
</head>
<body class="theme-editorial">
<a class="skip-link" href="#main-content">{{ ui_skip_content }}</a>
<div class="site-shell editorial-shell">
    <header class="site-header editorial-masthead">
        <div class="masthead-topline">
            <span class="masthead-kicker">P3</span>
            <div class="site-header-actions">
                {% if is_authenticated == "1" %}<a class="site-user-name" href="personnal.html">{{ username }}</a>{% endif %}
                {% if can_access_admin == "1" %}<a class="site-role-action action-content" href="Admin.html#content-management">{{ ui_manage_content }}</a>{% endif %}
                {% if is_admin == "1" %}<a class="site-role-action action-admin" href="Admin.html#administration">{{ ui_configure }}</a>{% endif %}
                {% if is_authenticated == "1" %}<form method="post" action="disconnect.html" class="site-logout-form"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><button type="submit" class="site-logout-button">{{ ui_logout }}</button></form>{% endif %}
                <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation"><span aria-hidden="true">☰</span> {{ ui_menu }}</button>
            </div>
        </div>
        <a class="brand editorial-brand" href="./" aria-label="{{ ui_home }} — {{ title }}">
            <span class="brand-copy"><strong>{{ title }}</strong><small>{{ ui_powered_by }}</small></span>
        </a>
    </header>

    <nav class="main-navigation editorial-navigation" id="main-navigation" aria-label="{{ ui_main_navigation }}">
        <ul>
            {% for navigation in navigation %}
            <li class="navigation-item {{ navigation.item_class }}">
                <div class="navigation-link-row">
                    <a class="{{ navigation.current }}"{% if navigation.current == "is-current" %} aria-current="page"{% endif %} href="{{ navigation.link }}">{{ navigation.name }}</a>
                    {% if navigation.has_submenu == "1" %}<button class="submenu-toggle" type="button" data-submenu-toggle aria-expanded="false" aria-haspopup="true" aria-controls="{{ navigation.submenu_id }}" aria-label="{{ navigation.toggle_label }}"><span aria-hidden="true">▾</span></button>{% endif %}
                </div>
                {{ navigation.submenu }}
            </li>
            {% endfor %}
        </ul>
    </nav>

    <div class="page-layout{% if has_auxiliary_sidebar == "0" %} page-layout-wide{% endif %}">
        <main class="content-card editorial-content" id="main-content" tabindex="-1">
            {% slot breadcrumb %}
    {% slot header %}
            {% slot main %}
        </main>
        {% if has_auxiliary_sidebar == "1" %}
        <aside class="sidebar editorial-sidebar" aria-label="{{ ui_auxiliary_navigation }}">
            {% for menu1 in menu1 %}<section class="side-panel auxiliary-panel">{% slot menu1 %}</section>{% endfor %}
            {% for menu2 in menu2 %}<section class="side-panel auxiliary-panel">{% slot menu2 %}</section>{% endfor %}
        </aside>
        {% endif %}
    </div>

    <footer class="site-footer editorial-footer">
        <span>P3 — {{ ui_version }} {{ portix_version }}</span>
        <span>{{ ui_original_project }}</span>
        {% if is_authenticated == "0" %}<a class="footer-login-link" href="login.html" aria-label="{{ ui_login }}" title="{{ ui_login }}">☠</a>{% endif %}
        {% slot footer %}
    </footer>
</div>
</body>
</html>
