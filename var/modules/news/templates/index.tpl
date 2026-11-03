{% if content_preview == "1" %}
<p class="status-message content-preview-notice" role="status">{{ content_preview_notice }}</p>
{% endif %}

{% for shownews in shownews %}
<article class="news-detail">
    <header>
        <h1>{{ shownews.title }}</h1>
        {% if shownews.show_date == "1" %}<time>{{ shownews.date }}</time>{% endif %}
    </header>
    <div class="news-intro news-rich-text">{{ shownews.intro }}</div>
    <div class="news-body news-rich-text">{{ shownews.content }}</div>
</article>
<p><a href="news.html">{{ backnews }}</a></p>
{% endfor %}

{% if news_listing == "1" %}
<header class="content-heading">
    <h1>{{ ui_news }}</h1>
</header>

<div class="news-list">
{% for viewnews in viewnews %}
<article class="news-card">
    <header>
        <h2><a href="{{ viewnews.slug }}.html">{{ viewnews.title }}</a></h2>
        {% if viewnews.show_date == "1" %}<time>{{ viewnews.date }}</time>{% endif %}
    </header>
    <div class="news-summary news-rich-text">{{ viewnews.intro }}</div>
    <p><a class="read-more" href="{{ viewnews.slug }}.html">{{ follownews }}</a></p>
</article>
{% endfor %}
</div>

<nav class="pagination" aria-label="{{ news_pagination }}">
{% for page in page %}
<a class="{{ page.current }}"{% if page.current == "is-current" %} aria-current="page"{% endif %} href="news-page-{{ page.page }}.html">{{ page.page }}</a>
{% endfor %}
</nav>
<p class="rss-link"><a href="news-rss.html">{{ syndicnews }}</a></p>
{% endif %}
