<header class="page-heading"><h1>{{ dl2_manage }}</h1><p>{{ dl2_manage_help }}</p></header>
{% if dl2_message %}<p role="status">{{ dl2_message }}</p>{% endif %}
<p><a class="button" href="Admin-dl2-new.html">{{ dl2_add }}</a></p>
<section class="admin-section">
<table><caption>{{ dl2_manage }}</caption><thead><tr><th>{{ dl2_title_field }}</th><th>{{ dl2_category }}</th><th>{{ dl2_status }}</th><th>{{ dl2_downloads_admin }}</th><th>{{ ui_actions }}</th></tr></thead>
<tbody>{% for dl2_admin_file in dl2_admin_file %}<tr>
<td><strong>{{ dl2_admin_file.title }}</strong><small>{{ dl2_admin_file.name }}</small></td>
<td>{{ dl2_admin_file.category }}</td><td>{{ dl2_admin_file.status }}</td>
<td>{{ dl2_admin_file.counter }}</td>
<td><a href="Admin-dl2-edit-{{ dl2_admin_file.id }}.html">{{ dl2_edit }}</a>
<form method="post" action="Admin-dl2.html" class="inline-action" data-confirm-delete data-confirm-title="{{ dl2_admin_file.title }}">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}">
<input type="hidden" name="dl2_action" value="delete"><input type="hidden" name="id" value="{{ dl2_admin_file.id }}">
<button type="submit" class="button-danger">{{ dl2_delete }}</button></form></td>
</tr>{% endfor %}</tbody></table>
</section>
<dialog id="confirm-delete-dialog" class="confirm-dialog"><form method="dialog">
<h2>{{ dl2_delete }} ?</h2><p data-confirm-title></p>
<button value="cancel">{{ dl2_cancel }}</button><button value="confirm">{{ dl2_delete }}</button>
</form></dialog>
