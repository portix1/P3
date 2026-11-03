<nav class="module-tabs" aria-label="{{ insights_navigation }}">
    <a class="{{ insights_dashboard_current }}"{% if insights_dashboard_current == "is-current" %} aria-current="page"{% endif %} href="Admin-insights.html">{{ insights_dashboard }}</a>
    <a class="{{ insights_logs_current }}"{% if insights_logs_current == "is-current" %} aria-current="page"{% endif %} href="Admin-insights-logs.html">{{ insights_application_logs }}</a>
    <a class="{{ insights_audit_current }}"{% if insights_audit_current == "is-current" %} aria-current="page"{% endif %} href="Admin-insights-audit.html">{{ insights_audit_logs }}</a>
    <a class="{{ insights_settings_current }}"{% if insights_settings_current == "is-current" %} aria-current="page"{% endif %} href="Admin-insights-settings.html">{{ insights_settings }}</a>
</nav>
