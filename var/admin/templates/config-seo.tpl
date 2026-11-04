<header class="page-heading"><p class="eyebrow">{{ ux_configuration }}</p><h1>{{ ux_seo }}</h1></header>
{% if confmessage %}<p class="status-message" role="status">{{ confmessage }}</p>{% endif %}
<form action="Admin-config-seo.html" method="post" class="settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="ux_section" value="seo" />
<div class="form-grid">
    <label class="form-grid-wide" for="seo-home-title">
        <span>{{ config_seo_home_title }}</span>
        <input id="seo-home-title" type="text" name="seo_home_title" value="{{ seo_home_title }}" maxlength="180" aria-describedby="seo-home-title-help" />
        <small id="seo-home-title-help">{{ config_seo_home_title_help }}</small>
    </label>
    <label class="form-grid-wide" for="seo-home-description">
        <span>{{ config_seo_home_description }}</span>
        <textarea id="seo-home-description" name="seo_home_description" maxlength="170" rows="3" aria-describedby="seo-home-description-help">{{ seo_home_description }}</textarea>
        <small id="seo-home-description-help">{{ config_seo_home_description_help }}</small>
    </label>
    <label>
        <span>{{ config_seo_default_author }}</span>
        <input type="text" name="seo_default_author" value="{{ seo_default_author }}" maxlength="160" />
        <small>{{ config_seo_default_author_help }}</small>
    </label>
    <label>
        <span>{{ config_seo_publisher_name }}</span>
        <input type="text" name="seo_publisher_name" value="{{ seo_publisher_name }}" maxlength="160" />
        <small>{{ config_seo_publisher_name_help }}</small>
    </label>
    <label class="form-grid-wide">
        <span>{{ config_seo_publisher_logo }}</span>
        <input type="text" name="seo_publisher_logo" value="{{ seo_publisher_logo }}" maxlength="500" placeholder="var/uploads/images/logo.webp" />
        <small>{{ config_seo_publisher_logo_help }}</small>
    </label>
    <label class="form-grid-wide">
        <span>{{ config_seo_default_image }}</span>
        <input type="text" name="seo_default_image" value="{{ seo_default_image }}" maxlength="500" placeholder="var/uploads/images/social.webp" />
        <small>{{ config_seo_default_image_help }}</small>
    </label>
</div>
<div class="form-actions"><button type="submit" data-admin-icon="circle-check" title="{{ config_save }}">{{ config_save }}</button></div>
</form>
