<header class="page-heading"><h1>{{ articles_series }}</h1></header>
{% if articles_message %}<p role="status">{{ articles_message }}</p>{% endif %}
<form method="post" action="{{ series_action }}" data-unsaved-warning="1">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}">
<label>{{ articles_field_title }}<input name="title" required maxlength="250" value="{{ series_title_value }}"></label>
<label>{{ articles_series_description }}<textarea name="description" maxlength="2000" rows="3">{{ series_description_value }}</textarea></label>
<label><input type="checkbox" name="show_title" value="1" {{ series_show_title_checked }}>{{ articles_series_show_title }}</label>
<p>{{ articles_series_order_help }}</p>
<div class="article-picker" data-article-picker>
<div class="article-picker-controls" data-picker-controls hidden>
<label>{{ articles_picker_search }}<input type="search" data-picker-search autocomplete="off" placeholder="{{ articles_picker_search_hint }}"></label>
<label>{{ articles_picker_category }}<select data-picker-category-filter><option value="">{{ articles_picker_all_categories }}</option></select></label>
<p data-picker-count role="status" aria-live="polite" data-format="{{ articles_picker_count }}"></p>
</div>
<ol data-series-order>{% for seriesarticle in seriesarticle %}<li data-picker-row data-picker-title="{{ seriesarticle.title }}" data-picker-category="{{ seriesarticle.category }}" data-picker-category-id="{{ seriesarticle.category_id }}">
<label><input type="checkbox" name="parts[]" value="{{ seriesarticle.id }}" {{ seriesarticle.checked }}>{{ seriesarticle.title }} <small>{{ seriesarticle.category }} · {{ seriesarticle.status }}</small></label>
<span class="article-picker-order" data-picker-order>
<label>{{ articles_series_position }}<input type="number" name="positions[{{ seriesarticle.id }}]" min="0" value="{{ seriesarticle.position }}"></label>
<button type="button" data-series-move="up" title="{{ articles_series_up }}" aria-label="{{ articles_series_up }}">↑</button>
<button type="button" data-series-move="down" title="{{ articles_series_down }}" aria-label="{{ articles_series_down }}">↓</button>
</span></li>{% endfor %}</ol>
<button type="button" data-picker-more hidden>{{ articles_picker_more }}</button>
</div>
<button type="submit">{{ ui_save }}</button> <a href="Admin-articles-series.html">{{ ui_cancel }}</a>
</form>
<link rel="stylesheet" href="var/modules/articles/article-picker.css?v={{ articles_asset_version }}">
<script src="var/modules/articles/article-picker.js?v={{ articles_asset_version }}" defer></script>
<script src="var/modules/articles/series-order.js?v={{ articles_asset_version }}" defer></script>
