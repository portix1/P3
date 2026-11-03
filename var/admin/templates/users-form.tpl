<header class="page-heading">
    <p class="eyebrow"><a href="Admin-users.html">{{ users_management }}</a></p>
    <h1>{{ users_form_title }}</h1>
    <p>{{ users_form_intro }}</p>
</header>

{% if users_message %}
<p class="status-message" role="status">{{ users_message }}</p>
{% endif %}

<form method="post" action="{{ users_form_action }}" class="user-account-form">
    <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
    <section class="admin-section user-form-section">
        <div class="section-heading-row">
            <div>
                <h2>{{ users_identity }}</h2>
                <p>{{ users_identity_help }}</p>
            </div>
        </div>
        <div class="form-grid">
            {% if users_is_new == "1" %}
            <label>{{ users_username }}
                <input name="pseudo" value="{{ users_pseudo_value }}" required pattern="[A-Za-z0-9_.-]{2,64}" maxlength="64" autocomplete="username" />
                <small>{{ users_username_help }}</small>
            </label>
            {% endif %}
            {% if users_is_edit == "1" %}
            <div class="readonly-field">
                <span>{{ users_username }}</span>
                <strong>@{{ users_pseudo_value }}</strong>
                <small>{{ users_username_immutable }}</small>
            </div>
            {% endif %}
            <label>{{ users_first_name }}
                <input name="first_name" value="{{ users_first_name_value }}" maxlength="100" autocomplete="given-name" />
            </label>
            <label>{{ users_last_name }}
                <input name="last_name" value="{{ users_last_name_value }}" maxlength="100" autocomplete="family-name" />
            </label>
            <label>{{ users_email }}
                <input type="email" name="email" value="{{ users_email_value }}" maxlength="254" autocomplete="email" />
                <small>{{ users_email_help }}</small>
            </label>
        </div>
    </section>

    <section class="admin-section user-form-section">
        <h2>{{ users_permissions }}</h2>
        <p class="field-help">{{ users_permissions_help }}</p>
        <fieldset class="settings-group role-options">
            <legend class="visually-hidden">{{ users_role }}</legend>
            <label class="role-option">
                <input type="checkbox" name="is_admin" value="1" {% if users_admin_checked == "1" %}checked{% endif %} />
                <span><strong>{{ users_role_admin }}</strong><small>{{ users_role_admin_help }}</small></span>
            </label>
            <label class="role-option">
                <input type="checkbox" name="is_redactor" value="1" {% if users_redactor_checked == "1" %}checked{% endif %} />
                <span><strong>{{ users_role_redactor }}</strong><small>{{ users_role_redactor_help }}</small></span>
            </label>
        </fieldset>
        {% if users_is_self == "1" %}<p class="field-help">{{ users_self_role_help }}</p>{% endif %}
    </section>

    {% if users_is_new == "1" %}
    <section class="admin-section user-form-section">
        <h2>{{ users_initial_password }}</h2>
        <p class="field-help">{{ users_password_help }}</p>
        <div class="form-grid">
            <label>{{ users_password }}
                <input type="password" name="password" minlength="12" maxlength="4096" required autocomplete="new-password" />
            </label>
            <label>{{ users_password_confirm }}
                <input type="password" name="password_confirm" minlength="12" maxlength="4096" required autocomplete="new-password" />
            </label>
        </div>
    </section>
    {% endif %}

    <div class="user-form-actions">
        <button type="submit">{{ users_save_account }}</button>
        <a class="button button-quiet" href="Admin-users.html">{{ ui_cancel }}</a>
    </div>
</form>

