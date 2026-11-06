<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Services;

/**
 * T1 - Ha Van Tuan, 65134070.
 * Pure SM-2 scheduling: no database, session, HTTP, XP or authentication.
 * State names below are domain fields, not assumptions about the team's SQL schema.
 */
final class Sm2Scheduler
{
    public function initialState(): array
    {
        return ['easiness_factor' => 2.5, 'repetitions' => 0, 'interval_days' => 0];
    }

    /**
     * Schedule one completed recall, using server-provided review time.
     * @param mixed $grade Require a real integer 0..5; do not coerce JSON strings/bools.
     * @return array Domain state + UTC last_reviewed_at/due_at, with no user/DB fields.
     */
    public function schedule(array $state, $grade, \DateTimeImmutable $reviewedAt): array
    {
        if (!is_int($grade) || $grade < 0 || $grade > 5) {
            throw new \InvalidArgumentException('Grade must be an integer from 0 to 5.');
        }
        foreach (['easiness_factor', 'repetitions', 'interval_days'] as $field) {
            if (!array_key_exists($field, $state)) {
                throw new \InvalidArgumentException('Missing state field: ' . $field);
            }
        }
        $repetitions = $state['repetitions'];
        $oldInterval = $state['interval_days'];
        if (!is_int($repetitions) || $repetitions < 0 || !is_int($oldInterval) || $oldInterval < 0) {
            throw new \InvalidArgumentException('Repetitions and interval_days must be non-negative integers.');
        }
        if ($repetitions > 0 && $oldInterval === 0) {
            throw new \InvalidArgumentException('A previously recalled item must have a positive interval.');
        }
        $efMilli = $this->efToMilli($state['easiness_factor']);
        if ($grade < 3) {
            $newRepetitions = 0;
            $interval = 1;
        } elseif ($repetitions === 0) {
            $newRepetitions = 1;
            $interval = 1;
        } elseif ($repetitions === 1) {
            $newRepetitions = 2;
            $interval = 6;
        } else {
            if ($repetitions === PHP_INT_MAX || $oldInterval > intdiv(PHP_INT_MAX - 999, $efMilli)) {
                throw new \OverflowException('State is too large to calculate safely.');
            }
            $newRepetitions = $repetitions + 1;
            // ceil(I * old_EF) in fixed point, avoiding float rounding at integer boundaries.
            $interval = intdiv($oldInterval * $efMilli + 999, 1000);
        }

        // EF' = EF + 0.1 - (5-q) * (0.08 + (5-q) * 0.02).
        // EF changes after choosing the new interval, including when the answer is wrong.
        $deltaMilli = 100 - (5 - $grade) * (80 + (5 - $grade) * 20);
        if ($efMilli > PHP_INT_MAX - max(0, $deltaMilli)) {
            throw new \OverflowException('Easiness factor is too large.');
        }
        $newEfMilli = max(1300, $efMilli + $deltaMilli);
        $utc = new \DateTimeZone('UTC');
        $at = $reviewedAt->setTimezone($utc);
        $year = (int) $at->format('Y');
        if ($year < 1 || $year > 9999) {
            throw new \InvalidArgumentException('Review time must use a four-digit positive year.');
        }
        $lastSupportedDate = new \DateTimeImmutable('9999-12-31T23:59:59Z');
        $remainingDays = (int) $at->diff($lastSupportedDate)->format('%a');
        if ($interval > $remainingDays) {
            throw new \OverflowException('The next review is outside the supported date range.');
        }
        $due = $at->add(new \DateInterval('P' . $interval . 'D'));
        return [
            'easiness_factor' => $newEfMilli / 1000,
            'repetitions' => $newRepetitions,
            'interval_days' => $interval,
            'last_reviewed_at' => $at->format('Y-m-d\TH:i:s\Z'),
            'due_at' => $due->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** Accept PDO's DECIMAL string as well as numeric domain values. */
    private function efToMilli($value): int
    {
        if (is_string($value)) {
            if (!preg_match('/^[0-9]+(?:\.[0-9]{1,3})?$/D', $value)) {
                throw new \InvalidArgumentException('EF must be a decimal with at most three fractional digits.');
            }
        } elseif (!is_int($value) && !is_float($value)) {
            throw new \InvalidArgumentException('EF must be a numeric decimal, not a boolean or null.');
        }
        $number = (float) $value;
        if (!is_finite($number) || $number < 1.3 || $number > intdiv(PHP_INT_MAX, 1000) - 1) {
            throw new \InvalidArgumentException('EF must be finite, at least 1.3 and safe to calculate.');
        }
        $scaled = $number * 1000;
        $rounded = round($scaled);
        if (abs($scaled - $rounded) > 0.000001) {
            throw new \InvalidArgumentException('EF must have at most three fractional digits.');
        }
        return (int) $rounded;
    }
}
