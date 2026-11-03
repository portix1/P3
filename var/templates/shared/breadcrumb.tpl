{% if breadcrumb_has_items == "1" %}
<nav class="site-breadcrumb article-breadcrumb" aria-label="{{ breadcrumb_label }}">
    <ol>
        {% for breadcrumbitem in breadcrumbitem %}
        {% if breadcrumbitem.current == "1" %}
        <li aria-current="page">{{ breadcrumbitem.name }}</li>
        {% else %}
        <li><a href="{{ breadcrumbitem.url }}">{{ breadcrumbitem.name }}</a><span aria-hidden="true">›</span></li>
        {% endif %}
        {% endfor %}
    </ol>
</nav>
{% endif %}
