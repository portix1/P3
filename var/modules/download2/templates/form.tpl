<header class="page-heading"><h1>{{ dl2_form_title }}</h1><p><a href="Admin-dl2.html">{{ dl2_manage }}</a> · <a href="Admin-dl2-categories.html">{{ dl2_categories }}</a></p></header>
{% if dl2_message %}<p role="status">{{ dl2_message }}</p>{% endif %}
<form method="post" enctype="multipart/form-data" class="settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}">
<input type="hidden" name="dl2_action" value="{{ dl2_form_action }}">
<input type="hidden" name="id" value="{{ dl2_id }}">
<input type="hidden" name="MAX_FILE_SIZE" value="20971520">
<div class="form-grid">
<label>{{ dl2_title_field }} <input type="text" name="title" value="{{ dl2_title_value }}" maxlength="200"></label>
<label>{{ dl2_category }}<select name="category_id"><option value="" {{ dl2_category_none_selected }}>{{ dl2_without_category }}</option>
{% for dl2_category_option in dl2_category_option %}<option value="{{ dl2_category_option.id }}" {{ dl2_category_option.selected }}>{{ dl2_category_option.name }}</option>{% endfor %}
</select></label>
<label>{{ dl2_status }} <select name="status"><option value="draft" {{ dl2_draft_selected }}>{{ dl2_draft }}</option><option value="published" {{ dl2_published_selected }}>{{ dl2_published }}</option></select></label>
<label>{{ dl2_file }} <input type="file" name="file" accept=".pdf,.zip,.png,.jpg,.jpeg,.gif,.txt,.csv,.tar,.gz" {{ dl2_file_required }}>
<small>{{ dl2_file_current }} {{ dl2_file_help }}</small></label>
<label class="form-grid-wide"><input type="checkbox" name="show_in_library" value="1" {{ dl2_show_in_library_checked }}> {{ dl2_show_in_library }}</label>
<label class="form-grid-wide">{{ dl2_description }}<textarea name="description" maxlength="5000">{{ dl2_description_value }}</textarea></label>
</div><button type="submit">{{ dl2_save }}</button> <a href="Admin-dl2.html">{{ dl2_cancel }}</a>
</form>
