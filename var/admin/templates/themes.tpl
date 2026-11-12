<header class="page-heading"><p class="eyebrow">{{ ux_configuration }}</p><h1>{{ ux_themes }}</h1></header>
{% if confmessage %}<p role="status">{{ confmessage }}</p>{% endif %}
{% include "var/admin/templates/config-appearance.tpl" %}
<section class="admin-section" aria-labelledby="theme-inventory-title">
<h2 id="theme-inventory-title">{{ themes_inventory_title }}</h2><p>{{ themes_inventory_help }}</p>
<div class="table-scroll"><table class="theme-inventory-table">
<caption class="visually-hidden">{{ themes_inventory_title }}</caption>
<thead><tr><th scope="col">{{ themes_name }}</th><th scope="col">{{ themes_version }}</th><th scope="col">{{ themes_type }}</th><th scope="col">{{ themes_compatibility }}</th><th scope="col">{{ themes_file_state }}</th><th scope="col">{{ themes_details }}</th></tr></thead>
<tbody>{% for themerow in themerow %}<tr><td><strong>{{ themerow.name }}</strong><br /><small>{{ themerow.id }} {{ themerow.active }}</small></td><td>{{ themerow.version }}
{% if themerow.update_url %}<br /><a href="{{ themerow.update_url }}">{{ extension_updates_download }} {{ themerow.update_version }}</a>{% endif %}
</td><td>{{ themerow.type }}</td><td>{{ themerow.compatibility }}</td><td>{{ themerow.files }}</td><td><details><summary title="{{ themes_details }}">{{ themes_details }}</summary><p>{{ themerow.details }}</p></details></td></tr>{% endfor %}</tbody>
</table></div>
</section>

<section class="admin-section" aria-labelledby="theme-install-title">
<h2 id="theme-install-title">{{ themes_install_title }}</h2><p>{{ themes_install_help }}</p><p>{{ extension_updates_upload_help }}</p>
<form method="post" action="Admin-config-themes.html" enctype="multipart/form-data" class="settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="themes_action" value="analyse" />
<label><span>{{ themes_zip_label }}</span><input type="file" name="theme_archive" accept=".zip,application/zip" required /><small>{{ themes_zip_help }}</small></label>
<button type="submit" title="{{ ux_install_theme }}">{{ ux_install_theme }}</button>
</form>
{% if themes_have_plan == "1" %}
<h3>{{ themes_plan_name }} — {{ themes_plan_version }}</h3><p>{{ themes_previous }} : {{ themes_plan_previous }}</p>
{% if themes_plan_ready == "0" %}<p class="error-box" role="alert">{{ themes_conflicts_help }} {{ themes_plan_conflicts }}</p>{% endif %}
<form method="post" action="Admin-config-themes.html"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="theme_token" value="{{ themes_plan_token }}" />
{% if themes_plan_ready == "1" %}<label class="checkbox-label"><input type="checkbox" name="themes_confirm" value="yes" required /><span>{{ themes_confirm_install }}</span></label><button type="submit" name="themes_action" value="install" title="{{ themes_install }}">{{ themes_install }}</button>{% endif %}
<button type="submit" name="themes_action" value="cancel" formnovalidate title="{{ themes_cancel }}">{{ themes_cancel }}</button></form>
{% endif %}
</section>
<section class="admin-section"><h2>{{ themes_backups_title }}</h2><p>{{ themes_backups_help }}</p>
{% for themebackup in themebackup %}<article><h3>{{ themebackup.id }} — {{ themes_before_version }} {{ themebackup.version }}</h3><p>{{ themebackup.date }}</p><form method="post" action="Admin-config-themes.html"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="themes_action" value="restore" /><input type="hidden" name="backup_token" value="{{ themebackup.token }}" /><label class="checkbox-label"><input type="checkbox" name="themes_confirm" value="yes" required /><span>{{ themes_confirm_restore }}</span></label><button type="submit" title="{{ themes_restore }}">{{ themes_restore }}</button></form></article>{% endfor %}
</section>
