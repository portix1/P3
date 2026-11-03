<header class="page-heading">
    <p class="eyebrow">{{ affiliation_title }}</p>
    <h1>{{ affiliation_form_heading }}</h1>
    <p>{{ affiliation_offer_form_help }}</p>
</header>
{% include "var/modules/affiliation/templates/nav.tpl" %}
{% if affiliation_can_save_offer == "1" %}
<form method="post" action="Admin-affiliation-offers.html" class="settings-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
    <input type="hidden" name="affiliation_action" value="save_offer">
    <input type="hidden" name="id" value="{{ affiliation_form_id }}">
    <div class="form-grid">
        <label><span>{{ affiliation_product }}</span><select name="product_id" required>{% for affproductoption in affproductoption %}<option value="{{ affproductoption.id }}" {{ affproductoption.selected }}>{{ affproductoption.name }}</option>{% endfor %}</select><small>{{ affiliation_offer_product_help }}</small></label>
        <label><span>{{ affiliation_partner }}</span><select name="partner_id" required>{% for affpartneroption in affpartneroption %}<option value="{{ affpartneroption.id }}" {{ affpartneroption.selected }}>{{ affpartneroption.name }}</option>{% endfor %}</select></label>
        <label class="form-grid-wide"><span>{{ affiliation_url }}</span><input type="url" name="affiliate_url" maxlength="2000" required value="{{ affiliation_form_url }}" placeholder="https://"><small>{{ affiliation_offer_url_help }}</small></label>
        <label><span>{{ affiliation_button_label }}</span><input type="text" name="button_label" maxlength="120" value="{{ affiliation_form_label }}"><small>{{ affiliation_button_help }}</small></label>
    </div>
    <label class="checkbox-line"><input type="checkbox" name="enabled" value="1" {{ affiliation_form_enabled }}> <span>{{ affiliation_active }}</span></label>
    <p class="field-help">{{ affiliation_visibility_help }}</p>
    <div class="form-actions"><button type="submit">{{ affiliation_save }}</button> <a class="button button-quiet" href="Admin-affiliation-offers.html">{{ ui_cancel }}</a></div>
</form>
{% else %}
<section class="admin-section user-empty-state">
    <h2>{{ affiliation_offer_prerequisite_title }}</h2>
    <p>{{ affiliation_offer_prerequisite_help }}</p>
    <div class="news-admin-actions"><a class="button button-quiet" href="Admin-affiliation-partners.html">{{ affiliation_add_partner }}</a><a class="button button-quiet" href="Admin-affiliation.html">{{ affiliation_add_product }}</a></div>
</section>
{% endif %}
