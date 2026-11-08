{{ home_style_html }}
<div class="p3-home" data-home-page data-home-ticket="{{ home_ticket }}" data-home-endpoint="home-events.html">
{% if home_has_content %}<div class="home-zones">{{ home_html }}</div>{% else %}<p>{{ home_empty }}</p>{% endif %}
</div>
{% if home_tracking_enabled %}<script src="var/modules/home/assets/home.js?v=1.0.6" defer></script>{% endif %}
