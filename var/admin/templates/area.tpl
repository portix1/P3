<header class="page-heading"><h1>{{ ux_area_title }}</h1><p>{{ ux_area_help }}</p></header>
<div class="management-grid">
{% for item in ux_entries %}<a class="management-card workspace-link" href="{{ item.url }}" data-admin-icon="{{ item.icon }}"><strong>{{ item.name }}</strong></a>{% endfor %}
</div>
