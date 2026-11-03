<header class="page-heading"><p class="eyebrow">{{ download }}</p><h1>{{ downloads_manage_categories }}</h1><p>{{ downloads_categories_help }}</p></header>
{% include "var/modules/download/templates/nav.tpl" %}
{% if downloads_message %}<p class="status-message" role="status">{{ downloads_message }}</p>{% endif %}
<p><a class="button" href="Admin-dwl-newcategory.html">{{ downloads_new_category }}</a></p>
<section class="admin-section insights-wide">
{% for downloadcategoryrow in downloadcategoryrow %}
<article class="admin-list-row"><div><h2>{{ downloadcategoryrow.name }}</h2><p>{{ downloadcategoryrow.description }}</p><small>/{{ downloadcategoryrow.slug }} · {{ downloadcategoryrow.count }} {{ downloads_files_count }}</small></div><div class="news-row-actions"><a class="button button-quiet" href="Admin-dwl-category-{{ downloadcategoryrow.id }}.html">{{ ui_edit }}</a><form method="post" action="Admin-dwl-categories.html" class="inline-action" data-confirm-delete data-confirm-title="{{ downloadcategoryrow.name }}"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="download_action" value="delete_category" /><input type="hidden" name="id" value="{{ downloadcategoryrow.id }}" /><button type="submit" class="button-danger">{{ ui_delete }}</button></form></div></article>
{% endfor %}
</section>
<dialog id="confirm-delete-dialog" class="confirm-dialog" aria-labelledby="downloads-delete-category-dialog-title"><form method="dialog"><h2 id="downloads-delete-category-dialog-title">{{ downloads_delete_category_title }}</h2><p>{{ downloads_delete_category_help }} « <strong data-confirm-title></strong> ».</p><div class="dialog-actions"><button class="button button-quiet" value="cancel">{{ ui_cancel }}</button><button class="button-danger" value="confirm">{{ ui_delete }}</button></div></form></dialog>
