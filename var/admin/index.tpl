<!doctype html>
<html lang="{{ language_code }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="color-scheme" content="light" />
<base href="{{ base_url }}/" />
<title>{{ ui_admin }} — {{ title }}</title>
<link rel="stylesheet" href="var/admin/style.css?v={{ asset_version }}" media="screen" />
<link rel="stylesheet" href="var/admin/icons.css?v={{ asset_version }}&amp;icons=2" media="screen" />
<script src="var/editor/portix-editor.js?v={{ asset_version }}&amp;media=4" defer></script>
<script src="var/editor/portix-ui.js?v={{ asset_version }}" defer></script>
<script src="var/admin/icons.js?v={{ asset_version }}&amp;icons=2" defer></script>
</head>
<body class="{{ admin_area_class }}" data-portix-media="{{ media_enabled }}" data-media-csrf="{{ csrf_token }}"
    data-editor-invalid-image-format="{{ editor_invalid_image_format }}"
    data-editor-image-too-large="{{ editor_image_too_large }}"
    data-editor-incomplete-form="{{ editor_incomplete_form }}"
    data-editor-uploading-image="{{ editor_uploading_image }}"
    data-editor-invalid-response="{{ editor_invalid_response }}"
    data-editor-upload-failed="{{ editor_upload_failed }}"
    data-editor-images-added="{{ editor_images_added }}"
    data-editor-image-added="{{ editor_image_added }}"
    data-editor-image-failed="{{ editor_image_failed }}"
    data-editor-wait-upload="{{ editor_wait_upload }}"
    data-editor-text-color="{{ editor_text_color }}"
    data-editor-image-options="{{ editor_image_options }}"
    data-editor-image-size="{{ editor_image_size }}"
    data-editor-image-size-auto="{{ editor_image_size_auto }}"
    data-editor-image-alignment="{{ editor_image_alignment }}"
    data-editor-image-align-none="{{ editor_image_align_none }}"
    data-editor-image-align-left="{{ editor_image_align_left }}"
    data-editor-image-align-center="{{ editor_image_align_center }}"
    data-editor-image-align-right="{{ editor_image_align_right }}"
    data-editor-image-alt="{{ editor_image_alt }}"
    data-editor-image-caption="{{ editor_image_caption }}"
    data-editor-image-move="{{ editor_image_move }}"
    data-editor-image-move-up="{{ editor_image_move_up }}"
    data-editor-image-move-down="{{ editor_image_move_down }}"
    data-editor-toolbar="{{ editor_toolbar }}"
    data-editor-heading="{{ editor_heading }}"
    data-editor-size="{{ editor_size }}"
    data-editor-bold="{{ editor_bold }}"
    data-editor-italic="{{ editor_italic }}"
    data-editor-underline="{{ editor_underline }}"
    data-editor-strike="{{ editor_strike }}"
    data-editor-numbered-list="{{ editor_numbered_list }}"
    data-editor-bullet-list="{{ editor_bullet_list }}"
    data-editor-quote="{{ editor_quote }}"
    data-editor-link="{{ editor_link }}"
    data-editor-link-new-window="{{ editor_link_new_window }}"
    data-editor-image="{{ editor_image }}"
    data-editor-alignment="{{ editor_alignment }}"
    data-editor-clear-formatting="{{ editor_clear_formatting }}"
 data-editor-rich-tools="{{ editor_rich_tools }}"
 data-editor-table="{{ editor_table }}"
 data-editor-table-insert="{{ editor_table_insert }}"
 data-editor-table-rows="{{ editor_table_rows }}"
 data-editor-table-columns="{{ editor_table_columns }}"
 data-editor-table-tools="{{ editor_table_tools }}"
 data-editor-table-row-above="{{ editor_table_row_above }}"
 data-editor-table-row-below="{{ editor_table_row_below }}"
 data-editor-table-column-left="{{ editor_table_column_left }}"
 data-editor-table-column-right="{{ editor_table_column_right }}"
 data-editor-table-delete-row="{{ editor_table_delete_row }}"
 data-editor-table-delete-column="{{ editor_table_delete_column }}"
 data-editor-table-delete="{{ editor_table_delete }}"
 data-editor-formula="{{ editor_formula }}"
 data-editor-formula-title="{{ editor_formula_title }}"
 data-editor-formula-latex="{{ editor_formula_latex }}"
 data-editor-formula-display="{{ editor_formula_display }}"
 data-editor-formula-preview="{{ editor_formula_preview }}"
 data-editor-formula-unavailable="{{ editor_formula_unavailable }}"
 data-editor-video="{{ editor_video }}"
 data-editor-video-title="{{ editor_video_title }}"
 data-editor-video-help="{{ editor_video_help }}"
 data-editor-video-url="{{ editor_video_url }}"
 data-editor-video-label="{{ editor_video_label }}"
 data-editor-video-size="{{ editor_video_size }}"
 data-editor-video-invalid="{{ editor_video_invalid }}"
 data-editor-video-frame-title="{{ editor_video_frame_title }}"
 data-editor-markdown="{{ editor_markdown }}"
 data-editor-markdown-title="{{ editor_markdown_title }}"
 data-editor-markdown-help="{{ editor_markdown_help }}"
 data-editor-markdown-insert="{{ editor_markdown_insert }}"
 data-editor-insert="{{ editor_insert }}"
 data-editor-update="{{ editor_update }}"
 data-editor-cancel="{{ editor_cancel }}"
    data-editor-font="{{ editor_font }}"
    data-editor-header="{{ editor_header }}"
    data-editor-background="{{ editor_background }}"
    data-editor-script="{{ editor_script }}"
    data-editor-list="{{ editor_list }}"
    data-editor-indent="{{ editor_indent }}"
    data-editor-direction="{{ editor_direction }}"
    data-editor-blockquote="{{ editor_blockquote }}"
    data-editor-code="{{ editor_code }}"
    data-editor-code-block="{{ editor_code_block }}"
    data-editor-align="{{ editor_align }}"
    data-editor-clean="{{ editor_clean }}"
    data-editor-undo="{{ editor_undo }}"
    data-editor-redo="{{ editor_redo }}"
    data-editor-divider="{{ editor_divider }}"
    data-editor-table-header-row="{{ editor_table_header_row }}"
    data-editor-table-header-first-row="{{ editor_table_header_first_row }}"
    data-editor-table-width="{{ editor_table_width }}"
    data-editor-table-cell-header="{{ editor_table_cell_header }}"
    data-editor-cell-vertical="{{ editor_cell_vertical }}"
    data-editor-cell-top="{{ editor_cell_top }}"
    data-editor-cell-middle="{{ editor_cell_middle }}"
    data-editor-cell-bottom="{{ editor_cell_bottom }}"
    data-editor-image-width="{{ editor_image_width }}"
    data-editor-image-drag="{{ editor_image_drag }}"
    data-editor-image-wrap-square="{{ editor_image_wrap_square }}"
    data-editor-image-wrap-none="{{ editor_image_wrap_none }}"
    data-editor-image-wrapping="{{ editor_image_wrapping }}"
    data-editor-image-resize="{{ editor_image_resize }}"
    data-editor-video-edit-hint="{{ editor_video_edit_hint }}"
    data-editor-checked-list="{{ editor_checked_list }}"
    data-editor-sub="{{ editor_sub }}"
    data-editor-super="{{ editor_super }}"
    data-editor-indent-more="{{ editor_indent_more }}"
    data-editor-indent-less="{{ editor_indent_less }}"
    data-editor-link-save="{{ editor_link_save }}"
    data-editor-link-remove="{{ editor_link_remove }}"
    data-news-delete-prefix="{{ news_delete_fallback_prefix }}"
    data-news-delete-suffix="{{ news_delete_fallback_suffix }}"
    data-news-delete-title="{{ news_delete_fallback_title }}"
    data-diagnostics-copied="{{ diagnostics_copied }}"
    data-diagnostics-copy-failed="{{ diagnostics_copy_failed }}"
