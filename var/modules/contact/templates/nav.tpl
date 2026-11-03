<nav class="module-tabs" aria-label="{{ contact_admin_navigation }}">
    <a class="{{ contact_inbox_current }}"{% if contact_inbox_current == "is-current" %} aria-current="page"{% endif %} href="Admin-contact.html">{{ contact_inbox }}</a>
    {% if contact_can_configure == "1" %}<a class="{{ contact_settings_current }}"{% if contact_settings_current == "is-current" %} aria-current="page"{% endif %} href="Admin-contact-settings.html">{{ contact_settings }}</a>{% endif %}
</nav>
