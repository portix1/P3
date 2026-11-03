<header class="page-heading">
    <p class="eyebrow">{{ affiliation_title }}</p>
    <h1>{{ affiliation_form_heading }}</h1>
    <p>{{ affiliation_merchant_form_help }}</p>
</header>
{% include "var/modules/affiliation/templates/nav.tpl" %}
<form method="post" action="Admin-affiliation-partners.html" class="settings-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}">
    <input type="hidden" name="affiliation_action" value="save_partner">
    <input type="hidden" name="id" value="{{ affiliation_form_id }}">
    <div class="form-grid">
        <label><span>{{ affiliation_name }}</span><input type="text" name="name" maxlength="160" required value="{{ affiliation_form_name }}" autocomplete="organization"></label>
        <label><span>{{ affiliation_website }}</span><input type="url" name="website_url" maxlength="500" value="{{ affiliation_form_website }}" placeholder="https://"><small>{{ affiliation_partner_website_help }}</small></label>
    </div>
    <label class="checkbox-line"><input type="checkbox" name="enabled" value="1" {{ affiliation_form_enabled }}> <span>{{ affiliation_active }}</span></label>
    <div class="form-actions"><button type="submit">{{ affiliation_save }}</button> <a class="button button-quiet" href="Admin-affiliation-partners.html">{{ ui_cancel }}</a></div>
</form>
