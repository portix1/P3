<header class="page-heading">
    <p class="eyebrow">{{ modules_extensions }}</p>
    <h1>{{ ui_module_management }}</h1>
    <p>{{ modules_introduction }}</p>
</header>

{% if modmgr_message %}
<p class="status-message" role="status">{{ modmgr_message }}</p>
{% endif %}

<section class="admin-section">
<h2>{{ modules_install }}</h2>
<form enctype="multipart/form-data" action="Admin-config-modules.html" method="post" class="upload-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="module_action" value="upload" />
<input type="hidden" name="MAX_FILE_SIZE" value="5242880" />
<label><span>{{ modules_archive }}</span><input name="userfile" accept="application/zip, application/x-zip-compressed" type="file" required /></label>
<button type="submit">{{ modules_install_button }}</button>
</form>
</section>

<section class="admin-section">
<h2>{{ modules_installed }}</h2>
<div class="module-list" id="module-list">
<div class="module-list-header" aria-hidden="true">
<span>{{ modules_state_column }}</span>
<span>{{ modules_module_column }}</span>
<span>{{ modules_state_action_column }}</span>
<span>{{ modules_uninstall }}</span>
</div>
{% for checkmod in checkmod %}
<div class="module-row">
<span class="module-state">
{% if checkmod.enabled == "1" %}<span class="state-enabled">{{ modules_enabled }}</span>{% endif %}
{% if checkmod.enabled == "0" %}<span class="state-disabled">{{ modules_disabled }}</span>{% endif %}
{% if checkmod.uninstallable == "1" %}<small>{{ modules_uninstall_data_note }}</small>{% endif %}
</span>
<div class="module-identity">
<strong>{{ checkmod.name }}</strong>
<small class="module-version">{{ modules_version_label }} {{ checkmod.version }}</small>
{% if checkmod.update_url %}<a href="{{ checkmod.update_url }}">{{ extension_updates_download }} {{ checkmod.update_version }}</a>{% endif %}
</div>
<div class="module-row-actions">
<div class="module-action-slot module-action-state">
<form action="Admin-config-modules.html" method="post" class="inline-action">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="module_action" value="{{ checkmod.action }}" />
<input type="hidden" name="prefix" value="{{ checkmod.prefix }}" />
<button type="submit" class="{{ checkmod.action_class }}">{{ checkmod.action_label }}</button>
</form>
</div>
<div class="module-action-slot module-action-uninstall">
{% if checkmod.uninstallable == "1" %}
<form action="Admin-config-modules.html" method="post" class="inline-action" data-confirm-message="{{ modules_uninstall_confirm }}">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="module_action" value="uninstall" />
<input type="hidden" name="prefix" value="{{ checkmod.prefix }}" />
<button type="submit" class="button-danger">{{ modules_uninstall }}</button>
</form>
{% endif %}
</div>
</div>
</div>
{% endfor %}
</div>
</section>
