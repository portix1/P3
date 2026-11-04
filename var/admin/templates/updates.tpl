<header class="page-heading"><p class="eyebrow">{{ ux_management }}</p><h1>{{ ux_updates }}</h1></header>
<section id="extension-updates" aria-labelledby="extension-updates-title">
    <h2 id="extension-updates-title">{{ extension_updates_heading }}</h2>
    <form action="Admin-config-updates.html#extension-updates" method="post">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
        <button type="submit" name="check_extension_updates" value="1">{{ extension_updates_check }}</button>
    </form>
    {% if extension_updates_status %}
    <p class="status-message" role="status">{{ extension_updates_status }}</p>
    {% endif %}
    {% for update in extensionupdate %}
    <p>{{ update.name }} ({{ update.installed }} → {{ update.version }}) · <a href="{{ update.url }}">{{ extension_updates_download }}</a></p>
    {% endfor %}
</section>
<p><a class="button" data-admin-icon="download" href="https://github.com/portix1/P3/releases/latest">{{ dashboard_portix_version }}</a></p>
