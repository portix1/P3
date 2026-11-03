<p class="account-greeting"><strong>{{ hello }} {{ username }}</strong></p>
<p class="account-link"><a href="personnal.html">{{ modify_personnal }}</a></p>
<form method="post" action="disconnect.html" class="account-logout-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<button type="submit">{{ logout }}</button>
</form>
