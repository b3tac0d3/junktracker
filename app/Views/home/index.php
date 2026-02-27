<?php
$businessName = (string) (($business['name'] ?? '') !== '' ? $business['name'] : ('Business #' . (string) current_business_id()));
?>
<div class="page-header">
    <h1>Dashboard</h1>
    <p class="muted"><?= e($businessName) ?></p>
</div>

<div class="kpi-grid">
    <div class="kpi-card"><span>Clients</span><strong><?= e((string) ((int) ($summary['clients_total'] ?? 0))) ?></strong></div>
    <div class="kpi-card"><span>Open Jobs</span><strong><?= e((string) ((int) ($summary['jobs_open'] ?? 0))) ?></strong></div>
    <div class="kpi-card"><span>My Open Tasks</span><strong><?= e((string) ((int) ($summary['tasks_mine_open'] ?? 0))) ?></strong></div>
    <div class="kpi-card"><span>Open Clock Entries</span><strong><?= e((string) ((int) ($summary['time_open_entries'] ?? 0))) ?></strong></div>
    <div class="kpi-card"><span>Open Invoices</span><strong><?= e((string) ((int) ($summary['invoices_open'] ?? 0))) ?></strong></div>
</div>

<div class="card mt-3">
    <h2>Phase A Scope</h2>
    <ul>
        <li>Strict business isolation via <code>business_id</code> on all tenant data tables</li>
        <li>Simplified roles: <code>punch_only</code>, <code>general_user</code>, <code>admin</code>, global <code>site_admin</code></li>
        <li>Core module shells and shared UI foundation in place</li>
    </ul>
</div>
