<header class="page-heading"><p class="eyebrow">{{ safety_title }}</p><h1>{{ safety_revisions }}</h1><p>{{ safety_intro }}</p></header>
{% include "var/modules/safety/templates/nav.tpl" %}
{% if safety_message %}<p class="status-message" role="status">{{ safety_message }}</p>{% endif %}
<div class="admin-section table-scroll"><table><caption>{{ safety_revisions }}</caption><thead><tr><th scope="col">{{ safety_type }}</th><th scope="col">{{ safety_title_col }}</th><th scope="col">{{ safety_date }}</th><th scope="col">{{ safety_actor }}</th><th scope="col"></th></tr></thead><tbody>
{% for revisionrow in revisionrow %}<tr><td>{{ revisionrow.type }}</td><td>{{ revisionrow.title }}</td><td>{{ revisionrow.date }}</td><td>{{ revisionrow.actor }}</td><td><form method="post" action="Admin-safety-revisions.html"><input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="safety_action" value="restore_revision"><input type="hidden" name="id" value="{{ revisionrow.id }}"><button type="submit">{{ safety_restore }}</button></form></td></tr>{% endfor %}
</tbody></table></div>
{% if safety_has_revisions == "0" %}<p>{{ safety_no_revisions }}</p>{% endif %}
