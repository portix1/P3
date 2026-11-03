<header class="page-heading">
    <p class="eyebrow">{{ affiliation_title }}</p>
    <h1>{{ affiliation_offers }}</h1>
    <p>{{ affiliation_intro }}</p>
</header>
{% include "var/modules/affiliation/templates/nav.tpl" %}
{% if affiliation_message %}<p class="status-message" role="status">{{ affiliation_message }}</p>{% endif %}

{% if affiliation_has_offers == "1" %}
<section class="news-admin-summary">
    <p><strong>{{ affiliation_offers_count }}</strong> {{ affiliation_offers_summary }}</p>
    <div class="news-admin-actions"><a class="button" href="Admin-affiliation-newoffer.html">{{ affiliation_add_offer }}</a></div>
</section>
<div class="admin-section table-scroll"><table><caption class="visually-hidden">{{ affiliation_offers }}</caption>
    <thead><tr><th scope="col">{{ affiliation_product }}</th><th scope="col">{{ affiliation_partner }}</th><th scope="col">{{ affiliation_offer_label }}</th><th scope="col">{{ affiliation_state }}</th><th scope="col">{{ affiliation_actions }}</th></tr></thead>
    <tbody>{% for affoffer in affoffer %}
        <tr>
            <td>{{ affoffer.product }}</td>
            <td>{{ affoffer.partner }}</td>
            <td>{{ affoffer.label }}</td>
            <td><span class="{{ affoffer.state_class }}">{{ affoffer.state }}</span></td>
            <td class="table-actions"><a class="button button-quiet" href="Admin-affiliation-offer-{{ affoffer.id }}.html">{{ affiliation_edit }}</a><form method="post" action="Admin-affiliation-offers.html" class="inline-action" data-affiliation-confirm="{{ affiliation_delete_offer_confirm }}"><input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="affiliation_action" value="delete_offer"><input type="hidden" name="id" value="{{ affoffer.id }}"><button class="button-danger" type="submit">{{ affiliation_delete }}</button></form></td>
        </tr>
    {% endfor %}</tbody>
</table></div>
{% else %}
<section class="admin-section user-empty-state">
    <h2>{{ affiliation_no_offers_title }}</h2>
    <p>{{ affiliation_no_offers_body }}</p>
    {% if affiliation_can_save_offer == "1" %}
    <p><a class="button" href="Admin-affiliation-newoffer.html">{{ affiliation_add_offer }}</a></p>
    {% else %}
    <div class="news-admin-actions"><a class="button button-quiet" href="Admin-affiliation-partners.html">{{ affiliation_add_partner }}</a><a class="button button-quiet" href="Admin-affiliation.html">{{ affiliation_add_product }}</a></div>
    {% endif %}
</section>
{% endif %}