{% if users_is_edit == "1" %}<section class="admin-section"><h2>{{ users_permissions_title }}</h2><p>{{ users_article_permissions_help }}</p><form method="post" action="{{ users_permissions_action }}" class="settings-form"><input type="hidden" name="csrf_token" value="{{ csrf_token }}" /><fieldset><legend>{{ users_permissions_title }}</legend><label class="checkbox-label"><input type="checkbox" name="permission_create" value="1" {% if permission_create == "1" %}checked{% endif %} /><span>{{ users_permission_create }}</span></label>
<label class="checkbox-label"><input type="checkbox" name="permission_edit_own" value="1" {% if permission_edit_own == "1" %}checked{% endif %} /><span>{{ users_permission_edit_own }}</span></label>
<label class="checkbox-label"><input type="checkbox" name="permission_edit_others" value="1" {% if permission_edit_others == "1" %}checked{% endif %} /><span>{{ users_permission_edit_others }}</span></label>
<label class="checkbox-label"><input type="checkbox" name="permission_publish" value="1" {% if permission_publish == "1" %}checked{% endif %} /><span>{{ users_permission_publish }}</span></label>
<label class="checkbox-label"><input type="checkbox" name="permission_edit_published" value="1" {% if permission_edit_published == "1" %}checked{% endif %} /><span>{{ users_permission_edit_published }}</span></label>
<label class="checkbox-label"><input type="checkbox" name="permission_delete_own" value="1" {% if permission_delete_own == "1" %}checked{% endif %} /><span>{{ users_permission_delete_own }}</span></label>
<label class="checkbox-label"><input type="checkbox" name="permission_all_categories" value="1" {% if permission_all_categories == "1" %}checked{% endif %} /><span>{{ users_permission_all_categories }}</span></label>
<label class="checkbox-label"><input type="checkbox" name="permission_include_children" value="1" {% if permission_include_children == "1" %}checked{% endif %} /><span>{{ users_permission_include_children }}</span></label>
</fieldset><fieldset><legend>{{ users_permission_categories }}</legend>{% for permissioncategory in permissioncategory %}<label class="checkbox-label"><input type="checkbox" name="permission_categories[]" value="{{ permissioncategory.id }}" {% if permissioncategory.checked == "1" %}checked{% endif %} /><span>{{ permissioncategory.name }} (#{{ permissioncategory.id }})</span></label>{% endfor %}</fieldset><button type="submit" title="{{ ui_save }}">{{ ui_save }}</button> <button type="submit" name="permissions_reset" value="1" title="{{ users_permissions_reset }}">{{ users_permissions_reset }}</button></form></section>{% endif %}


{% if users_is_edit == "1" %}
<section class="admin-section user-password-section">
    <h2>{{ users_change_password }}</h2>
    <p>{{ users_change_password_help }}</p>
    <form method="post" action="{{ users_password_action }}" class="user-password-form">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
        <label>{{ users_password }}
            <input type="password" name="password" minlength="12" maxlength="4096" required autocomplete="new-password" />
        </label>
        <label>{{ users_password_confirm }}
            <input type="password" name="password_confirm" minlength="12" maxlength="4096" required autocomplete="new-password" />
        </label>
        <button type="submit" class="button-quiet">{{ users_change_password_button }}</button>
    </form>
</section>

{% if users_can_delete == "1" %}
<section class="admin-section danger-zone user-danger-zone">
    <h2>{{ users_delete_title }}</h2>
    <p>{{ users_delete_warning }}</p>
    <form method="post" action="{{ users_delete_action }}" data-confirm-delete data-confirm-title="{{ users_pseudo_value }}" data-confirm-prefix="{{ users_delete_prefix }}" data-confirm-suffix="{{ users_delete_suffix }}">
        <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
        <button type="submit" class="button-danger">{{ users_delete_account }}</button>
    </form>
</section>

<dialog class="confirm-dialog" id="confirm-delete-dialog" aria-labelledby="users-delete-account-dialog-title">
    <form method="dialog">
        <h2 id="users-delete-account-dialog-title">{{ users_delete_title }}</h2>
        <p>{{ users_delete_confirmation }} <strong data-confirm-title>{{ users_account }}</strong>.</p>
        <p>{{ users_delete_warning }}</p>
        <div class="dialog-actions">
            <button value="cancel" class="button-quiet">{{ ui_cancel }}</button>
            <button value="confirm" class="button-danger">{{ ui_delete }}</button>
        </div>
    </form>
</dialog>
{% endif %}
{% endif %}