>
<a class="skip-link" href="#admin-content">{{ ui_skip_content }}</a>

<header class="admin-topbar">
    <a class="admin-brand" href="Admin.html" aria-label="{{ ui_admin_dashboard_aria }}">
        <span class="admin-brand-mark" aria-hidden="true">P<sup>3</sup></span>
        <span><strong>P3</strong><small>{{ ui_admin_site }} {{ title }}</small></span>
    </a>
    {% if can_access_admin == "1" %}
    <nav class="ux-area-shortcuts" aria-label="{{ ux_navigation }}">
        <a class="button action-content {{ ux_writing_current }}"{% if ux_writing_current == "is-current" %} aria-current="page"{% endif %} href="Admin-writing.html" data-admin-icon="pencil">{{ ux_writing }}</a>
        {% if is_admin == "1" %}
        <a class="button action-management {{ ux_management_current }}"{% if ux_management_current == "is-current" %} aria-current="page"{% endif %} href="Admin-management.html" data-admin-icon="users">{{ ux_management }}</a>
        {% endif %}
        {% if ux_has_configuration == "1" %}
        <a class="button action-configuration {{ ux_configuration_current }}"{% if ux_configuration_current == "is-current" %} aria-current="page"{% endif %} href="Admin-configuration.html" data-admin-icon="settings">{{ ux_configuration }}</a>
        {% endif %}
    </nav>
    {% endif %}
    <nav class="topbar-actions" aria-label="{{ ui_admin_navigation }}">
        <button class="button admin-nav-toggle" type="button" aria-controls="admin-sidebar" aria-expanded="false" data-admin-icon="menu">{{ ui_menu }}</button>
        <a class="button button-quiet" href="{{ base_url }}/" data-admin-icon="external-link">{{ ui_view_site }}</a>
        {% if can_access_admin == "1" %}<form method="post" action="disconnect.html" class="inline-action"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><button class="button" type="submit" data-admin-icon="log-out">{{ ui_logout }}</button></form>{% endif %}
    </nav>
