<header class="page-heading">
    <p class="eyebrow">{{ footer_admin_eyebrow }}</p>
    <h1>{{ footer_admin_title }}</h1>
    <p>{{ footer_admin_help }}</p>
</header>

{% if footer_message %}<p class="status-message" role="status">{{ footer_message }}</p>{% endif %}

<form method="post" action="Admin-footer.html" class="footer-menu-admin-form" id="footer-menu-admin-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <input type="hidden" name="footer_action" value="save" />

    <section class="admin-section footer-layout-settings">
        <h2>{{ footer_layout_title }}</h2>
        <label>
            <span>{{ footer_column_count_label }}</span>
            <select name="column_count" id="footer-column-count">
                <option value="2" {{ footer_columns_2_selected }}>2</option>
                <option value="3" {{ footer_columns_3_selected }}>3</option>
            </select>
        </label>
        <p class="field-help">{{ footer_column_count_help }}</p>
    </section>

    <section class="admin-section">
        <div class="footer-links-heading">
            <div>
                <h2>{{ footer_links_title }}</h2>
                <p class="field-help">{{ footer_drag_help }}</p>
            </div>
        </div>

        <div class="footer-admin-columns" id="footer-admin-columns" data-column-count="{{ footer_column_count }}">
            {% for column in footer_admin_columns %}
            <section class="footer-admin-column" data-footer-column="{{ column.id }}" data-column-position="{{ column.position }}"{% if column.hidden == "1" %} hidden{% endif %}>
                <header class="footer-admin-column-header">
                    <label class="footer-column-title-field">
                        <input type="hidden" name="column_id[]" value="{{ column.id }}" />
                        <span>{{ footer_column_label }} {{ column.position }} — {{ footer_column_title_label }}</span>
                        <input type="text" name="column_title[]" maxlength="100" value="{{ column.title }}" />
                    </label>
                    <button type="button" class="footer-add-link-button" data-footer-add="{{ column.id }}">+ {{ footer_add_link }}</button>
                </header>

                <div class="footer-link-dropzone" data-footer-dropzone="{{ column.id }}">
                    {% for link in column.links %}
                    <article class="footer-admin-link-row" data-footer-link data-link-key="{{ link.key }}">
                        <div class="footer-drag-handle" draggable="true" data-footer-drag-handle title="{{ footer_drag_handle }}" aria-label="{{ footer_drag_handle }}">⋮⋮</div>
                        <input type="hidden" name="links[{{ link.key }}][id]" value="{{ link.id }}" />
                        <input type="hidden" name="links[{{ link.key }}][column_id]" value="{{ column.id }}" data-footer-column-input />
                        <input type="hidden" name="links[{{ link.key }}][position]" value="{{ link.position }}" data-footer-position-input />
                        <input type="hidden" name="links[{{ link.key }}][delete]" value="0" data-footer-delete-input />

                        <label class="footer-link-label">
                            <span>{{ footer_label }}</span>
                            <input type="text" name="links[{{ link.key }}][label]" maxlength="140" value="{{ link.label }}" required />
                        </label>

                        <label class="footer-link-url">
                            <span>{{ footer_url }}</span>
                            <input type="text" name="links[{{ link.key }}][url]" value="{{ link.url }}" required />
                        </label>

                        <div class="footer-link-options">
                            <label class="footer-check">
                                <input type="hidden" name="links[{{ link.key }}][enabled]" value="0" />
                                <input type="checkbox" name="links[{{ link.key }}][enabled]" value="1" {{ link.enabled_checked }} />
                                {{ footer_visible }}
                            </label>
                            <label class="footer-check">
                                <input type="hidden" name="links[{{ link.key }}][target_blank]" value="0" />
                                <input type="checkbox" name="links[{{ link.key }}][target_blank]" value="1" {{ link.target_blank_checked }} />
                                {{ footer_new_tab }}
                            </label>
                        </div>

                        <button type="button" class="footer-remove-link-button" data-footer-remove>{{ ui_delete }}</button>
                    </article>
                    {% endfor %}
                </div>
            </section>
            {% endfor %}
        </div>
    </section>

    <div class="form-actions"><button type="submit">{{ footer_save }}</button></div>
</form>

