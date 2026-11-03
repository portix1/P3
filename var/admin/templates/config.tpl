<header class="page-heading">
    <p class="eyebrow">{{ config_site_settings }}</p>
    <h1>{{ config }}</h1>
    <p>{{ config_introduction }}</p>
</header>

{% if confmessage %}
<p class="status-message" role="status">{{ confmessage }}</p>
{% endif %}

<section id="extension-updates" aria-labelledby="extension-updates-title">
    <h2 id="extension-updates-title">{{ extension_updates_heading }}</h2>
    <form action="Admin-config.html#extension-updates" method="post">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
        <button type="submit" name="check_extension_updates" value="1">{{ extension_updates_check }}</button>
    </form>
    {% if extension_updates_status %}
    <p class="status-message" role="status">{{ extension_updates_status }}</p>
    {% endif %}
    {% for update in extensionupdate %}
    <p>{{ update.name }} ({{ update.installed }} → {{ update.version }}) · <a href="{{ update.url }}">{{ extension_updates_download }}</a></p>
    {% endfor %}
</section>

<form action="Admin-config.html" method="post" class="settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
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
    <label>
        <span>{{ config_mail_sender_name }}</span>
        <input type="text" name="mail_sender_name" value="{{ mail_sender_name }}" maxlength="120" />
        <small>{{ config_mail_sender_name_help }}</small>
    </label>
    <label>
        <span>{{ config_mail_sender_email }}</span>
        <input type="email" name="mail_sender_email" value="{{ mail_sender_email }}" maxlength="254" />
        <small>{{ config_mail_sender_email_help }}</small>
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
        <span>{{ template }}</span>
        <select name="templ">
        {% for templates in templates %}
            <option value="{{ templates.name }}" {% if templates.checked == "selected" %}selected="selected"{% endif %}>{{ templates.label }}</option>
        {% endfor %}
        </select>
        <small>{{ config_theme_help }}</small>
    </label>
    <label>
        <span>{{ defaultpage }}</span>
        <input type="text" name="defpage" value="{{ defaultpg }}" maxlength="512" placeholder="{{ config_default_page_placeholder }}" required />
        <small>{{ config_default_page_help }}</small>
    </label>
</div>
<div class="form-actions"><button type="submit">{{ config_save }}</button></div>
</form>

<p><a class="button" href="Admin-config-themes.html">{{ themes_manage }}</a></p>
