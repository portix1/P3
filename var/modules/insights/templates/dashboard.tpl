<header class="page-heading">
    <p class="eyebrow">{{ insights_observability }}</p>
    <h1>{{ insights_title }}</h1>
    <p>{{ insights_introduction }}</p>
</header>
{% include "var/modules/insights/templates/nav.tpl" %}
{% if insights_message %}<p class="status-message" role="status">{{ insights_message }}</p>{% endif %}

<div class="insights-toolbar">
    <form method="get" action="Admin-insights.html">
        <label>{{ insights_period }}<select name="days" data-auto-submit>
            <option value="7" {{ insights_period_7_selected }}>7 {{ insights_days_label }}</option>
            <option value="30" {{ insights_period_30_selected }}>30 {{ insights_days_label }}</option>
            <option value="90" {{ insights_period_90_selected }}>90 {{ insights_days_label }}</option>
            <option value="365" {{ insights_period_365_selected }}>365 {{ insights_days_label }}</option>
        </select></label>
    </form>
    <a class="button button-quiet" href="Admin-insights-export.html?days={{ insights_days }}">{{ insights_export_csv }}</a>
</div>

<p class="status-message" role="status">{{ insights_measurement_note }}</p>
{% if insights_previous_audience_note %}<p>{{ insights_previous_audience_note }}</p>{% endif %}
{% if insights_legacy_note %}<p>{{ insights_legacy_note }}</p>{% endif %}

<section class="admin-section"><h2>{{ insights_cookie_breakdown }}</h2><p>{{ insights_confirmed_pages }} {{ insights_confirmed_pages_label }} · {{ insights_unconfirmed_pages }} {{ insights_unconfirmed_pages_label }}</p><p>{{ insights_cookie_breakdown_help }}</p></section>

<div class="dashboard-grid insights-metrics">
    <section class="metric-card"><span class="metric-value">{{ insights_page_views }}</span><h2>{{ insights_page_views_label }}</h2><p>{{ insights_human_views }}</p></section>
    <section class="metric-card"><span class="metric-value">{% if insights_has_visits == "1" %}{{ insights_visits }}{% endif %}{% if insights_has_visits == "0" %}—{% endif %}</span><h2>{{ insights_visits_label }}</h2><p>{% if insights_has_visits == "1" %}{{ insights_visits_help }}{% endif %}{% if insights_has_visits == "0" %}{{ insights_visits_disabled }}{% endif %}</p></section>
    <section class="metric-card"><span class="metric-value">{{ insights_bots }}</span><h2>{{ insights_bots_label }}</h2><p>{{ insights_bots_help }}</p></section>
    <section class="metric-card"><span class="metric-value">{{ insights_unique_visitors }}</span><h2>{{ insights_unique_visitors_label }}</h2><p>{{ insights_unique_visitors_help }}</p></section>
    <section class="metric-card"><span class="metric-value">{{ insights_new_visitors }}</span><h2>{{ insights_new_visitors_label }}</h2><p>{{ insights_returning_visitors }} {{ insights_returning_visitors_label }}</p></section>
    <section class="metric-card"><span class="metric-value">{{ insights_pages_per_visitor }}</span><h2>{{ insights_pages_per_visitor_label }}</h2><p>{{ insights_avg_unique_day }} {{ insights_avg_unique_day_label }} · {{ insights_avg_views_day }} {{ insights_avg_views_day_label }}</p></section>
</div>

<section class="admin-section insights-wide">
    <h2>{{ insights_evolution }}</h2>
    <ul class="insights-chart" aria-label="{{ insights_chart_label }}">
        {% for insightday in insightday %}
        <li class="insights-bar-row"><time datetime="{{ insightday.day }}">{{ insightday.day }}</time><progress max="100" value="{{ insightday.percent }}">{{ insightday.percent }} %</progress><strong>{{ insightday.views }}</strong></li>
        {% endfor %}
    </ul>
</section>

<div class="insights-columns">
    <section class="admin-section"><h2>{{ insights_top_pages }}</h2><ol class="insights-ranking">{% for insightpage in insightpage %}<li><span><strong>{{ insightpage.title }}</strong><small>{{ insightpage.route }}</small></span><strong>{{ insightpage.views }}</strong></li>{% endfor %}</ol></section>
    <section class="admin-section"><h2>{{ insights_sources }}</h2><p>{{ insights_sources_help }}</p><ol class="insights-ranking">{% for insightreferrer in insightreferrer %}<li><span>{{ insightreferrer.host }}</span><strong>{{ insightreferrer.views }}</strong></li>{% endfor %}</ol></section>
    <section class="admin-section"><h2>{{ insights_devices }}</h2><ol class="insights-ranking">{% for insightdevice in insightdevice %}<li><span>{{ insightdevice.device }}</span><strong>{{ insightdevice.views }}</strong></li>{% endfor %}</ol></section>
</div>
