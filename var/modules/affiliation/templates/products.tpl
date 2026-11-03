<header class="page-heading">
    <p class="eyebrow">{{ affiliation_title }}</p>
    <h1>{{ affiliation_products }}</h1>
    <p>{{ affiliation_products_home_help }}</p>
</header>
{% include "var/modules/affiliation/templates/nav.tpl" %}
{% if affiliation_message %}<p class="status-message" role="status">{{ affiliation_message }}</p>{% endif %}

<section class="affiliation-dashboard" aria-label="{{ affiliation_overview }}">
    <div><strong>{{ affiliation_products_count }}</strong><span>{{ affiliation_products }}</span></div>
    <div><strong>{{ affiliation_partners_count }}</strong><span>{{ affiliation_merchants }}</span></div>
    <div><strong>{{ affiliation_offers_count }}</strong><span>{{ affiliation_merchant_links }}</span></div>
    <a class="button" href="Admin-affiliation-newproduct.html">{{ affiliation_add_product }}</a>
</section>

{% if affiliation_has_products == "1" %}
<div class="admin-section table-scroll"><table><caption class="visually-hidden">{{ affiliation_products }}</caption>
    <thead><tr><th scope="col">{{ affiliation_product }}</th><th scope="col">{{ affiliation_articles }}</th><th scope="col">{{ affiliation_merchants }}</th><th scope="col">{{ affiliation_state }}</th><th scope="col">{{ affiliation_actions }}</th></tr></thead>
    <tbody>{% for affproduct in affproduct %}
        <tr>
            <td><strong>{{ affproduct.name }}</strong></td>
            <td>{{ affproduct.articles }}</td>
            <td>{{ affproduct.offers }}</td>
            <td><span class="{{ affproduct.state_class }}">{{ affproduct.state }}</span></td>
            <td class="table-actions"><a class="button button-quiet" href="Admin-affiliation-product-{{ affproduct.id }}.html">{{ affiliation_manage }}</a><form method="post" action="Admin-affiliation.html" class="inline-action" data-affiliation-confirm="{{ affiliation_delete_product_confirm }}"><input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="affiliation_action" value="delete_product"><input type="hidden" name="id" value="{{ affproduct.id }}"><button class="button-danger" type="submit">{{ affiliation_delete }}</button></form></td>
        </tr>
    {% endfor %}</tbody>
</table></div>
{% else %}
<section class="admin-section user-empty-state">
    <h2>{{ affiliation_no_products_title }}</h2>
    <p>{{ affiliation_no_products_body_simple }}</p>
    <p><a class="button" href="Admin-affiliation-newproduct.html">{{ affiliation_add_product }}</a></p>
</section>
{% endif %}
