<?php

declare(strict_types=1);

require_once __DIR__ . '/Database.php';

function runStats(PgSql\Connection $connection, int $runId): array
{
    $result = queryParams(
        $connection,
        "SELECT r.id AS run_id, r.project_id, r.label, r.config_hash, r.source, r.started_at, r.finished_at,
                COUNT(res.id)::int AS total,
                COUNT(res.id) FILTER (WHERE res.outcome = 'PASS')::int AS pass_count,
                COUNT(res.id) FILTER (WHERE res.outcome = 'FAIL')::int AS fail_count,
                COUNT(res.id) FILTER (WHERE res.outcome = 'ERROR')::int AS error_count
         FROM runs r
         LEFT JOIN run_results res ON res.run_id = r.id
         WHERE r.id = $1
         GROUP BY r.id, r.project_id, r.label, r.config_hash, r.source, r.started_at, r.finished_at",
        [$runId]
    );

    $row = pg_fetch_assoc($result);
    if (!$row) {
        return [];
    }

    return [
        'run_id' => (int)$row['run_id'],
        'project_id' => (int)$row['project_id'],
        'label' => (string)$row['label'],
        'config_hash' => (string)$row['config_hash'],
        'source' => (string)$row['source'],
        'started_at' => (string)$row['started_at'],
        'finished_at' => $row['finished_at'] ? (string)$row['finished_at'] : null,
        'total' => (int)$row['total'],
        'pass_count' => (int)$row['pass_count'],
        'fail_count' => (int)$row['fail_count'],
        'error_count' => (int)$row['error_count'],
    ];
}
