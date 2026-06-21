<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;

final class QuoteSchedule
{
    public const TYPE_NONE = 'none';
    public const TYPE_FOLLOW_UP_TASK = 'follow_up_task';
    public const TYPE_MEETING = 'meeting';

    /**
     * @return array<string, string>
     */
    public static function defaultFields(): array
    {
        return [
            'schedule_type' => self::TYPE_NONE,
            'follow_up_task_due_at' => '',
            'follow_up_task_title' => '',
            'meeting_at' => '',
            'next_follow_up_at' => '',
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public static function enrichForDisplay(array $form): array
    {
        $form = array_merge(self::defaultFields(), $form);
        $scheduleType = strtolower(trim((string) ($form['schedule_type'] ?? '')));
        $meetingAt = self::toInputDateTimeLocal((string) ($form['meeting_at'] ?? ''));
        $followUpDue = self::toInputDateTimeLocal((string) ($form['follow_up_task_due_at'] ?? ''));

        if (!in_array($scheduleType, self::typeOptions(), true)) {
            $storedMeeting = self::toInputDateTimeLocal((string) ($form['next_follow_up_at'] ?? ''));
            if ($storedMeeting !== '') {
                $scheduleType = self::TYPE_MEETING;
                $meetingAt = $storedMeeting;
            } else {
                $scheduleType = self::TYPE_NONE;
            }
        }

        $form['schedule_type'] = $scheduleType;
        $form['meeting_at'] = $meetingAt;
        $form['follow_up_task_due_at'] = $followUpDue;

        return $form;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function fieldsFromRequest(array $input): array
    {
        $scheduleType = strtolower(trim((string) ($input['schedule_type'] ?? self::TYPE_NONE)));
        if (!in_array($scheduleType, self::typeOptions(), true)) {
            $scheduleType = self::TYPE_NONE;
        }

        return [
            'schedule_type' => $scheduleType,
            'follow_up_task_due_at' => trim((string) ($input['follow_up_task_due_at'] ?? '')),
            'follow_up_task_title' => trim((string) ($input['follow_up_task_title'] ?? '')),
            'meeting_at' => trim((string) ($input['meeting_at'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, mixed>
     */
    public static function applyMeetingDate(array $form): array
    {
        $scheduleType = strtolower(trim((string) ($form['schedule_type'] ?? self::TYPE_NONE)));

        if ($scheduleType === self::TYPE_MEETING) {
            $form['next_follow_up_at'] = trim((string) ($form['meeting_at'] ?? ''));
        } else {
            $form['next_follow_up_at'] = '';
        }

        return $form;
    }

    /**
     * @param array<string, mixed> $form
     * @return array<string, string>
     */
    public static function validate(array $form): array
    {
        $errors = [];
        $scheduleType = strtolower(trim((string) ($form['schedule_type'] ?? self::TYPE_NONE)));

        if ($scheduleType === self::TYPE_FOLLOW_UP_TASK) {
            $dueAt = trim((string) ($form['follow_up_task_due_at'] ?? ''));
            if ($dueAt === '') {
                $errors['follow_up_task_due_at'] = 'Enter when the follow-up task is due.';
            } elseif (strtotime($dueAt) === false) {
                $errors['follow_up_task_due_at'] = 'Enter a valid follow-up date/time.';
            }
        }

        if ($scheduleType === self::TYPE_MEETING) {
            $meetingAt = trim((string) ($form['meeting_at'] ?? ''));
            if ($meetingAt === '') {
                $errors['meeting_at'] = 'Enter the meeting date and time.';
            } elseif (strtotime($meetingAt) === false) {
                $errors['meeting_at'] = 'Enter a valid meeting date/time.';
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $form
     */
    public static function maybeCreateFollowUpTask(
        int $businessId,
        string $linkType,
        int $linkId,
        array $form,
        int $actorUserId,
        string $defaultTitle
    ): void {
        if ($businessId <= 0 || $linkId <= 0 || $actorUserId <= 0) {
            return;
        }

        if (strtolower(trim((string) ($form['schedule_type'] ?? ''))) !== self::TYPE_FOLLOW_UP_TASK) {
            return;
        }

        $dueRaw = trim((string) ($form['follow_up_task_due_at'] ?? ''));
        $dueAt = null;
        if ($dueRaw !== '') {
            $timestamp = strtotime($dueRaw);
            if ($timestamp !== false) {
                $dueAt = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $title = trim((string) ($form['follow_up_task_title'] ?? ''));
        if ($title === '') {
            $title = $defaultTitle;
        }

        $body = trim((string) ($form['notes'] ?? ''));

        $taskId = Task::create($businessId, [
            'title' => $title,
            'body' => $body,
            'status' => 'open',
            'owner_user_id' => $actorUserId,
            'assigned_user_id' => null,
            'due_at' => $dueAt,
            'priority' => 3,
            'link_type' => $linkType,
            'link_id' => $linkId,
        ], $actorUserId);

        if ($taskId > 0) {
            google_calendar_sync_item($businessId, 'task', $taskId);
        }
    }

    /**
     * @return list<string>
     */
    public static function typeOptions(): array
    {
        return [self::TYPE_NONE, self::TYPE_FOLLOW_UP_TASK, self::TYPE_MEETING];
    }

    public static function toInputDateTimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);
        return $timestamp === false ? '' : date('Y-m-d\TH:i', $timestamp);
    }
}
