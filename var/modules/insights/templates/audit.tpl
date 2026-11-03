<header class="page-heading"><p class="eyebrow">{{ insights_observability }}</p><h1>{{ insights_audit_logs }}</h1><p>{{ insights_audit_logs_help }}</p></header>
{% include "var/modules/insights/templates/nav.tpl" %}
<div class="admin-section insights-wide table-scroll"><table><caption class="visually-hidden">{{ insights_audit_logs_caption }}</caption><thead><tr><th scope="col">{{ insights_date }}</th><th scope="col">{{ insights_actor }}</th><th scope="col">{{ insights_action }}</th><th scope="col">{{ insights_target }}</th><th scope="col">{{ insights_request }}</th></tr></thead><tbody>
{% for auditlog in auditlog %}<tr><td>{{ auditlog.date }}</td><td>{{ auditlog.actor }}</td><td>{{ auditlog.action }}</td><td>{{ auditlog.target }}</td><td><code>{{ auditlog.request_id }}</code></td></tr>{% endfor %}
</tbody></table></div>
