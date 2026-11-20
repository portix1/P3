<header class="page-heading"><h1>{{ dl2_categories }}</h1><p>{{ dl2_categories_help }}</p></header>
{% if dl2_message %}<p role="status">{{ dl2_message }}</p>{% endif %}
<p><a href="Admin-dl2.html">{{ dl2_manage }}</a></p>
<section class="admin-section">
<h2>{{ dl2_add_category }}</h2>
<form method="post" action="Admin-dl2-categories.html">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}">
<input type="hidden" name="dl2_action" value="create_category">
<label>{{ dl2_category }} <input type="text" name="name" maxlength="120" required></label>
<button type="submit">{{ dl2_save }}</button></form>
</section>
<section class="admin-section"><h2>{{ dl2_categories }}</h2>
{% for dl2_category_row in dl2_category_row %}
<article class="download-admin-category">
<form method="post" action="Admin-dl2-categories.html">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}">
<input type="hidden" name="dl2_action" value="update_category">
<input type="hidden" name="id" value="{{ dl2_category_row.id }}">
<label>{{ dl2_category }} <input name="name" value="{{ dl2_category_row.name }}" maxlength="120" required></label>
<label>{{ dl2_description }} <textarea name="description" maxlength="5000">{{ dl2_category_row.description }}</textarea></label>
<p>{{ dl2_files_in_category }} : {{ dl2_category_row.usage }}</p>
<button type="submit">{{ dl2_save }}</button>
</form>
{% if dl2_category_row.can_delete == "1" %}
<form method="post" action="Admin-dl2-categories.html" data-confirm-delete data-confirm-title="{{ dl2_category_row.name }}">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}">
<input type="hidden" name="dl2_action" value="delete_category">
<input type="hidden" name="id" value="{{ dl2_category_row.id }}">
<button type="submit" class="button-danger">{{ dl2_delete }}</button>
</form>
{% endif %}
</article>
{% endfor %}
</section>
<dialog id="confirm-delete-dialog" class="confirm-dialog" aria-labelledby="dl2-delete-heading">
<form method="dialog"><h2 id="dl2-delete-heading">{{ dl2_delete }} ?</h2>
<p data-confirm-title></p><button value="cancel">{{ dl2_cancel }}</button>
<button value="confirm">{{ dl2_delete }}</button></form></dialog>
