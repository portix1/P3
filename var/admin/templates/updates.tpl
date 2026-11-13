<header class="page-heading"><p class="eyebrow">{{ ux_management }}</p><h1>{{ ux_updates }}</h1></header>
<section id="core-update" aria-labelledby="core-update-title">
    <h2 id="core-update-title">{{ core_update_heading }}</h2>
    <p>{{ core_update_current }} : <strong>{{ core_update_installed }}</strong></p>
    {% if core_update_message %}
    <p class="status-message" role="status">{{ core_update_message }}</p>
    {% endif %}
    {% if core_update_target %}
    <p>{{ core_update_available }} : <strong>{{ core_update_target }}</strong></p>
    <form action="Admin-config-updates.html#core-update" method="post">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
        <input type="hidden" name="core_target_version" value="{{ core_update_target }}" />
        <p>{{ core_update_help }}</p>
        <label>
            <input type="checkbox" name="confirm_core_update" value="1" required />
            {{ core_update_confirm }}
        </label>
        <p><button type="submit" name="install_core_update" value="1">{{ core_update_action }} {{ core_update_target }}</button></p>
    </form>
    {% else %}
    <p>{{ core_update_none }}</p>
    {% endif %}
</section>
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

