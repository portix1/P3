<header class="page-heading"><p class="eyebrow">{{ contact_conversation }} #{{ contact_id }}</p><h1>{{ contact_conversation_subject }}</h1><p>{{ contact_visitor }}: {{ contact_visitor_name }} — {{ contact_visitor_email }}</p></header>
{% include "var/modules/contact/templates/nav.tpl" %}
{% if contact_admin_message %}<p class="status-message" role="status">{{ contact_admin_message }}</p>{% endif %}
<div class="conversation-thread">
{% for contactmessage in contactmessage %}
<article class="conversation-message message-{{ contactmessage.direction }}"><header><strong>{{ contactmessage.author }}</strong><span>{{ contactmessage.direction_label }} · {{ contactmessage.date }}</span></header><p>{{ contactmessage.body }}</p><small>{{ contactmessage.delivery }}</small>
{% if contact_conversation_live == "1" %}<form method="post" action="Admin-contact-view-{{ contact_id }}.html"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="conversation_id" value="{{ contact_id }}" /><input type="hidden" name="message_id" value="{{ contactmessage.id }}" /><label><input type="checkbox" name="confirm_trash" value="yes" required /> {{ contact_trash_confirm }}</label><button name="contact_admin_action" value="message_trash" title="{{ contact_trash }}">{{ contact_trash }}</button></form>{% endif %}</article>
{% endfor %}
</div>
{% if contact_can_reply == "1" %}
<form method="post" action="Admin-contact-view-{{ contact_id }}.html" class="settings-form contact-reply-form"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="contact_admin_action" value="reply" /><input type="hidden" name="conversation_id" value="{{ contact_id }}" /><label><span>{{ contact_reply }}</span><textarea name="reply" maxlength="20000" required></textarea><small>{{ contact_reply_identity_help }}</small></label><div class="form-actions"><button type="submit" title="{{ contact_send_reply }}">{{ contact_send_reply }}</button></div></form>
<form method="post" action="Admin-contact-view-{{ contact_id }}.html" class="inline-action"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="contact_admin_action" value="close" /><input type="hidden" name="conversation_id" value="{{ contact_id }}" /><button type="submit" class="button-quiet" title="{{ contact_close }}">{{ contact_close }}</button></form>
{% endif %}
{% if contact_is_open == "0" %}<form method="post" action="Admin-contact-view-{{ contact_id }}.html"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="contact_admin_action" value="reopen" /><input type="hidden" name="conversation_id" value="{{ contact_id }}" /><button type="submit" title="{{ contact_reopen }}">{{ contact_reopen }}</button></form>{% endif %}

<details><summary>{{ contact_deleted_messages }}</summary>
{% for trashedmessage in trashedmessage %}<article class="conversation-message"><strong>{{ trashedmessage.author }}</strong><p>{{ trashedmessage.body }}</p><form method="post" action="Admin-contact-view-{{ contact_id }}.html"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><input type="hidden" name="conversation_id" value="{{ contact_id }}" /><input type="hidden" name="message_id" value="{{ trashedmessage.id }}" /><button name="contact_admin_action" value="message_restore" title="{{ contact_restore }}">{{ contact_restore }}</button></form></article>{% endfor %}
</details>
