<header class="page-heading">
    <p class="eyebrow">{{ affiliation_title }}</p>
    <h1>{{ affiliation_settings }}</h1>
    <p>{{ affiliation_settings_help }}</p>
</header>
{% include "var/modules/affiliation/templates/nav.tpl" %}
{% if affiliation_message %}<p class="status-message" role="status">{{ affiliation_message }}</p>{% endif %}
<form method="post" action="Admin-affiliation-settings.html" class="settings-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
    <input type="hidden" name="affiliation_action" value="settings">
    <label><span>{{ affiliation_settings_title_label }}</span><input type="text" name="title" maxlength="160" required value="{{ affiliation_settings_title }}"><small>{{ affiliation_settings_title_help }}</small></label>
    <label><span>{{ affiliation_settings_disclosure_label }}</span><textarea name="disclosure" maxlength="500" rows="4">{{ affiliation_settings_disclosure }}</textarea><small>{{ affiliation_settings_disclosure_help }}</small></label>
    <label><span>{{ affiliation_settings_max_label }}</span><input type="number" name="max_products" min="1" max="12" value="{{ affiliation_settings_max }}"><small>{{ affiliation_settings_max_help }}</small></label>
    <div class="form-actions"><button type="submit">{{ affiliation_save }}</button></div>
</form>
