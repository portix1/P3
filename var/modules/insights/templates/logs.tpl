<header class="page-heading"><p class="eyebrow">{{ insights_observability }}</p><h1>{{ insights_application_logs }}</h1><p>{{ insights_application_logs_help }}</p></header>
{% include "var/modules/insights/templates/nav.tpl" %}
<div class="admin-section insights-wide table-scroll"><table><caption class="visually-hidden">{{ insights_application_logs_caption }}</caption><thead><tr><th scope="col">{{ insights_date }}</th><th scope="col">{{ insights_level }}</th><th scope="col">{{ insights_event }}</th><th scope="col">{{ insights_message }}</th><th scope="col">{{ insights_request }}</th></tr></thead><tbody>
{% for applicationlog in applicationlog %}<tr><td>{{ applicationlog.date }}</td><td><span class="log-level log-{{ applicationlog.level }}">{{ applicationlog.level }}</span></td><td>{{ applicationlog.event }}</td><td>{{ applicationlog.message }}</td><td><code>{{ applicationlog.request_id }}</code></td></tr>{% endfor %}
</tbody></table></div>
