<header class="page-heading"><p class="eyebrow">{{ insights_observability }}</p><h1>{{ insights_settings }}</h1><p>{{ insights_settings_help }}</p></header>
{% include "var/modules/insights/templates/nav.tpl" %}
{% if insights_message %}<p class="status-message" role="status">{{ insights_message }}</p>{% endif %}
<form method="post" action="Admin-insights-settings.html" class="settings-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <input type="hidden" name="insights_action" value="settings" />
    <div class="form-grid">
        <label><span>{{ insights_collection_mode }}</span><select name="mode"><option value="minimal" {{ insights_mode_minimal }}>{{ insights_mode_minimal_label }}</option><option value="estimated" {{ insights_mode_estimated }}>{{ insights_mode_estimated_label }}</option><option value="off" {{ insights_mode_off }}>{{ insights_mode_off_label }}</option></select><small>{{ insights_collection_help }}</small></label>
        <label><span>{{ insights_analytics_retention }}</span><input type="number" name="analytics_days" min="30" max="3650" value="{{ insights_analytics_days }}" /><small>{{ insights_retention_days_help }}</small></label>
        <label><span>{{ insights_application_retention }}</span><input type="number" name="application_days" min="7" max="365" value="{{ insights_application_days }}" /><small>{{ insights_retention_days_help }}</small></label>
        <label><span>{{ insights_audit_retention }}</span><input type="number" name="audit_days" min="30" max="3650" value="{{ insights_audit_days }}" /><small>{{ insights_retention_days_help }}</small></label>
    </div>
    <div class="form-actions"><button type="submit">{{ ui_save }}</button></div>
</form>
<section class="admin-section danger-zone"><h2>{{ insights_purge_title }}</h2><p>{{ insights_purge_help }}</p><form method="post" action="Admin-insights-settings.html"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="insights_action" value="purge" /><button type="submit" class="button-danger">{{ insights_purge }}</button></form></section>
