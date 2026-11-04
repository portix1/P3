<header class="page-heading"><p class="eyebrow">{{ ux_configuration }}</p><h1>{{ ux_mail }}</h1></header>
{% if confmessage %}<p class="status-message" role="status">{{ confmessage }}</p>{% endif %}
<form action="Admin-config-mail.html" method="post" class="settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="ux_section" value="mail" />
<div class="form-grid">
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
</div>
<div class="form-actions"><button type="submit" data-admin-icon="circle-check" title="{{ config_save }}">{{ config_save }}</button></div>
</form>
