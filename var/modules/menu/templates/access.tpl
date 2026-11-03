<h1>{{ menu_accessibility_charter }}</h1>
<p>{{ menu_accessibility_help }}</p>
<ul class="menu-list">
{% for menusimple in menusimple %}
<li><a href="{{ menusimple.url }}">{{ menusimple.name }}</a>{% if menusimple.desc %} — {{ menusimple.desc }}{% endif %}</li>
{% endfor %}
</ul>
