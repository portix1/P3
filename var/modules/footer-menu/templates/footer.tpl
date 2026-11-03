<style>
.content-card:has(> .site-footer .portix-footer-menu) {
    padding-bottom: 0 !important;
}
.site-footer:has(.portix-footer-menu) {
    display: flex !important;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-start;
    width: 100% !important;
    max-width: none !important;
    margin: 2rem 0 0 !important;
    padding: 0 !important;
    gap: 0 !important;
    border: 0 !important;
    border-top: 1px solid rgba(120, 88, 62, .24) !important;
    border-radius: 0 !important;
    background: transparent !important;
    box-shadow: none !important;
    backdrop-filter: none !important;
    -webkit-backdrop-filter: none !important;
}
.site-footer:has(.portix-footer-menu) > .portix-footer-menu {
    order: -1;
    flex: 1 0 100%;
}
.site-footer:has(.portix-footer-menu) > span {
    margin: .7rem 0;
}
.site-footer:has(.portix-footer-menu) > span:first-of-type {
    margin-left: 1rem;
}
.site-footer:has(.portix-footer-menu) > span + span {
    margin-left: 1rem;
}
.site-footer:has(.portix-footer-menu) > .footer-login-link {
    margin-left: auto;
    margin-right: .7rem;
}
.portix-footer-menu {
    width: 100%;
    padding: 1.35rem 1rem 1.2rem;
    border-bottom: 1px solid rgba(120, 88, 62, .20);
}
.portix-footer-menu-grid {
    display: grid;
    gap: 1.5rem 2.5rem;
    width: 100%;
}
.portix-footer-menu-columns-1 {
    grid-template-columns: minmax(0, 1fr);
}
.portix-footer-menu-columns-2 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}
.portix-footer-menu-columns-3 {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.portix-footer-menu-column h2 {
    margin: 0 0 .55rem;
    color: inherit;
    font: inherit;
    font-size: .82rem;
    font-weight: 750;
    letter-spacing: .04em;
    text-transform: uppercase;
}
.portix-footer-menu-column ul {
    margin: 0;
    padding: 0;
    list-style: none;
}
.portix-footer-menu-column li + li {
    margin-top: .28rem;
}
.portix-footer-menu-column a {
    color: inherit;
    font-size: .88rem;
    font-weight: 600;
    text-decoration: none;
}
.portix-footer-menu-column a:hover,
.portix-footer-menu-column a:focus-visible {
    text-decoration: underline;
    text-underline-offset: .18em;
}
@media (max-width: 700px) {
    .portix-footer-menu-columns-2,
    .portix-footer-menu-columns-3 {
        grid-template-columns: 1fr;
    }
    .site-footer:has(.portix-footer-menu) > span {
        width: 100%;
        margin: .35rem 1rem 0;
    }
    .site-footer:has(.portix-footer-menu) > span:last-of-type {
        margin-bottom: .5rem;
    }
}
</style>
<nav class="portix-footer-menu" aria-label="{{ footer_menu_aria }}">
    <div class="portix-footer-menu-grid portix-footer-menu-columns-{{ footer_column_count }}">
        {% for column in footer_columns %}
        <section class="portix-footer-menu-column">
            {% if column.title %}<h2>{{ column.title }}</h2>{% endif %}
            <ul>
                {% for link in column.links %}
                <li><a href="{{ link.url }}"{% if link.target_blank == "1" %} target="_blank" rel="noopener noreferrer"{% endif %}>{{ link.label }}</a></li>
                {% endfor %}
            </ul>
        </section>
        {% endfor %}
    </div>
</nav>
