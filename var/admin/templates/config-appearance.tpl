<h2>{{ ux_appearance }}</h2>

<form action="Admin-config-themes.html" method="post" class="settings-form">
<input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
<input type="hidden" name="ux_section" value="appearance" />
<div class="form-grid">
    <label>
        <span>{{ template }}</span>
        <select name="templ">
        {% for templates in templates %}
            <option value="{{ templates.name }}" {% if templates.checked == "selected" %}selected="selected"{% endif %}>{{ templates.label }}</option>
        {% endfor %}
        </select>
        <small>{{ config_theme_help }}</small>
    </label>
</div>
<div class="form-actions"><button type="submit" data-admin-icon="circle-check" title="{{ ux_use_theme }}">{{ ux_use_theme }}</button></div>
</form>

