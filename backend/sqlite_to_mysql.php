<?php
/**
 * Copy all tables from SQLite → MySQL (seas), chunked inserts.
 * Run after: php artisan migrate:fresh
 */
ini_set('memory_limit', '1024M');
set_time_limit(0);

$sqlitePath = __DIR__ . '/database/database.sqlite';
$mysqlDsn = 'mysql:host=127.0.0.1;port=3307;dbname=seas;charset=utf8mb4';

$src = new PDO('sqlite:' . $sqlitePath);
$src->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dst = new PDO($mysqlDsn, 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$dst->exec('SET NAMES utf8mb4');
$dst->exec('SET FOREIGN_KEY_CHECKS=0');
$dst->exec('SET UNIQUE_CHECKS=0');
$dst->exec('SET SESSION sql_mode=""');
try {
    $dst->exec('SET SESSION max_allowed_packet=67108864');
} catch (Throwable $e) {
    // ignore if not permitted
}

$preferred = [
    'migrations',
    'users',
    'password_reset_tokens',
    'sessions',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
    'failed_jobs',
    'personal_access_tokens',
    'regions',
    'circles',
    'divisions',
    'substations',
    'feeders',
    'dtrs',
    'zones',
    'consumers',
    'poles',
    'user_scopes',
    'work_assignments',
    'dtr_surveys',
    'feeder_surveys',
    'feeder_survey_sld_photos',
    'consumer_surveys',
    'activity_logs',
    'app_notifications',
];

$sqliteTables = $src->query(
    "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
)->fetchAll(PDO::FETCH_COLUMN);

$mysqlTables = $dst->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

$tables = [];
foreach ($preferred as $t) {
    if (in_array($t, $sqliteTables, true) && in_array($t, $mysqlTables, true)) {
        $tables[] = $t;
    }
}
foreach ($sqliteTables as $t) {
    if (!in_array($t, $tables, true) && in_array($t, $mysqlTables, true)) {
        $tables[] = $t;
    }
}

$chunkSize = 500; // keep under MariaDB max_allowed_packet=1M

foreach ($tables as $table) {
    $srcCount = (int) $src->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo '[' . date('H:i:s') . "] $table: $srcCount rows ... ";
    flush();

    $dst->exec("TRUNCATE TABLE `$table`");

    if ($srcCount === 0) {
        echo "skip empty\n";
        continue;
    }

    $srcCols = $src->query("PRAGMA table_info(`$table`)")->fetchAll(PDO::FETCH_ASSOC);
    $srcColNames = array_column($srcCols, 'name');

    $dstCols = $dst->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
    $dstColNames = array_column($dstCols, 'Field');

    $cols = array_values(array_intersect($srcColNames, $dstColNames));
    if (count($cols) === 0) {
        echo "NO overlapping columns\n";
        continue;
    }

    $colList = '`' . implode('`,`', $cols) . '`';
    $placeholders = '(' . implode(',', array_fill(0, count($cols), '?')) . ')';

    $offset = 0;
    $inserted = 0;
    $colSelect = '`' . implode('`,`', $cols) . '`';

    while ($offset < $srcCount) {
        $lim = (int) $chunkSize;
        $off = (int) $offset;
        $rows = $src->query("SELECT $colSelect FROM `$table` LIMIT $lim OFFSET $off")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) === 0) {
            break;
        }

        $valuesSql = [];
        $params = [];
        foreach ($rows as $row) {
            $valuesSql[] = $placeholders;
            foreach ($cols as $c) {
                $params[] = $row[$c];
            }
        }

        $sql = "INSERT INTO `$table` ($colList) VALUES " . implode(',', $valuesSql);
        try {
            $ins = $dst->prepare($sql);
            $ins->execute($params);
        } catch (Throwable $e) {
            // Fallback: row-by-row for problematic chunks
            echo "\n  chunk@$offset failed (" . $e->getMessage() . "), row-by-row... ";
            flush();
            $single = $dst->prepare("INSERT INTO `$table` ($colList) VALUES $placeholders");
            foreach ($rows as $row) {
                $p = [];
                foreach ($cols as $c) {
                    $p[] = $row[$c];
                }
                try {
                    $single->execute($p);
                    $inserted++;
                } catch (Throwable $e2) {
                    echo "\n  SKIP row in $table: " . $e2->getMessage() . "\n";
                }
            }
            $offset += $chunkSize;
            continue;
        }

        $inserted += count($rows);
        $offset += $chunkSize;

        if ($table === 'consumers' && ($inserted % 50000 === 0)) {
            echo "$inserted ";
            flush();
        }
    }

    $dstCount = (int) $dst->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
    echo "→ mysql=$dstCount " . ($dstCount === $srcCount ? 'OK' : 'MISMATCH') . "\n";
    flush();
}

$dst->exec('SET FOREIGN_KEY_CHECKS=1');
$dst->exec('SET UNIQUE_CHECKS=1');

echo "\nDone at " . date('H:i:s') . "\n";
