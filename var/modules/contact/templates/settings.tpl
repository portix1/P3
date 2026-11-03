<header class="page-heading"><p class="eyebrow">{{ contact }}</p><h1>{{ contact_settings }}</h1><p>{{ contact_settings_help }}</p></header>
{% include "var/modules/contact/templates/nav.tpl" %}
{% if contact_admin_message %}<p class="status-message" role="status">{{ contact_admin_message }}</p>{% endif %}
<form method="post" action="Admin-contact-settings.html" class="settings-form"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="contact_admin_action" value="settings" /><div class="form-grid">
<label><span>{{ contact_recipient_name_label }}</span><input type="text" name="recipient_name" value="{{ contact_recipient_name }}" required maxlength="120" /><small>{{ contact_recipient_name_help }}</small></label>
<label><span>{{ contact_notification_email_label }}</span><input type="email" name="notification_email" value="{{ contact_notification_email }}" required /><small>{{ contact_notification_email_help }}</small></label>
<label><span>{{ contact_retention_label }}</span><input type="number" name="trash_retention_days" min="1" max="3650" value="{{ contact_retention_days }}" /><small>{{ contact_retention_help }}</small></label>
</div><div class="form-actions"><button type="submit" title="{{ ui_save }}">{{ ui_save }}</button></div></form>
<p>{{ contact_purge_help }}</p><a href="Admin-contact.html?folder=trash">{{ contact_folder_trash }}</a>
