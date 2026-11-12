<header class="page-heading">
    <p class="eyebrow">{{ diagnostics_eyebrow }}</p>
    <h1 data-admin-icon="activity">{{ diagnostics_title }}</h1>
    <p>{{ diagnostics_intro }}</p>
</header>

{% if diagnostics_message %}<p class="status-message" role="status">{{ diagnostics_message }}</p>{% endif %}

<section class="diagnostics-summary">
    <div>
        <h2>{{ diagnostics_environment_title }}</h2>
        <p>{{ diagnostics_environment_help }}</p>
    </div>
    <p><strong>{{ diagnostics_last_run }}</strong> {{ diagnostics_last_summary }}</p>
</section>

<div class="diagnostics-table-wrap">
<table class="diagnostics-table">
    <caption>{{ diagnostics_environment_title }}</caption>
    <thead><tr><th scope="col">{{ diagnostics_test }}</th><th scope="col">{{ diagnostics_status }}</th><th scope="col">{{ diagnostics_detail }}</th></tr></thead>
    <tbody>
    {% for diagnosticenvironment in diagnosticenvironment %}
    <tr class="diagnostic-{{ diagnosticenvironment.status }}">
        <th scope="row">{{ diagnosticenvironment.label }}</th>
        <td><span class="diagnostic-badge">{{ diagnosticenvironment.status_label }}</span></td>
        <td>{{ diagnosticenvironment.detail }}</td>
    </tr>
    {% endfor %}
    </tbody>
</table>
</div>

<section class="diagnostics-actions">
    <h2>{{ diagnostics_run_title }}</h2>
    <p>{{ diagnostics_run_help }}</p>
    <div class="diagnostics-action-grid">
        <form method="post" action="Admin-diagnostics.html">
            <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
            <input type="hidden" name="diagnostic_mode" value="quick" />
            <button class="button" type="submit">{{ diagnostics_run_quick }}</button>
            <p>{{ diagnostics_quick_help }}</p>
        </form>
        <form method="post" action="Admin-diagnostics.html">
            <input type="hidden" name="csrf_token" value="{{ csrf_token }}" />
            <input type="hidden" name="diagnostic_mode" value="full" />
            <button class="button" type="submit">{{ diagnostics_run_full }}</button>        </form>
    </div>
</section>

{% if diagnostics_has_result == "1" %}
<section class="diagnostics-results">
    <div class="management-heading">
        <p class="eyebrow">{{ diagnostics_results_eyebrow }}</p>
        <h2>{{ diagnostics_results_title }}</h2>
        <p>{{ diagnostics_total }} {{ diagnostics_total_label }} — {{ diagnostics_pass }} {{ diagnostics_pass_label }} — {{ diagnostics_fail }} {{ diagnostics_fail_label }}</p>
    </div>

    <div class="diagnostics-table-wrap">
    <table class="diagnostics-table">
        <caption>{{ diagnostics_results_title }}</caption>
        <thead><tr><th scope="col">{{ diagnostics_test }}</th><th scope="col">{{ diagnostics_status }}</th><th scope="col">{{ diagnostics_detail }}</th></tr></thead>
        <tbody>
        {% for diagnosticrow in diagnosticrow %}
        <tr class="diagnostic-{{ diagnosticrow.status }}">
            <th scope="row">{{ diagnosticrow.label }}</th>
            <td><span class="diagnostic-badge">{{ diagnosticrow.status_label }}</span></td>
            <td>{{ diagnosticrow.detail }}</td>
        </tr>
        {% endfor %}
        </tbody>
    </table>
    </div>

    <label for="diagnostics-report"><strong>{{ diagnostics_report_title }}</strong></label>
    <textarea id="diagnostics-report" class="diagnostics-report" rows="14" readonly>{{ diagnostics_report }}</textarea>
    <button class="button button-quiet" type="button" data-copy-diagnostics="#diagnostics-report">{{ diagnostics_copy_report }}</button>
    <span class="copy-status" data-copy-diagnostics-status aria-live="polite"></span>
</section>
{% endif %}

<section class="diagnostics-note">
    <h2>{{ diagnostics_scope_title }}</h2>
    <p>{{ diagnostics_scope_help }}</p>
</section>
