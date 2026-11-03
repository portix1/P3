<!doctype html>
<html lang="{{ language_code }}" class="no-js">
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
<link rel="stylesheet" href="var/templates/six-cordes-ux-clair/style.min.css?v={{ asset_version }}" media="screen" />
<script src="var/editor/portix-ui.js?v={{ asset_version }}" defer></script>
<script src="var/templates/six-cordes-ux-clair/assets/reading-mobile.js?v=0.1.3" defer></script>
<script src="var/templates/six-cordes-ux-clair/assets/clair-ui.js?v=0.1.0" defer></script>
</head>
<body class="theme-six-cordes theme-six-cordes-clair{% if is_authenticated == "1" %} is-authenticated{% endif %}">
<a class="skip-link" href="#main-content">{{ ui_skip_content }}</a>
<div class="site-shell six-cordes-shell" id="page-top">
    <header class="site-header six-cordes-masthead">
        <a class="brand six-cordes-brand" href="./" aria-label="{{ ui_home }} — {{ title }}">
            <span class="clair-brand-mark" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-linecap="round"><path d="M4 4v20M8 4v20M12 4v20M16 4v20M20 4v20M24 4v20" stroke-width="1.2"/><path d="M3 9h22M3 19h22" stroke-width="1.5"/></svg></span>
            <span class="brand-copy"><strong>{{ title }}</strong><small>Atelier &amp; lutherie</small></span>
        </a>
        <div class="site-header-tools">
            <div class="site-header-search">
                {% for navigation in navigation %}
                {% if navigation.item_class == "navigation-search" %}{{ navigation.submenu }}{% endif %}
                {% if navigation.item_class == "navigation-search-inline" %}{{ navigation.submenu }}{% endif %}
                {% endfor %}
            </div>
            <div class="site-header-actions">
                {% if is_authenticated == "1" %}<a class="site-user-name" href="personnal.html">{{ username }}</a>{% endif %}
                {% if can_access_admin == "1" %}<a class="site-role-action action-content" href="Admin.html#content-management">{{ ui_manage_content }}</a>{% endif %}
                {% if is_admin == "1" %}<a class="site-role-action action-admin" href="Admin.html#administration">{{ ui_configure }}</a>{% endif %}
                {% if is_authenticated == "1" %}<form method="post" action="disconnect.html" class="site-logout-form"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><button type="submit" class="site-logout-button">{{ ui_logout }}</button></form>{% endif %}
                <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="main-navigation"><span aria-hidden="true">☰</span> {{ ui_menu }}</button>
            </div>
        </div>
    </header>

    <nav class="main-navigation six-cordes-top-navigation" id="main-navigation" aria-label="{{ ui_main_navigation }}">
        <ul>
            {% for navigation in navigation %}
            {% if navigation.item_class != "navigation-search" %}
            {% if navigation.item_class != "navigation-search-inline" %}
            <li class="navigation-item {{ navigation.item_class }}">
                <div class="navigation-link-row">
                    <a class="{{ navigation.current }}"{% if navigation.current == "is-current" %} aria-current="page"{% endif %} href="{{ navigation.link }}">{{ navigation.name }}</a>
                    {% if navigation.has_submenu == "1" %}<button class="submenu-toggle" type="button" data-submenu-toggle aria-expanded="false" aria-haspopup="true" aria-controls="{{ navigation.submenu_id }}" aria-label="{{ navigation.toggle_label }}"><span aria-hidden="true">▾</span></button>{% endif %}
                </div>
                {% if navigation.has_submenu == "1" %}{{ navigation.submenu }}{% endif %}
            </li>
            {% endif %}
            {% endif %}
            {% endfor %}
        </ul>
    </nav>

    <div class="page-layout">
        <aside class="six-cordes-left" aria-label="{{ ui_auxiliary_navigation }}">
            {% for navigation in navigation %}
            {% if navigation.current == "is-current" %}
            {% if navigation.has_submenu == "1" %}
            <section class="six-cordes-category-panel">
                <h2>{{ navigation.name }}</h2>
                <nav class="six-cordes-category-nav" aria-label="{{ navigation.name }}">
                    {{ navigation.submenu }}
                </nav>
            </section>
            {% endif %}
            {% endif %}
            {% endfor %}
            {% if has_auxiliary_sidebar == "1" %}
            <div class="six-cordes-sidebar">
                {% for menu1 in menu1 %}<section class="side-panel auxiliary-panel">{% slot menu1 %}</section>{% endfor %}
                {% for menu2 in menu2 %}<section class="side-panel auxiliary-panel">{% slot menu2 %}</section>{% endfor %}
            </div>
            {% endif %}
        </aside>

        <main class="content-card six-cordes-content" id="main-content" tabindex="-1">
            {% slot breadcrumb %}
            {% slot header %}
            {% slot main %}
            {% slot after_main %}
        </main>
    </div>

    <footer class="site-footer six-cordes-footer">
        {% if is_authenticated == "0" %}<a class="footer-login-link" href="login.html" aria-label="{{ ui_login }}" title="{{ ui_login }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true" focusable="false"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/></svg></a>{% endif %}
        {% slot footer %}
    </footer>
</div>
<a class="volume-knob" href="{{ meta_canonical }}#page-top" aria-label="Retour en haut de page" title="Retour en haut de page"><span aria-hidden="true">↑</span></a>
</body>
</html>