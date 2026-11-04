<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this test from the PHP command line.');
}

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Modules\Reviews\Services\Sm2Scheduler;

$scheduler = new Sm2Scheduler();
$checks = 0;

function checkSm2(bool $condition, string $message): void
{
    global $checks;

    if (!$condition) {
        fwrite(STDERR, 'FAIL: ' . $message . PHP_EOL);
        exit(1);
    }

    $checks++;
}

function expectSm2Error(
    callable $operation,
    string $class,
    string $message
): void {
    try {
        $operation();
    } catch (Throwable $error) {
        checkSm2(
            $error instanceof $class,
            $message . ' (' . get_class($error) . ')'
        );
        return;
    }

    checkSm2(false, $message . ' (no exception)');
}

// Ten, EF cu, so lan nho cu, khoang on cu, muc danh gia,
// EF moi, so lan nho moi, khoang on moi, ngay on tiep theo.
$cases = [
    ['first q4', 2.5, 0, 0, 4, 2.5, 1, 1, '2026-11-05T02:00:00Z'],
    ['first q5', 2.5, 0, 0, 5, 2.6, 1, 1, '2026-11-05T02:00:00Z'],
    ['second q4', 2.5, 1, 1, 4, 2.5, 2, 6, '2026-11-10T02:00:00Z'],
    ['third q4', 2.5, 2, 6, 4, 2.5, 3, 15, '2026-11-19T02:00:00Z'],
    ['use old EF on q5', 2.5, 2, 6, 5, 2.6, 3, 15, '2026-11-19T02:00:00Z'],
    ['ceil 13.08 to 14', 2.18, 2, 6, 4, 2.18, 3, 14, '2026-11-18T02:00:00Z'],
    ['wrong q2', 2.5, 4, 38, 2, 2.18, 0, 1, '2026-11-05T02:00:00Z'],
    ['blackout q0', 2.5, 3, 15, 0, 1.7, 0, 1, '2026-11-05T02:00:00Z'],
    ['wrong q1', 2.5, 3, 15, 1, 1.96, 0, 1, '2026-11-05T02:00:00Z'],
    ['hard q3', 2.5, 2, 6, 3, 2.36, 3, 15, '2026-11-19T02:00:00Z'],
    ['EF floor', 1.31, 2, 6, 0, 1.3, 0, 1, '2026-11-05T02:00:00Z'],
    ['exact decimal boundary', 2.36, 3, 25, 4, 2.36, 4, 59, '2027-01-02T02:00:00Z'],
    ['PDO decimal string', '2.500', 2, 6, 4, 2.5, 3, 15, '2026-11-19T02:00:00Z'],
];

$at = new DateTimeImmutable('2026-11-04T02:00:00Z');

foreach ($cases as $case) {
    [
        $name, $ef, $reps, $interval, $q,
        $wantEf, $wantReps, $wantInterval, $wantDue
    ] = $case;

    $old = [
        'easiness_factor' => $ef,
        'repetitions' => $reps,
        'interval_days' => $interval,
    ];

    $copy = $old;
    $result = $scheduler->schedule($old, $q, $at);

    checkSm2(
        abs($result['easiness_factor'] - $wantEf) < 0.000001,
        $name . ': EF'
    );

    checkSm2(
        $result['repetitions'] === $wantReps,
        $name . ': repetitions'
    );

    checkSm2(
        $result['interval_days'] === $wantInterval,
        $name . ': interval'
    );

    checkSm2($result['due_at'] === $wantDue, $name . ': due date');

    checkSm2(
        $result['last_reviewed_at'] === '2026-11-04T02:00:00Z',
        $name . ': actual review time'
    );

    checkSm2($old === $copy, $name . ': does not mutate input');

    checkSm2(
        $result === $scheduler->schedule($old, $q, $at),
        $name . ': deterministic'
    );
}

// On tre: ngay moi tinh tu luc thuc su on.
$late = $scheduler->schedule(
    [
        'easiness_factor' => 2.5,
        'repetitions' => 2,
        'interval_days' => 6,
        'due_at' => '2026-09-01T02:00:00Z',
    ],
    4,
    new DateTimeImmutable('2026-11-03T02:00:00Z')
);

checkSm2(
    $late['due_at'] === '2026-11-18T02:00:00Z',
    'Late review schedules from actual review time'
);

// Chuyen gio Viet Nam sang UTC.
$vietnam = $scheduler->schedule(
    $scheduler->initialState(),
    4,
    new DateTimeImmutable('2026-11-04T00:01:00+07:00')
);

checkSm2(
    $vietnam['last_reviewed_at'] === '2026-11-03T17:01:00Z',
    'Convert Vietnam review time to UTC'
);

checkSm2(
    $vietnam['due_at'] === '2026-11-04T17:01:00Z',
    'UTC next due remains one day later'
);

// Tu choi muc danh gia sai kieu hoac ngoai 0-5.
foreach ([-1, 6, 2.5, '4', null, true, false] as $badGrade) {
    expectSm2Error(
        static function () use ($scheduler, $at, $badGrade): void {
            $scheduler->schedule(
                $scheduler->initialState(),
                $badGrade,
                $at
            );
        },
        InvalidArgumentException::class,
        'Reject invalid grade ' . var_export($badGrade, true)
    );
}

// Tu choi trang thai khong hop le.
$badStates = [
    [],
    ['easiness_factor' => 1.2, 'repetitions' => 0, 'interval_days' => 0],
    ['easiness_factor' => true, 'repetitions' => 0, 'interval_days' => 0],
    ['easiness_factor' => INF, 'repetitions' => 0, 'interval_days' => 0],
    ['easiness_factor' => NAN, 'repetitions' => 0, 'interval_days' => 0],
    ['easiness_factor' => '2.5004', 'repetitions' => 0, 'interval_days' => 0],
    ['easiness_factor' => 2.5, 'repetitions' => -1, 'interval_days' => 0],
    ['easiness_factor' => 2.5, 'repetitions' => '1', 'interval_days' => 1],
    ['easiness_factor' => 2.5, 'repetitions' => 1, 'interval_days' => 0],
];

foreach ($badStates as $index => $badState) {
    expectSm2Error(
        static function () use ($scheduler, $at, $badState): void {
            $scheduler->schedule($badState, 4, $at);
        },
        InvalidArgumentException::class,
        'Reject invalid state #' . $index
    );
}

// Kiem tra tran so.
expectSm2Error(
    static function () use ($scheduler, $at): void {
        $scheduler->schedule(
            [
                'easiness_factor' => 2.5,
                'repetitions' => PHP_INT_MAX,
                'interval_days' => 6,
            ],
            4,
            $at
        );
    },
    OverflowException::class,
    'Repetition overflow'
);

// Kiem tra tran ngay.
expectSm2Error(
    static function () use ($scheduler): void {
        $scheduler->schedule(
            $scheduler->initialState(),
            4,
            new DateTimeImmutable('9999-12-31T23:59:59Z')
        );
    },
    OverflowException::class,
    'Date overflow'
);

echo 'PASS: ' . $checks . ' checks for T1 SM-2.' . PHP_EOL;