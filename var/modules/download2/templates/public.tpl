<header class="content-heading"><h1>{{ dl2_title }}</h1><p>{{ dl2_intro }}</p></header>
{% if dl2_has_files == "0" %}<p>{{ dl2_none }}</p>{% endif %}
{% if dl2_has_files == "1" %}<div class="download-file-list">
{% for dl2_public_file in dl2_public_file %}<article class="download-file-card">
<div><h2>{{ dl2_public_file.title }}</h2><p>{{ dl2_public_file.description }}</p>
<small>{{ dl2_public_file.category }} {{ dl2_public_file.size }}</small></div>
<a class="button download-button" href="{{ dl2_public_file.url }}">{{ dl2_download }}</a>
</article>{% endfor %}</div>{% endif %}
