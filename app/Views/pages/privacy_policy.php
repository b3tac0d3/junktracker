<?php
    $effectiveDate = (string) ($effectiveDate ?? 'February 20, 2026');
    $homePath = is_authenticated() ? '/': '/login';
?>
<div class="container-fluid px-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between mt-4 mb-3 gap-3">
        <div>
            <h1 class="mb-1">Privacy Policy</h1>
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="<?= url($homePath) ?>">Home</a></li>
                <li class="breadcrumb-item active">Privacy Policy</li>
            </ol>
        </div>
        <span class="badge bg-light text-dark border">Effective <?= e($effectiveDate) ?></span>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <p class="mb-3">
                This Privacy Policy explains how JunkTracker stores and uses data entered into the application.
                By using JunkTracker, you agree to this policy.
            </p>

            <h5>Information We Store</h5>
            <p>We store account details, job records, customer records, notes, time tracking data, attachments, and operational logs needed to run the platform.</p>

            <h5>How Data Is Used</h5>
            <p>Data is used to operate scheduling, invoicing, reporting, communications, and access control features. We also use audit logs for security and troubleshooting.</p>

            <h5>Access and Sharing</h5>
            <p>Data is accessible to authorized users based on role permissions. We do not sell user data. Data may be shared only with service providers required for hosting, email delivery, backups, or security.</p>

            <h5>Security</h5>
            <p>We use technical and administrative safeguards, but no system is guaranteed to be fully secure. Users are responsible for keeping account credentials confidential.</p>

            <h5>Retention</h5>
            <p>Data is retained as needed for business operations, legal compliance, and backup recovery unless deleted by authorized administrators.</p>

            <h5>Cookies and Sessions</h5>
            <p>JunkTracker uses session cookies and optional remember-me cookies to keep users signed in and secure authenticated sessions.</p>

            <h5>User Responsibility</h5>
            <p>Do not upload sensitive personal data unless required for business operations. You are responsible for obtaining any required consent from customers or contacts whose data is entered.</p>

            <h5>Policy Updates</h5>
            <p class="mb-0">This policy may be updated from time to time. Continued use of JunkTracker after updates means you accept the revised policy.</p>
        </div>
    </div>
</div>
