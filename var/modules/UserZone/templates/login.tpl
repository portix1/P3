<header class="content-heading login-heading">
    <p class="eyebrow">{{ ui_login }}</p>
    <h1>{{ login_title }}</h1>
    <p>{{ login_introduction }}</p>
</header>
<section class="login-page-panel" aria-label="{{ ui_login }}">
    {% include "./users.tpl" %}
</section>
