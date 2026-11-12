<header class="page-heading">
    <h1>{{ insights_dashboard }}</h1>
    <p>{{ insights_collection_status }}</p>
</header>
{% include "var/modules/insights/templates/nav.tpl" %}
<form method="get" action="Admin-insights.html" class="settings-form">
    <label>{{ insights_period }}
        <select name="days">
            <option value="7" {{ insights_period_7_selected }}>7 {{ insights_days_label }}</option>
            <option value="30" {{ insights_period_30_selected }}>30 {{ insights_days_label }}</option>
            <option value="90" {{ insights_period_90_selected }}>90 {{ insights_days_label }}</option>
            <option value="365" {{ insights_period_365_selected }}>365 {{ insights_days_label }}</option>
        </select>
    </label>
    <button type="submit">{{ insights_apply }}</button>
    <a href="Admin-insights-export.html?days={{ insights_days }}">{{ insights_export_csv }}</a>
</form>
{% if insights_has_data == "1" %}
<section class="admin-section">
    <dl>
        <dt>{{ insights_page_views_label }}</dt><dd>{{ insights_page_views }}</dd>
        <dt>{{ insights_visits_label }}</dt><dd>{{ insights_visits }}</dd>
        <dt>{{ insights_pages_per_visitor_label }}</dt><dd>{{ insights_pages_per_visitor }}</dd>
    </dl>
    <p>{{ insights_ratio_help }}</p>
</section>
<link rel="stylesheet" href="var/modules/insights/assets/daily-chart.css?v=2.0.2" />
<section class="admin-section insights-wide">
    <h2 id="insights-daily-evolution">{{ insights_evolution }}</h2>
    <ul class="insights-daily-legend">
        <li><span class="insights-key-views" aria-hidden="true"></span>{{ insights_page_views_label }}</li>
        <li><span class="insights-key-visitors" aria-hidden="true"></span>{{ insights_visits_label }}</li>
    </ul>
    <ul class="insights-chart" aria-labelledby="insights-daily-evolution">
        {% for insightday in insightday %}
        <li class="insights-daily-row">
            <time datetime="{{ insightday.day }}">{{ insightday.day }}</time>
            <div class="insights-daily-pair">
                <progress class="insights-daily-views" max="{{ insightday.chart_max }}" value="{{ insightday.views }}" aria-label="{{ insights_page_views_label }} · {{ insightday.day }}">{{ insightday.views }}</progress>
                <strong aria-hidden="true">{{ insightday.views }}</strong>
                <progress class="insights-daily-visitors" max="{{ insightday.chart_max }}" value="{{ insightday.visitors }}" aria-label="{{ insights_visits_label }} · {{ insightday.day }}">{{ insightday.visitors }}</progress>
                <strong aria-hidden="true">{{ insightday.visitors }}</strong>
            </div>
        </li>
        {% endfor %}
    </ul>
    <details class="table-scroll">
        <summary>{{ insights_daily_details }}</summary>
        <table>
            <caption class="visually-hidden">{{ insights_daily_details }}</caption>
        <thead><tr><th scope="col">{{ insights_date }}</th><th scope="col">{{ insights_page_views_label }}</th><th scope="col">{{ insights_visits_label }}</th></tr></thead>
        <tbody>{% for insightday in insightday %}<tr><td>{{ insightday.day }}</td><td>{{ insightday.views }}</td><td>{{ insightday.visitors }}</td></tr>{% endfor %}</tbody>
        </table>
    </details>
</section>
<section class="admin-section table-scroll">
    <table>
        <caption>{{ insights_top_pages }}</caption>
        <thead><tr><th scope="col">{{ insights_article }}</th><th scope="col">{{ insights_page_views_label }}</th></tr></thead>
        <tbody>{% for insightarticle in insightarticle %}<tr><td>{{ insightarticle.title }}</td><td>{{ insightarticle.total }}</td></tr>{% endfor %}</tbody>
    </table>
</section>
<section class="admin-section table-scroll">
    <table>
        <caption>{{ insights_entries }}</caption>
        <thead><tr><th scope="col">{{ insights_page }}</th><th scope="col">{{ insights_visits_label }}</th></tr></thead>
        <tbody>{% for insightentry in insightentry %}<tr><td>{{ insightentry.title }} <small>{{ insightentry.name }}</small></td><td>{{ insightentry.total }}</td></tr>{% endfor %}</tbody>
    </table>
</section>
<section class="admin-section table-scroll">
    <table>
        <caption>{{ insights_sources }}</caption>
        <thead><tr><th scope="col">{{ insights_source }}</th><th scope="col">{{ insights_visits_label }}</th></tr></thead>
        <tbody>{% for insightreferrer in insightreferrer %}<tr><td>{{ insightreferrer.name }}</td><td>{{ insightreferrer.total }}</td></tr>{% endfor %}</tbody>
    </table>
</section>
{% else %}
<p>{{ insights_no_data }}</p>
{% endif %}
