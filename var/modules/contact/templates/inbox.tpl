<header class="page-heading"><p class="eyebrow">{{ contact }}</p><h1>{{ contact_inbox }}</h1><p>{{ contact_inbox_help }}</p></header>
{% include "var/modules/contact/templates/nav.tpl" %}
{% if contact_admin_message %}<p class="status-message" role="status">{{ contact_admin_message }}</p>{% endif %}
<nav class="module-tabs contact-folders" aria-label="{{ contact_folders }}">
{% for contactfolder in contactfolder %}<a class="{{ contactfolder.current }}" href="Admin-contact.html?folder={{ contactfolder.key }}">{{ contactfolder.label }}</a>{% endfor %}
</nav>
<form method="post" action="Admin-contact.html?folder={{ contact_folder }}" class="contact-mailbox" data-contact-mailbox>
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="folder" value="{{ contact_folder }}" />
<input type="hidden" name="confirm_trash" value="" /><noscript><label><input type="checkbox" name="confirm_trash" value="yes" /> {{ contact_trash_confirm }}</label></noscript>
<div class="contact-toolbar">
<label title="{{ contact_select_all }}"><input type="checkbox" data-contact-all aria-label="{{ contact_select_all }}" /> {{ contact_select_all }}</label>
{% if contact_is_trash == "0" %}
<button name="contact_admin_action" value="archive" title="{{ contact_archive }}" aria-label="{{ contact_archive }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 7h14v10H3zM2 3h16v4H2zM7 10h6"/></svg></button>
<button name="contact_admin_action" value="unarchive" title="{{ contact_unarchive }}" aria-label="{{ contact_unarchive }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 11v6h14v-6M10 13V3M6 7l4-4 4 4"/></svg></button>
<button name="contact_admin_action" value="close" title="{{ contact_close }}" aria-label="{{ contact_close }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="m6 10 3 3 5-6"/></svg></button>
<button name="contact_admin_action" value="reopen" title="{{ contact_reopen }}" aria-label="{{ contact_reopen }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 8a7 7 0 1 1 0 5M4 3v5h5"/></svg></button>
<button name="contact_admin_action" value="read" title="{{ contact_mark_read }}" aria-label="{{ contact_mark_read }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m2 8 8 5 8-5M2 8l8-5 8 5v9H2z"/></svg></button>
<button name="contact_admin_action" value="unread" title="{{ contact_mark_unread }}" aria-label="{{ contact_mark_unread }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 5h16v12H2zM2 5l8 6 8-6"/></svg></button>
<button name="contact_admin_action" value="trash" title="{{ contact_trash }}" data-contact-confirm="{{ contact_trash_confirm }}" aria-label="{{ contact_trash }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 5h14M7 5V2h6v3M5 5l1 13h8l1-13M8 8v7M12 8v7"/></svg></button>
{% endif %}
{% if contact_is_trash == "1" %}<button name="contact_admin_action" value="restore" title="{{ contact_restore }}" aria-label="{{ contact_restore }}"><svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 8a7 7 0 1 1 0 5M4 3v5h5"/></svg></button>{% endif %}
</div>
<div class="contact-mail-list">
{% for conversation in conversation %}
<div class="contact-mail-row {{ conversation.unread }}">
<input type="checkbox" name="ids[]" value="{{ conversation.id }}" aria-label="{{ contact_select }} {{ conversation.subject }}" />
<span class="contact-mail-sender">{{ conversation.name }}</span>
<a class="contact-mail-subject" href="Admin-contact-view-{{ conversation.id }}.html">{{ conversation.subject }}</a>
<span class="contact-mail-status">{{ conversation.status_label }}</span><span class="contact-mail-count">{{ conversation.message_count }}</span><time>{{ conversation.updated_at }}</time>
</div>
{% endfor %}
</div>
</form>
{% if contact_is_trash == "1" %}
<p>{{ contact_message_trash_help }}</p>
{% if contact_can_configure == "1" %}<form method="post" action="Admin-contact.html?folder=trash" data-contact-empty><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="folder" value="trash" /><input type="hidden" name="contact_admin_action" value="purge" /><label><input type="checkbox" name="confirm_empty" value="yes" required /> {{ contact_empty_confirm }}</label><button type="submit" class="button-danger" title="{{ contact_empty }}">{{ contact_empty }}</button></form>{% endif %}
{% endif %}
<script src="var/modules/contact/assets/mailbox.js?v=1.1.0" defer></script>
