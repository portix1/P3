<header class="page-heading">
    <p class="eyebrow">{{ affiliation_title }}</p>
    <h1>{{ affiliation_form_heading }}</h1>
    <p>{{ affiliation_product_workspace_help }}</p>
</header>
{% include "var/modules/affiliation/templates/nav.tpl" %}
{% if affiliation_message %}<p class="status-message" role="status">{{ affiliation_message }}</p>{% endif %}

<form method="post" action="Admin-affiliation.html" class="settings-form affiliation-product-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
    <input type="hidden" name="affiliation_action" value="save_product">
    <input type="hidden" name="id" value="{{ affiliation_form_id }}">

    <section class="affiliation-form-section">
        <h2>{{ affiliation_product_details }}</h2>
        <div class="form-grid">
            <label><span>{{ affiliation_name }}</span><input type="text" name="name" maxlength="200" required value="{{ affiliation_form_name }}" autocomplete="off"></label>
            <label><span>{{ affiliation_image }}</span><input type="text" name="image_url" maxlength="500" value="{{ affiliation_form_image }}"><small>{{ affiliation_image_help }}</small></label>
        </div>
        <label><span>{{ affiliation_description }}</span><textarea name="description" rows="4" maxlength="1000">{{ affiliation_form_description }}</textarea><small>{{ affiliation_product_description_help }}</small></label>
        <label class="checkbox-line"><input type="checkbox" name="enabled" value="1" {{ affiliation_form_enabled }}> <span>{{ affiliation_active }}</span></label>
    </section>

    <section class="affiliation-form-section">
        <div class="affiliation-section-heading"><div><h2>{{ affiliation_display_in_articles }}</h2><p>{{ affiliation_article_selector_help }}</p></div></div>
        {% if affiliation_has_articles == "1" %}
        <label class="affiliation-filter"><span>{{ affiliation_filter_articles }}</span><input type="search" data-affiliation-article-filter placeholder="{{ affiliation_filter_articles_placeholder }}"></label>
        <div class="checkbox-list affiliation-article-list" data-affiliation-article-list>{% for affarticleoption in affarticleoption %}<label data-affiliation-article="{{ affarticleoption.title }} {{ affarticleoption.category_path }}"><input type="checkbox" name="article_ids[]" value="{{ affarticleoption.id }}" {{ affarticleoption.checked }}> <span><strong>{{ affarticleoption.title }}</strong><small>{% if affarticleoption.category_path %}{{ affarticleoption.category_path }} · {% endif %}{{ affarticleoption.status }}</small></span></label>{% endfor %}</div>
        {% else %}<p>{{ affiliation_articles_none }}</p><p><a class="button button-quiet" href="Admin-articles.html">{{ affiliation_create_article }}</a></p>{% endif %}
    </section>

    <div class="form-actions affiliation-sticky-actions"><button type="submit">{{ affiliation_save_product }}</button> <a class="button button-quiet" href="Admin-affiliation.html">{{ ui_cancel }}</a></div>
</form>

<section class="affiliation-form-section affiliation-merchants-section">
    <div class="affiliation-section-heading"><div><h2>{{ affiliation_where_to_buy }}</h2><p>{{ affiliation_where_to_buy_help }}</p></div><strong>{{ affiliation_product_offer_count }}</strong></div>
    {% if affiliation_product_exists == "0" %}
        <div class="affiliation-inline-notice"><p>{{ affiliation_save_before_links }}</p></div>
    {% else %}
        {% if affiliation_product_has_offers == "1" %}
        <div class="affiliation-offer-list">{% for affproductoffer in affproductoffer %}
            <article class="affiliation-offer-card">
                <div class="affiliation-offer-card-title"><strong>{{ affproductoffer.partner }}</strong><span>{% if affproductoffer.enabled %}{{ affiliation_enabled }}{% else %}{{ affiliation_disabled }}{% endif %}</span></div>
                <form method="post" action="Admin-affiliation.html" class="affiliation-offer-form">
                    <input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="affiliation_action" value="save_offer"><input type="hidden" name="id" value="{{ affproductoffer.id }}"><input type="hidden" name="product_id" value="{{ affproductoffer.product_id }}"><input type="hidden" name="partner_id" value="{{ affproductoffer.partner_id }}">
                    <label class="form-grid-wide"><span>{{ affiliation_url }}</span><input type="url" name="affiliate_url" maxlength="2000" required value="{{ affproductoffer.url }}"></label>
                    <label><span>{{ affiliation_button_label }}</span><input type="text" name="button_label" maxlength="120" value="{{ affproductoffer.label }}"><small>{{ affiliation_button_help }}</small></label>
                    <label class="checkbox-line"><input type="checkbox" name="enabled" value="1" {{ affproductoffer.enabled }}> <span>{{ affiliation_active }}</span></label>
                    <button type="submit">{{ affiliation_save_link }}</button>
                </form>
                <form method="post" action="Admin-affiliation.html" class="affiliation-offer-delete" data-affiliation-confirm="{{ affiliation_delete_offer_confirm }}"><input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="affiliation_action" value="delete_offer"><input type="hidden" name="id" value="{{ affproductoffer.id }}"><button class="button-danger" type="submit">{{ affiliation_delete_link }}</button></form>
            </article>
        {% endfor %}</div>
        {% endif %}

        {% if affiliation_has_partners == "1" %}
        <details class="affiliation-add-link"{% if affiliation_product_has_offers == "0" %} open{% endif %}>
            <summary>{{ affiliation_add_merchant_link }}</summary>
            <form method="post" action="Admin-affiliation.html" class="settings-form affiliation-offer-form">
                <input type="hidden" name="csrf_token" value="{{ csrf_token }}"><input type="hidden" name="affiliation_action" value="save_offer"><input type="hidden" name="product_id" value="{{ affiliation_form_id }}">
                <div class="form-grid"><label><span>{{ affiliation_merchant }}</span><select name="partner_id" required>{% for affpartneroption in affpartneroption %}<option value="{{ affpartneroption.id }}">{{ affpartneroption.name }}</option>{% endfor %}</select></label><label><span>{{ affiliation_button_label }}</span><input type="text" name="button_label" maxlength="120"><small>{{ affiliation_button_help }}</small></label></div>
                <label><span>{{ affiliation_url }}</span><input type="url" name="affiliate_url" maxlength="2000" required placeholder="https://"><small>{{ affiliation_offer_url_help }}</small></label>
                <label class="checkbox-line"><input type="checkbox" name="enabled" value="1" checked> <span>{{ affiliation_active }}</span></label>
                <button type="submit">{{ affiliation_add_link }}</button>
            </form>
        </details>
        {% else %}
        <div class="affiliation-inline-notice"><p>{{ affiliation_need_merchant }}</p><a class="button button-quiet" href="Admin-affiliation-newpartner.html">{{ affiliation_add_merchant }}</a></div>
        {% endif %}
    {% endif %}
</section>
<p class="field-help">{{ affiliation_visibility_help }}</p>
