<header class="page-heading"><p class="eyebrow">{{ safety_title }}</p><h1>{{ safety_settings }}</h1><p>{{ safety_intro }}</p></header>
{% include "var/modules/safety/templates/nav.tpl" %}
{% if safety_message %}<p class="status-message" role="status">{{ safety_message }}</p>{% endif %}
<form method="post" action="Admin-safety-settings.html" class="settings-form"><input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="safety_action" value="settings"><div class="admin-section">
<label><span>{{ safety_trash_days }}</span><input type="number" name="trash_days" min="1" max="90" value="{{ safety_trash_days_value }}"> {{ safety_days }}</label>
<label><input type="checkbox" name="trash_never" {{ safety_trash_never_checked }}> {{ safety_trash_never }}</label>
<label><span>{{ safety_revision_limit }}</span><input type="number" name="revision_limit" min="1" max="100" value="{{ safety_revision_limit_value }}"></label>
<button type="submit">{{ safety_save }}</button></div></form>