</header>

<div class="admin-layout">
    {% if can_access_admin == "1" %}
    <aside class="admin-sidebar" id="admin-sidebar" aria-label="{{ ui_admin_navigation }}">
        <nav>
            <ul class="admin-nav">
                <li><a class="{{ ux_home_current }}" href="Admin.html" data-admin-icon="layout-dashboard">{{ ui_dashboard }}</a></li>
            </ul>
            {% if ux_show_writing == "1" %}
            <p class="nav-heading">{{ ux_writing }}</p>
            <ul class="admin-nav">
                {% for item in ux_nav_writing %}
                <li><a class="{{ item.current }} workspace-link"{% if item.current == "is-current" %} aria-current="page"{% endif %} href="{{ item.url }}" data-admin-icon="{{ item.icon }}">{{ item.name }}</a></li>
                {% endfor %}
            </ul>
            {% endif %}
            {% if ux_show_management == "1" %}
            <p class="nav-heading">{{ ux_management }}</p>
            <ul class="admin-nav">
                {% for item in ux_nav_management %}
                <li><a class="{{ item.current }} workspace-link"{% if item.current == "is-current" %} aria-current="page"{% endif %} href="{{ item.url }}" data-admin-icon="{{ item.icon }}">{{ item.name }}</a></li>
                {% endfor %}
            </ul>
            {% endif %}
            {% if ux_show_configuration == "1" %}
            <p class="nav-heading">{{ ux_configuration }}</p>
            <ul class="admin-nav">
                {% for item in ux_nav_configuration %}
                <li><a class="{{ item.current }} workspace-link"{% if item.current == "is-current" %} aria-current="page"{% endif %} href="{{ item.url }}" data-admin-icon="{{ item.icon }}">{{ item.name }}</a></li>
                {% endfor %}
            </ul>
            {% endif %}

            <p class="nav-heading">{{ ui_my_account }}</p>
            <ul class="admin-nav admin-nav-secondary">
                <li><a href="personnal.html" data-admin-icon="circle-user">{{ ui_my_account }}</a></li>
                <li><a href="{{ base_url }}/" data-admin-icon="external-link">{{ ui_view_site }}</a></li>
            </ul>
        </nav>
    </aside>
    {% endif %}

    <main class="admin-content" id="admin-content" tabindex="-1">
        {% if ux_active_area %}
        <nav class="ux-breadcrumb" aria-label="{{ ui_breadcrumb }}"><a href="Admin.html">{{ ui_dashboard }}</a><span aria-hidden="true">›</span><a href="Admin-{{ ux_active_area }}.html">{{ ux_active_label }}</a>{% if ux_breadcrumb_leaf %}<span aria-hidden="true">›</span><span>{{ ux_breadcrumb_leaf }}</span>{% endif %}</nav>
        {% endif %}
        {% slot main %}
    </main>
</div>

<footer class="admin-footer">P3 — {{ ui_version }} {{ portix_version }}</footer>
</body>
</html>
