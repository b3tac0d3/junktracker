<?php
    $effectiveDate = (string) ($effectiveDate ?? 'February 20, 2026');
    $homePath = is_authenticated() ? '/': '/login';
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Terms &amp; Conditions</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url($homePath) ?>">Home</a></li>
                <li class="breadcrumb-item active">Terms &amp; Conditions</li>
            </ol>
        </div>
        <span class="badge bg-light text-dark border">Effective <?= e($effectiveDate) ?></span>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-3">
                These Terms &amp; Conditions govern use of JunkTracker. By accessing or using the platform, you agree to these terms.
            </p>

            <h5>Permitted Use</h5>
            <p>JunkTracker may be used only for lawful business operations. You agree not to misuse the system, attempt unauthorized access, or interfere with service availability.</p>

            <h5>Account Responsibility</h5>
            <p>You are responsible for actions taken under your account and for protecting your login credentials. Notify an administrator immediately if account access is suspected to be compromised.</p>

            <h5>Data Accuracy</h5>
            <p>You are responsible for verifying data entered into the platform, including customer records, job information, billing amounts, and time entries.</p>

            <h5>Intellectual Property</h5>
            <p>Application code, branding, and internal workflows remain the property of JunkTracker and its operators unless otherwise agreed in writing.</p>

            <h5>Service Availability</h5>
            <p>Service is provided on an "as is" and "as available" basis. We do not guarantee uninterrupted operation, error-free performance, or compatibility with every third-party environment.</p>

            <h5>Limitation of Liability</h5>
            <p>To the maximum extent permitted by law, JunkTracker and its operators are not liable for indirect, incidental, special, or consequential damages, including lost revenue, lost data, or business interruption.</p>

            <h5>Indemnification</h5>
            <p>You agree to indemnify and hold harmless JunkTracker and its operators from claims, damages, or losses resulting from your use of the platform, your data, or your violation of these terms.</p>

            <h5>Changes and Termination</h5>
            <p>Features and terms may be updated at any time. Access may be suspended or terminated for policy violations, security risks, or operational reasons.</p>

            <h5>Legal Note</h5>
            <p class="mb-0">These terms are a general baseline and are not legal advice. For production/legal use, review with qualified counsel.</p>
        </div>
    </div>
</div>
