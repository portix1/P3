{% if content_preview == "1" %}
<p class="status-message content-preview-notice" role="status">{{ content_preview_notice }}</p>
{% endif %}

{% for articleeditlink in articleeditlink %}
<link rel="stylesheet" href="var/modules/articles/public-edit.css?v={{ articleeditlink.version }}">
{% endfor %}

{% for showarticle in showarticle %}
<link rel="stylesheet" href="var/modules/articles/reading-mobile-lot2.css?v=20261112" media="screen">
<article class="news-detail article-reading">
    <header class="p3-article-edit-heading"><h1>{{ showarticle.title }}</h1>
        {% for articleeditlink in articleeditlink %}
        <a class="p3-article-edit-link" href="{{ articleeditlink.href }}" title="{{ articleeditlink.label }}" aria-label="{{ articleeditlink.label }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m16 3 5 5M4 15 16 3a2.8 2.8 0 0 1 5 5L9 20l-6 1zM4 15l5 5"/></svg>
        </a>
        {% endfor %}
    </header>
    {% if article_series_visible == "1" %}
    <aside class="article-series">
        <details id="p3-series-parts">
            <summary>{% if article_series_show_title == "1" %}<strong class="article-series-title">{{ article_series_title }}</strong> {% endif %}<span aria-label="{{ article_series_position }}">({{ article_series_position_compact }})</span> · <span class="article-series-toggle">{{ articles_series_parts }}</span></summary>
            {% if article_series_description %}<p>{{ article_series_description }}</p>{% endif %}
            <ol>{% for seriespart in seriespart %}<li>{% if seriespart.current == "1" %}<span aria-current="page">{{ seriespart.title }}</span>{% else %}<a href="{{ seriespart.slug }}.html">{{ seriespart.title }}</a>{% endif %}</li>{% endfor %}</ol>
        </details>
    </aside>
    {% endif %}
    {{ article_heading_toc }}
    <div class="news-body news-rich-text">{{ showarticle.body }}</div>
    {% if articles_has_navigation == "1" %}
    <nav class="article-sequential-navigation" aria-label="{{ articles_menu_navigation_title }}">
        {% for articleprevious in articleprevious %}
        <a class="article-sequential-link article-sequential-previous" rel="prev" href="{{ articleprevious.slug }}.html">
            <small>{{ articles_previous }}</small><strong>{{ articleprevious.title }}</strong>
        </a>
        {% endfor %}
        {% for articlenext in articlenext %}
        <a class="article-sequential-link article-sequential-next" rel="next" href="{{ articlenext.slug }}.html">
            <small>{{ articles_next }}</small><strong>{{ articlenext.title }}</strong>
        </a>
        {% endfor %}
    </nav>
    {% endif %}
    {% if article_has_related == "1" %}
    <aside class="article-related" aria-label="{{ articles_related }}"><h2>{{ articles_related }}</h2>
        <ul>{% for relatedarticle in relatedarticle %}<li><a href="{{ relatedarticle.slug }}.html">{{ relatedarticle.title }}</a></li>{% endfor %}</ul>
    </aside>
    {% endif %}
</article>
{% endfor %}
{% if articles_listing == "1" %}
<header class="content-heading">
    <h1>{{ articles_list_title }}</h1>
    {% if articles_category_description_value %}<p class="article-category-description">{{ articles_category_description_value }}</p>{% endif %}
</header>
{% if articles_has_items == "0" %}<p class="status-message" role="status">{{ articles_empty }}</p>{% endif %}
{{ articles_toc }}
{% endif %}
