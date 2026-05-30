<?php
$clientId = (int) ($clientId ?? 0);
$clientAppointmentHistory = is_array($clientAppointmentHistory ?? null) ? $clientAppointmentHistory : [];
$rows = is_array($clientAppointmentHistory[$clientId] ?? null) ? $clientAppointmentHistory[$clientId] : [];
if ($clientId <= 0 || $rows === []) {
    return;
}
?>
<div class="jt-client-appointment-history mt-2">
    <div class="small text-uppercase text-muted fw-semibold mb-1">Appointment history</div>
    <ul class="list-unstyled mb-0 small">
        <?php foreach ($rows as $appointment): ?>
            <?php if (!is_array($appointment)) {
                continue;
            } ?>
            <?php
            $appointmentUrl = trim((string) ($appointment['url'] ?? ''));
            $appointmentAt = format_datetime((string) ($appointment['at'] ?? ''));
            $appointmentKind = trim((string) ($appointment['kind'] ?? 'Appointment'));
            $appointmentTitle = trim((string) ($appointment['title'] ?? ''));
            $appointmentStatus = strtolower(trim((string) ($appointment['status'] ?? '')));
            $statusSuffix = $appointmentStatus === 'cancelled' ? ' · Cancelled' : '';
            ?>
            <li class="jt-client-appointment-history-item py-1">
                <?php if ($appointmentUrl !== ''): ?>
                    <a class="text-decoration-none" href="<?= e($appointmentUrl) ?>">
                        <span class="text-muted"><?= e($appointmentAt) ?></span>
                        · <?= e($appointmentKind) ?><?= $appointmentTitle !== '' ? ': ' . e($appointmentTitle) : '' ?><?= e($statusSuffix) ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted"><?= e($appointmentAt) ?></span>
                    · <?= e($appointmentKind) ?><?= $appointmentTitle !== '' ? ': ' . e($appointmentTitle) : '' ?><?= e($statusSuffix) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
