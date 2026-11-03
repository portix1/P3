<header class="content-heading login-heading">
    <p class="eyebrow">{{ ui_login }}</p>
    <h1>{{ password_reset_title }}</h1>
    <p>{{ password_reset_intro }}</p>
</header>
<section class="login-page-panel" aria-label="{{ password_reset_title }}">
    {% if password_reset_success_message %}
    <p class="status-message" role="status">{{ password_reset_success_message }}</p>
    <p><a class="button" href="login.html">{{ password_reset_back_login }}</a></p>
    {% endif %}
    {% if password_reset_error %}<p class="login-message" role="alert">{{ password_reset_error }}</p>{% endif %}
    {% if password_reset_token_valid == "1" %}
    <form method="post" action="reset-password.html" class="login-form">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
        <input type="hidden" name="reset_token" value="{{ password_reset_token }}" />
        <label for="password-reset-new">{{ password_reset_new }}</label>
        <input type="password" id="password-reset-new" name="pass1" minlength="12" maxlength="4096" autocomplete="new-password" required />
        <label for="password-reset-confirm">{{ password_reset_confirm }}</label>
        <input type="password" id="password-reset-confirm" name="pass2" minlength="12" maxlength="4096" autocomplete="new-password" required />
        <button type="submit">{{ password_reset_submit }}</button>
    </form>
    {% endif %}
    {% if password_reset_token_valid == "0" %}
    <p><a href="forgot-password.html">{{ password_reset_request_new }}</a></p>
    {% endif %}
</section>
