<?php
$_LANG = array_merge($_LANG, array(
    'articles_title' => 'Articles',
    'articles_public_introduction' => 'Browse the site’s durable content, organised into categories.',
    'articles_categories' => 'Categories',
    'articles_all' => 'All articles',
    'articles_uncategorized' => 'Uncategorised',
    'articles_read' => 'Read article',
    'articles_back_to_list' => 'Back to articles',
    'articles_empty' => 'No article is published in this section.',
    'articles_breadcrumb_label' => 'Article path',
    'articles_previous' => 'Previous article',
    'articles_next' => 'Next article',

    'articles_admin_title' => 'Articles',
    'articles_admin_introduction' => 'Write timeless site content and organise it into a hierarchy.',
    'articles_admin_summary' => 'Article overview',
    'articles_admin_total' => 'article(s)',
    'articles_admin_published_total' => 'published',
    'articles_new' => 'New article',
    'articles_manage_categories' => 'Manage categories',
    'articles_manage_menu' => 'Configure menu',
    'articles_manage_order' => 'Organise content',
    'articles_last_update' => 'Last update',
    'articles_status_draft' => 'Draft',
    'articles_status_published' => 'Published',
    'articles_publish' => 'Publish',
    'articles_make_draft' => 'Move to draft',
    'articles_delete_title' => 'Delete this article?',
    'articles_delete_description' => 'You are about to permanently delete',
    'articles_delete_confirm' => 'Delete permanently',
    'articles_delete_forbidden' => 'Only an administrator can delete an article or category.',
    'articles_not_found' => 'Article not found.',
    'articles_invalid_id' => 'Invalid article or category identifier.',
    'articles_invalid_slug' => 'Invalid article address.',
    'articles_invalid_title' => 'The title is required and limited to 250 characters.',
    'articles_invalid_navigation_mode' => 'Invalid article navigation mode.',
    'articles_invalid_order' => 'The submitted order is invalid. Reload the page before trying again.',
    'articles_invalid_status' => 'Invalid article status.',

    'articles_form_new' => 'New article',
    'articles_form_edit' => 'Edit article',
    'articles_form_help' => 'The title suggests the public address. The slug may remain unchanged when the title changes.',
    'articles_field_title' => 'Title',
    'articles_field_url' => 'Public address',
    'articles_slug_placeholder' => 'generated from the title',
    'articles_slug_help' => 'Leave blank to generate the slug automatically.',
    'articles_field_status' => 'Status',
    'articles_field_category' => 'Category',
    'articles_field_author' => 'Author',
    'articles_field_author_help' => 'Optional. The site-wide default author is used when this field is empty.',
    'articles_field_featured_image' => 'Main image for search metadata',
    'articles_field_featured_image_help' => 'Optional. Leave blank to automatically use the first image in the article.',
    'articles_field_summary' => 'Description for search and sharing',
    'articles_field_summary_help' => 'Optional. Up to 170 characters. Used for previews and metadata, not shown in the article content.',
    'articles_invalid_summary' => 'The description cannot exceed 170 characters.',
    'articles_invalid_seo_fields' => 'The article search metadata is too long.',
    'articles_field_body' => 'Content',
    'articles_body_help' => 'The complete article content. Images are optimised and stored locally.',

    'articles_menu_settings' => 'Article menu',
    'articles_menu_introduction' => 'Each top-level category can become a header menu entry, with subcategories across several levels.',
    'articles_menu_header_title' => 'Header display',
    'articles_menu_header_enabled' => 'Show top-level categories in the menu',
    'articles_menu_header_help' => 'Their order follows the position defined in category management.',
    'articles_menu_show_articles' => 'Show published articles in submenus',
    'articles_menu_show_articles_help' => 'Articles appear below their category. Drafts and uncategorised articles remain hidden.',
    'articles_menu_navigation_title' => 'Navigation between articles',
    'articles_menu_navigation_label' => 'Scope of previous and next links',
    'articles_menu_navigation_off' => 'No previous or next links',
    'articles_menu_navigation_category' => 'Stay in the exact category',
    'articles_menu_navigation_book' => 'Browse the whole top-level category like a book',
    'articles_menu_navigation_help' => 'Book mode follows category and article order, but never enters another top-level category.',
    'articles_menu_saved' => 'Menu configuration saved.',
    'articles_menu_toggle' => 'Open or close the “%s” submenu',

    'articles_categories_help' => 'Subcategories can be nested across several levels.',
    'articles_back_to_admin' => 'Back to articles',
    'articles_category_new' => 'New category',
    'articles_category_edit' => 'Edit category',
    'articles_category_name' => 'Name',
    'articles_category_description' => 'Description for search and sharing',
    'articles_category_description_help' => '170 characters maximum. Leave empty to let P3 generate the description.',
    'articles_category_invalid_description' => 'The category description cannot exceed 170 characters.',
    'articles_category_count' => 'Published articles',
    'articles_category_parent' => 'Parent category',
    'articles_category_visible' => 'Show in public navigation',
    'articles_category_visible_help' => 'A hidden category and its branch disappear from public menus and listings. Their pages and published articles remain accessible through their direct URLs.',
    'articles_category_shown' => 'Shown in navigation',
    'articles_category_hidden' => 'Hidden from navigation',
    'articles_category_no_parent' => 'None — top-level category',
    'articles_category_position' => 'Position',
    'articles_category_form_help' => 'The parent category builds the public hierarchy.',
    'articles_category_delete_title' => 'Delete this category?',
    'articles_category_delete_description' => 'Articles and subcategories will become uncategorised after deleting',
    'articles_category_not_found' => 'Category not found.',
    'articles_category_invalid_name' => 'The category name is required and limited to 150 characters.',
    'articles_category_cycle' => 'A category cannot become the child of one of its subcategories.',

    'articles_order_title' => 'Editorial organisation',
    'articles_order_introduction' => 'Set the reading order by dragging items or using the arrow buttons. Articles and subcategories can be interleaved at the same level.',
    'articles_order_save' => 'Save order',
    'articles_order_saved' => 'Category and article order saved.',
    'articles_order_categories_title' => 'Content structure',
    'articles_order_categories_help' => 'Articles, categories and subcategories share the same editorial order at each level.',
    'articles_order_drag_help' => 'Drag and drop',
    'articles_order_uncategorized_help' => 'These articles remain outside book-mode navigation.',
    'articles_order_articles_in_category' => 'Articles in this category',
    'articles_order_subcategories' => 'Subcategories',
    'articles_order_no_category' => 'No categories yet.',
    'articles_order_no_article' => 'No articles at this level.',
    'articles_order_move_up' => 'Move “%s” up',
    'articles_order_move_down' => 'Move “%s” down'
));

