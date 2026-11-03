<header class="content-heading login-heading">
    <p class="eyebrow">{{ ui_login }}</p>
    <h1>{{ password_forgot_title }}</h1>
    <p>{{ password_forgot_intro }}</p>
</header>
<section class="login-page-panel" aria-label="{{ password_forgot_title }}">
    {% if password_forgot_message %}<p class="status-message" role="status">{{ password_forgot_message }}</p>{% endif %}
    {% if password_forgot_error %}<p class="login-message" role="alert">{{ password_forgot_error }}</p>{% endif %}
    <form method="post" action="forgot-password.html" class="login-form">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
        <label for="password-forgot-email">{{ password_forgot_email }}</label>
        <input type="email" id="password-forgot-email" name="email" autocomplete="email" required maxlength="254" />
        <button type="submit">{{ password_forgot_submit }}</button>
    </form>
    <p><a href="login.html">{{ password_reset_back_login }}</a></p>
</section>
