<h2>{{ modify_personnal }}</h2>
{% if usrmsg %}<p class="notice" role="status">{{ usrmsg }}</p>{% endif %}
{% for warning in warning %}
<p class="error" role="alert">{{ warning.message }}</p>
{% endfor %}

<form method="post" class="profile-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<fieldset>
    <legend>{{ profile_identity }}</legend>
    <div class="profile-form-grid">
        <label for="profile-name">{{ name }}</label>
        <input type="text" id="profile-name" name="name" value="{{ usrname }}" required autocomplete="family-name" />
        <label for="profile-firstname">{{ firstname }}</label>
        <input type="text" id="profile-firstname" name="firstname" value="{{ usrfirstname }}" required autocomplete="given-name" />
        <label for="profile-mail">{{ email }}</label>
        <input type="email" id="profile-mail" name="mail" value="{{ usrmail }}" required autocomplete="email" />
    </div>
</fieldset>
<fieldset>
    <legend>{{ profile_change_password }}</legend>
    <p class="field-help">{{ profile_password_optional }}</p>
    <div class="profile-form-grid">
        <label for="profile-old-password">{{ oldpass }}</label>
        <input type="password" id="profile-old-password" name="oldpass" autocomplete="current-password" />
        <label for="profile-new-password">{{ newpass }}</label>
        <input type="password" id="profile-new-password" name="pass1" minlength="12" autocomplete="new-password" />
        <label for="profile-confirm-password">{{ profile_confirm_password }}</label>
        <input type="password" id="profile-confirm-password" name="pass2" minlength="12" autocomplete="new-password" />
    </div>
</fieldset>
<button type="submit">{{ submit }}</button>
</form>