<template id="footer-link-template">
    <article class="footer-admin-link-row is-new" data-footer-link data-link-key="__KEY__">
        <div class="footer-drag-handle" draggable="true" data-footer-drag-handle title="{{ footer_drag_handle }}" aria-label="{{ footer_drag_handle }}">⋮⋮</div>
        <input type="hidden" name="links[__KEY__][id]" value="0" />
        <input type="hidden" name="links[__KEY__][column_id]" value="__COLUMN__" data-footer-column-input />
        <input type="hidden" name="links[__KEY__][position]" value="0" data-footer-position-input />
        <input type="hidden" name="links[__KEY__][delete]" value="0" data-footer-delete-input />

        <label class="footer-link-label">
            <span>{{ footer_label }}</span>
            <input type="text" name="links[__KEY__][label]" maxlength="140" value="" required />
        </label>

        <label class="footer-link-url">
            <span>{{ footer_url }}</span>
            <input type="text" name="links[__KEY__][url]" value="" placeholder="contact.html" required />
        </label>

        <div class="footer-link-options">
            <label class="footer-check">
                <input type="hidden" name="links[__KEY__][enabled]" value="0" />
                <input type="checkbox" name="links[__KEY__][enabled]" value="1" checked />
                {{ footer_visible }}
            </label>
            <label class="footer-check">
                <input type="hidden" name="links[__KEY__][target_blank]" value="0" />
                <input type="checkbox" name="links[__KEY__][target_blank]" value="1" />
                {{ footer_new_tab }}
            </label>
        </div>

        <button type="button" class="footer-remove-link-button" data-footer-remove>{{ ui_delete }}</button>
    </article>
</template>

<style>
.footer-menu-admin-form { display: grid; gap: 1rem; }
.footer-layout-settings > label { display: grid; max-width: 15rem; gap: .3rem; }
.footer-links-heading { display: flex; align-items: end; justify-content: space-between; gap: 1rem; }
.footer-admin-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    align-items: start;
}
.footer-admin-columns[data-column-count="3"] {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.footer-admin-column {
    min-width: 0;
    padding: .8rem;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--surface-soft);
}
.footer-admin-column[hidden] { display: none; }
.footer-admin-column-header {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: .7rem;
    margin-bottom: .7rem;
}
.footer-column-title-field { display: grid; min-width: 0; flex: 1 1 auto; gap: .3rem; }
.footer-add-link-button {
    flex: 0 0 auto;
    min-height: 36px;
    padding: .35rem .65rem;
}
.footer-link-dropzone {
    min-height: 4.5rem;
    display: grid;
    align-content: start;
    gap: .55rem;
    padding: .25rem;
    border: 1px dashed transparent;
    border-radius: 8px;
    transition: border-color .12s ease, background .12s ease;
}
.footer-link-dropzone.is-drag-over {
    border-color: var(--brand);
    background: rgba(255,255,255,.5);
}
.footer-admin-link-row {
    position: relative;
    display: grid;
    grid-template-columns: 1.4rem minmax(0, 1fr);
    gap: .55rem .65rem;
    padding: .7rem;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: var(--surface);
    box-shadow: 0 2px 7px rgba(0,0,0,.04);
}
.footer-admin-link-row.is-dragging { opacity: .45; }
.footer-admin-link-row.is-deleted { display: none; }
.footer-drag-handle {
    grid-row: 1 / span 4;
    align-self: stretch;
    display: grid;
    place-items: center;
    color: var(--muted);
    cursor: grab;
    user-select: none;
    touch-action: none;
}
.footer-admin-link-row:active .footer-drag-handle { cursor: grabbing; }
.footer-link-label,
.footer-link-url { display: grid; gap: .25rem; }
.footer-link-options { display: flex; flex-wrap: wrap; gap: .35rem 1rem; }
.footer-check { display: inline-flex; align-items: center; gap: .35rem; }
.footer-remove-link-button {
    justify-self: start;
    min-height: 32px;
    padding: .3rem .55rem;
    border-color: var(--danger);
    background: transparent;
    color: var(--danger);
}
.footer-remove-link-button:hover { background: var(--danger); color: #fff; }
@media (max-width: 1100px) {
    .footer-admin-columns { grid-template-columns: 1fr; }
}
@media (max-width: 600px) {
    .footer-admin-column-header { align-items: stretch; flex-direction: column; }
    .footer-add-link-button { width: 100%; }
}
</style>

<script src="var/modules/footer-menu/admin-footer.js?v={{ asset_version }}" defer></script>

