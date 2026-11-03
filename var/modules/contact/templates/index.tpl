<header class="content-heading">
    <p class="eyebrow">{{ contact }}</p>
    <h1>{{ contact_public_title }}</h1>
    <p>{{ contact_public_intro }}</p>
</header>
{% if contact_error %}<p class="error-box" role="alert">{{ contact_error }}</p>{% endif %}
<form action="contact.html" method="post" class="contact-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <input type="hidden" name="contact_action" value="send" />
    <label class="contact-honeypot" aria-hidden="true">{{ contact_website }}<input type="text" name="website" tabindex="-1" autocomplete="off" /></label>
    <div class="contact-grid">
        <label><span>{{ yourname }}</span><input type="text" name="name" value="{{ contactname }}" required maxlength="120" autocomplete="name" /></label>
        <label><span>{{ youremail }}</span><input type="email" name="email" value="{{ contactmail }}" required maxlength="254" autocomplete="email" /></label>
    </div>
    <label><span>{{ contact_subject }}</span><input type="text" name="subject" required maxlength="200" /></label>
    <label><span>{{ yourmessage }}</span><textarea name="message" rows="12" maxlength="20000" required></textarea></label>
    <p class="field-help">{{ contact_privacy_notice }}</p>
    <button type="submit">{{ contact_send }}</button>
</form>
