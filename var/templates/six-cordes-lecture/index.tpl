<!doctype html>
<html lang="{{ language_code }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="color-scheme" content="dark" />
<base href="{{ base_url }}/" />
<link rel="preload" as="image" type="image/webp" href="var/templates/six-cordes-lecture/assets/guitar-header.webp" />
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
<link rel="stylesheet" href="var/templates/six-cordes-lecture/style.min.css?v={{ asset_version }}" media="screen" />
<style>
/* Six Cordes — footer miroir du masthead. Placé ici pour ne pas dépendre du cache CSS. */
body.theme-six-cordes .site-footer.six-cordes-footer {
    min-height: 116px !important;
    align-items: flex-end !important;
    padding: 20px clamp(18px, 4vw, 42px) !important;
    overflow: hidden !important;
    border: 1px solid #343538 !important;
    border-radius: 0 0 18px 18px !important;
    color: #d8d2c8 !important;
    background-color: #191a1c !important;
    background-image:
        linear-gradient(90deg, rgba(9,10,12,.90) 0%, rgba(9,10,12,.64) 52%, rgba(9,10,12,.30) 100%),
        url("var/templates/six-cordes-lecture/assets/guitar-header.webp") !important;
    background-repeat: no-repeat !important;
    background-position: center 72% !important;
    background-size: cover !important;
    box-shadow: 0 16px 42px rgba(0,0,0,.22) !important;
}
body.theme-six-cordes .site-footer.six-cordes-footer a { color: #e4b67e !important; }
body.theme-six-cordes .site-footer.six-cordes-footer a:hover,
body.theme-six-cordes .site-footer.six-cordes-footer a:focus-visible { color: #ffd49f !important; }
@media (max-width: 820px) {
    body.theme-six-cordes .site-footer.six-cordes-footer {
        min-height: 92px !important;
        padding: 16px !important;
        background-position: 58% 72% !important;
    }
}
</style>
<script src="var/editor/portix-ui.js?v={{ asset_version }}" defer></script>
<link rel="stylesheet" href="var/templates/six-cordes-lecture/assets/reading-mobile.css?v=0.1.10" media="screen" />
<script src="var/templates/six-cordes-lecture/assets/reading-mobile.js?v=0.1.10" defer></script>
</head>
<body class="theme-six-cordes">
<a class="skip-link" href="#main-content">{{ ui_skip_content }}</a>
<div class="site-shell six-cordes-shell" id="page-top">
    <header class="site-header six-cordes-masthead">
        <a class="brand six-cordes-brand" href="./" aria-label="{{ ui_home }} — {{ title }}">
            <span class="brand-copy"><strong>{{ title }}</strong></span>
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
                    {% if navigation.has_submenu == "1" %}<button class="submenu-toggle" type="button" data-submenu-toggle aria-expanded="false" aria-controls="{{ navigation.submenu_id }}" aria-label="{{ navigation.toggle_label }}"><span aria-hidden="true">▾</span></button>{% endif %}
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
            <details class="six-cordes-category-panel">
                <summary>Explorer la rubrique : {{ navigation.name }}</summary>
                <nav class="six-cordes-category-nav" aria-label="{{ navigation.name }}">
                    <a class="six-cordes-category-home" href="{{ navigation.link }}">Voir la rubrique</a>
                    {{ navigation.submenu }}
                </nav>
            </details>
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
        {% if is_authenticated == "0" %}<a class="footer-login-link" href="login.html" aria-label="{{ ui_login }}" title="{{ ui_login }}">☠</a>{% endif %}
        {% slot footer %}
    </footer>
</div>
<a class="volume-knob" href="{{ meta_canonical }}#page-top" aria-label="Retour en haut de page" title="Retour en haut de page"><span aria-hidden="true">↑</span></a>
</body>
</html>