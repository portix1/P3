<header class="page-heading"><p class="eyebrow">{{ ux_configuration }}</p><h1>{{ ux_identity }}</h1></header>
{% if confmessage %}<p class="status-message" role="status">{{ confmessage }}</p>{% endif %}
<form action="Admin-config.html" method="post" class="settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="ux_section" value="identity" />
<div class="form-grid">
    <label>
        <span>{{ maintitle }}</span>
        <input type="text" name="conftitle" value="{{ title }}" required />
        <small>{{ config_title_help }}</small>
    </label>
    <label>
        <span>{{ config_site_url }}</span>
        <input type="url" name="site_url" value="{{ site_url }}" placeholder="https://www.example.com" />
        <small>{{ config_site_url_help }}</small>
    </label>
    <label class="form-grid-wide">
        <span>{{ config_site_description }}</span>
        <textarea name="site_description" maxlength="320" rows="4">{{ site_description }}</textarea>
        <small>{{ config_site_description_help }}</small>
    </label>
    <label>
        <span>{{ language }}</span>
        <select name="langage">
        {% for languages in languages %}
            <option value="{{ languages.name }}" {% if languages.check == "selected" %}selected="selected"{% endif %}>{{ languages.name }}</option>
        {% endfor %}
        </select>
    </label>
    <label>
        <span>{{ defaultpage }}</span>
        <input type="text" name="defpage" value="{{ defaultpg }}" maxlength="512" placeholder="{{ config_default_page_placeholder }}" required />
        <small>{{ config_default_page_help }}</small>
    </label>
</div>
<div class="form-actions"><button type="submit" data-admin-icon="circle-check" title="{{ config_save }}">{{ config_save }}</button></div>
</form>