$_LANG['articles_table_scroll'] = 'Table: scroll horizontally';

$_LANG['articles_series'] = 'Article series';
$_LANG['articles_series_new'] = 'Create a series';
$_LANG['articles_series_delete'] = 'Delete series';
$_LANG['articles_series_description'] = 'Optional description';
$_LANG['articles_series_show_title'] = 'Display the series title in articles';
$_LANG['articles_series_order_help'] = 'Select articles. Arrows change their order; positions remain editable without JavaScript. An article belongs to one series.';
$_LANG['articles_series_position'] = 'Position';
$_LANG['articles_series_up'] = 'Move this part up';
$_LANG['articles_series_down'] = 'Move this part down';
$_LANG['articles_series_part'] = 'Part %d of %d';
$_LANG['articles_series_parts'] = 'All parts';
$_LANG['articles_series_invalid'] = 'Title required (up to 250 characters); description limited to 2,000 characters.';
$_LANG['articles_series_missing'] = 'Series not found.';
$_LANG['articles_series_conflict'] = 'A selected article already belongs to another series. Remove it from that series first.';

$_LANG['articles_related'] = 'Further reading';
$_LANG['articles_related_help'] = 'Select related articles and their order. These optional links are independent of categories and series.';
$_LANG['articles_related_invalid'] = 'Invalid relations: choose accessible articles, without duplicates or a link to this article.';
$_LANG['articles_reading_settings'] = 'Reading options';
$_LANG['articles_heading_toc'] = 'On this page';
$_LANG['articles_heading_toc_enable'] = 'Display an automatic table of contents from headings';

