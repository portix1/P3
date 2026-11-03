<link rel="stylesheet" href="var/modules/search/assets/search.css?v=1.0.3" />
<section class="search-page" aria-labelledby="search-page-title">
    <header class="search-page-heading">
        <h1 id="search-page-title">{{ search_title }}</h1>
    </header>

    <form method="get" action="search.html" class="search-page-form" role="search" aria-label="{{ search_title }}">
        <label for="search-page-query">
            <span>{{ search_label }}</span>
            <input id="search-page-query" type="search" name="q" minlength="2" maxlength="120" required value="{{ search_query }}" placeholder="{{ search_placeholder }}" />
        </label>
        <label for="search-page-filter">
            <span>{{ search_filter_label }}</span>
            <select id="search-page-filter" name="type">
                <option value="all" {{ search_filter_all_selected }}>{{ search_filter_all }}</option>
                <option value="article" {{ search_filter_article_selected }}>{{ search_filter_articles }}</option>
                <option value="news" {{ search_filter_news_selected }}>{{ search_filter_news }}</option>
            </select>
        </label>
        <button type="submit">{{ search_submit }}</button>
    </form>

    {% if search_status_message %}<p class="search-status" role="status">{{ search_status_message }}</p>{% endif %}

    {% if search_has_query == "0" %}
    <p class="search-help">{{ search_start_help }}</p>
    {% endif %}

    {% if search_has_query == "1" %}
    <p class="search-result-count" role="status">{{ search_result_message }}</p>

    {% if search_has_results == "0" %}
    <p class="search-help">{{ search_no_results }}</p>
    {% endif %}

    {% if search_has_results == "1" %}
    <ul class="search-result-list">
        {% for result in searchresult %}
        <li class="search-result">
            <h2><a href="{{ result.url }}">{{ result.title }}</a></h2>
            <p class="search-result-meta">{{ result.kind_label }}{% if result.category %}<span aria-hidden="true"> · </span>{{ result.category }}{% endif %}</p>
            {% if result.excerpt %}<p class="search-result-excerpt">{{ result.excerpt }}</p>{% endif %}
        </li>
        {% endfor %}
    </ul>
    {% endif %}
    {% endif %}
</section>
