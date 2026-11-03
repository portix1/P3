<header class="page-heading">
    <p class="eyebrow">{{ affiliation_title }}</p>
    <h1>{{ affiliation_merchants }}</h1>
    <p>{{ affiliation_merchants_help }}</p>
</header>
{% include "var/modules/affiliation/templates/nav.tpl" %}
{% if affiliation_message %}<p class="status-message" role="status">{{ affiliation_message }}</p>{% endif %}

{% if affiliation_has_partners == "1" %}
<section class="news-admin-summary"><p><strong>{{ affiliation_partners_count }}</strong> {{ affiliation_merchants_summary }}</p><div class="news-admin-actions"><a class="button" href="Admin-affiliation-newpartner.html">{{ affiliation_add_merchant }}</a></div></section>
<div class="admin-section table-scroll"><table><caption class="visually-hidden">{{ affiliation_merchants }}</caption>
    <thead><tr><th scope="col">{{ affiliation_merchant }}</th><th scope="col">{{ affiliation_website }}</th><th scope="col">{{ affiliation_merchant_links }}</th><th scope="col">{{ affiliation_state }}</th><th scope="col">{{ affiliation_actions }}</th></tr></thead>
    <tbody>{% for affpartner in affpartner %}<tr>
        <td><strong>{{ affpartner.name }}</strong></td>
        <td>{% if affpartner.website_url %}<a href="{{ affpartner.website_url }}" target="_blank" rel="noopener noreferrer">{{ affiliation_open_partner }}</a>{% endif %}</td>
        <td>{{ affpartner.offers }}</td>
        <td><span class="{{ affpartner.state_class }}">{{ affpartner.state }}</span></td>
        <td class="table-actions"><a class="button button-quiet" href="Admin-affiliation-partner-{{ affpartner.id }}.html">{{ affiliation_edit }}</a><form method="post" action="Admin-affiliation-partners.html" class="inline-action" data-affiliation-confirm="{{ affiliation_delete_partner_confirm }}"><input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="affiliation_action" value="delete_partner"><input type="hidden" name="id" value="{{ affpartner.id }}"><button class="button-danger" type="submit">{{ affiliation_delete }}</button></form></td>
    </tr>{% endfor %}</tbody>
</table></div>
{% else %}
<section class="admin-section user-empty-state"><h2>{{ affiliation_no_merchants_title }}</h2><p>{{ affiliation_no_merchants_body }}</p><p><a class="button" href="Admin-affiliation-newpartner.html">{{ affiliation_add_merchant }}</a></p></section>
{% endif %}
