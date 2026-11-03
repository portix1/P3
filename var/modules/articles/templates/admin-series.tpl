<header class="page-heading"><h1>{{ articles_series }}</h1></header>
<p><a class="button" href="Admin-articles-series-new.html">{{ articles_series_new }}</a> <a href="Admin-articles.html">{{ articles_back_to_list }}</a></p>
{% if articles_message %}<p role="status">{{ articles_message }}</p>{% endif %}
<ul>{% for seriesrow in seriesrow %}<li><a href="Admin-articles-series-edit-{{ seriesrow.id }}.html">{{ seriesrow.title }}</a>
<form method="post" action="Admin-articles-series-delete-{{ seriesrow.id }}.html" class="inline-action" data-confirm-delete data-confirm-title="{{ seriesrow.title }}"><input type="hidden" name="csrf_token" value="{{ csrf_token }}"><button type="submit">{{ articles_series_delete }}</button></form></li>{% endfor %}</ul>
