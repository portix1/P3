<style>@import url("var/modules/affiliation/assets/admin.css?v={{ asset_version }}");</style>
<script src="var/modules/affiliation/assets/admin.js?v={{ asset_version }}&amp;affiliation=1.1.2" defer></script>
<nav class="module-tabs" aria-label="{{ affiliation_navigation }}">
    <a class="{{ affiliation_products_current }}"{% if affiliation_products_current == "is-current" %} aria-current="page"{% endif %} href="Admin-affiliation.html">{{ affiliation_products }} <span>({{ affiliation_products_count }})</span></a>
    <a class="{{ affiliation_partners_current }}"{% if affiliation_partners_current == "is-current" %} aria-current="page"{% endif %} href="Admin-affiliation-partners.html">{{ affiliation_merchants }} <span>({{ affiliation_partners_count }})</span></a>
</nav>