$_LANG['articles_picker_search'] = 'Search articles';
$_LANG['articles_picker_search_hint'] = 'Title or category…';
$_LANG['articles_picker_category'] = 'Category';
$_LANG['articles_picker_all_categories'] = 'All categories';
$_LANG['articles_picker_more'] = 'Show more suggestions';
$_LANG['articles_picker_count'] = '{selected} selected · {shown} of {total} suggestions shown';
$_LANG['articles_picker_related_order'] = 'Selected first, then the same category, then other articles alphabetically. No links are added automatically.';

$_LANG['articles_toc_batch_done'] = 'Table of contents enabled in %d article(s) with at least four headings.';
$_LANG['articles_toc_batch_failed'] = 'The table-of-contents batch did not complete. Existing settings are retained; check the PHP log before retrying.';
$_LANG['articles_toc_batch_backup_failed'] = 'Could not back up table-of-contents settings. No changes were applied.';

$_LANG['articles_series_batch_done'] = '%d series created, %d articles linked. %d incomplete, ambiguous or already assigned groups left unchanged.';
$_LANG['articles_series_batch_failed'] = 'Series creation did not complete. No series from the batch was added; check the PHP log.';
$_LANG['articles_series_batch_backup_failed'] = 'Could not back up existing series. No changes applied.';

$_LANG['articles_public_edit'] = 'Edit this article';

$_LANG['articles_status_pending'] = 'Awaiting review';
$_LANG['articles_admin_pending_total'] = 'awaiting review';
$_LANG['articles_review_request'] = 'Request publication';
$_LANG['articles_review_approve'] = 'Approve and publish';
$_LANG['articles_review_send_back'] = 'Return for changes';
$_LANG['articles_review_note_label'] = 'Explain the changes requested';
$_LANG['articles_review_note_required'] = 'Describe the requested changes (up to 2,000 characters).';
$_LANG['articles_review_returned'] = 'Changes requested';
$_LANG['articles_review_pending_edit_notice'] = 'This article is awaiting review. Editing it returns it to draft; request publication again when ready.';
$_LANG['articles_empty_admin'] = 'No articles yet. Create an article to begin.';
$_LANG['articles_empty_redactor'] = 'No articles available to edit. You can create a draft if permitted.';

$_LANG['articles_save_draft'] = 'Save draft';
$_LANG['articles_review_next_step'] = 'Save your draft first. In the article list, choose “Request publication”. An administrator will review it before it appears online.';

$_LANG['articles_filter_heading'] = 'Find articles';
$_LANG['articles_filter_search'] = 'Search';
$_LANG['articles_filter_placeholder'] = 'Title, category or address…';
$_LANG['articles_filter_status'] = 'Article status';
$_LANG['articles_filter_all'] = 'All statuses';
$_LANG['articles_filter_apply'] = 'Filter';
$_LANG['articles_filter_clear'] = 'Clear filters';
$_LANG['articles_filter_result_label'] = 'article(s) shown';
$_LANG['articles_filter_no_matches_label'] = 'No articles match these filters.';

$_LANG['articles_review_view_pending'] = 'View requests';

$_LANG['articles_page_navigation'] = 'Article list pages';
$_LANG['articles_page_items'] = 'Articles';
$_LANG['articles_page_previous'] = 'Previous page';
$_LANG['articles_page_next'] = 'Next page';
