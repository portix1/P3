<div class="p3-home" data-home-page data-home-ticket="{{ home_ticket }}" data-home-endpoint="home-events.html">
<h1 class="home-page-title">{{ home_title }}</h1>
{% if home_has_content %}{{ home_html }}{% else %}<p>{{ home_empty }}</p>{% endif %}
</div>
{% if home_tracking_enabled %}<script src="var/modules/home/assets/home.js?v=1.0.0" defer></script>{% endif %}
