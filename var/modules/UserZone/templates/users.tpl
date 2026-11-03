<form method="post" action="" class="login-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
{% if login_message %}<p class="login-message" role="alert">{{ login_message }}</p>{% endif %}
<label for="login-pseudo">{{ pseudo }}</label>
<input name="pseudo" id="login-pseudo" class="usrfield" autocomplete="username" required />
<label for="login-password">{{ pswd }}</label>
<input type="password" id="login-password" name="pass" class="passfield" autocomplete="current-password" required />
<button type="submit">{{ submit }}</button>
<p class="login-help"><a href="forgot-password.html">{{ password_forgot_link }}</a></p>
</form>
