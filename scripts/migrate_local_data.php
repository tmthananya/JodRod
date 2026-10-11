<?php
/**
 * JodRod Local Data Migration & Export Script
 * 
 * Safely inspects, backs up, validates, and imports local fallback data
 * into the authoritative Supabase database.
 * 
 * Usage:
 *   php scripts/migrate_local_data.php --dry-run
 *   php scripts/migrate_local_data.php --backup
 *   php scripts/migrate_local_data.php --import
 *   php scripts/migrate_local_data.php --import --dry-run
 */

require_once __DIR__ . '/../config.php';

$options = getopt('', ['dry-run', 'backup', 'import', 'export', 'help']);

if (isset($options['help'])) {
    echo <<<HELP
JodRod Local Data Migration CLI
================================
Options:
  --dry-run   Simulate migration without modifying Supabase or local files.
  --backup    Create a timestamped backup of local JSON files.
  --export    Export sanitized, mapped data to data/export_mapped_spaces.json.
  --import    Import verified records into Supabase parking_spaces.
  --help      Show this help message.

HELP;
    exit(0);
}

$isDryRun = isset($options['dry-run']) || (!isset($options['import']) && !isset($options['backup']) && !isset($options['export']));
$doBackup = isset($options['backup']) || isset($options['import']);
$doImport = isset($options['import']);
$doExport = isset($options['export']) || $isDryRun;

echo "========================================================\n";
echo "  JodRod Local Data Migration to Supabase\n";
echo "  Mode: " . ($isDryRun ? "DRY-RUN (Safe / Read-Only)" : "LIVE IMPORT") . "\n";
echo "========================================================\n\n";

$localFile = DATA_DIR . '/parking_spaces.json';

if (!file_exists($localFile)) {
    echo "[!] No local parking_spaces.json file found at {$localFile}.\n";
    exit(0);
}

$raw = file_get_contents($localFile);
$localSpaces = json_decode($raw, true);

if (!is_array($localSpaces)) {
    echo "[!] Local file contains invalid JSON.\n";
    exit(1);
}

$totalLocal = count($localSpaces);
echo "[1] Discovered {$totalLocal} local parking space records in data/parking_spaces.json\n\n";

// 1. Create Timestamped Backup
if ($doBackup) {
    $timestamp = date('Ymd_His');
    $backupFile = DATA_DIR . "/backup_parking_spaces_{$timestamp}.json";
    if (file_put_contents($backupFile, $raw)) {
        echo "  [✓] Backup created: {$backupFile}\n";
    } else {
        echo "  [!] Failed to create backup file.\n";
        exit(1);
    }
}

// 2. Fetch Existing Supabase Parking Spaces to prevent duplicate insertions
echo "\n[2] Checking live Supabase parking_spaces...\n";
$supabaseSpaces = [];
if (SUPABASE_LIVE_MODE) {
    $res = supabase_request('/rest/v1/parking_spaces?select=id,name,latitude,longitude,capacity,available_spaces,status,owner_id', 'GET');
    if ($res['success'] && is_array($res['data'])) {
        $supabaseSpaces = $res['data'];
        echo "  [✓] Connected to Supabase. Live count: " . count($supabaseSpaces) . " spaces.\n";
    } else {
        echo "  [!] Could not query Supabase: " . ($res['error'] ?? 'Unknown error') . "\n";
        if ($doImport) exit(1);
    }
} else {
    echo "  [!] Supabase Live Mode is NOT configured in .env.\n";
    if ($doImport) exit(1);
}

$existingIds = array_column($supabaseSpaces, 'id');
$existingSignatures = [];
foreach ($supabaseSpaces as $s) {
    $sig = sprintf('%s|%.4f|%.4f', strtolower(trim($s['name'])), (float)$s['latitude'], (float)$s['longitude']);
    $existingSignatures[$sig] = $s['id'];
}

// 3. Analyze and Map Local Records
echo "\n[3] Analyzing and Mapping Records...\n";

$validRecords = [];
$skippedDuplicates = [];
$alreadyImported = [];
$seenSignaturesInBatch = [];

foreach ($localSpaces as $idx => $space) {
    $id = trim($space['id'] ?? '');
    $name = trim($space['name'] ?? '');
    $lat = isset($space['latitude']) ? (float)$space['latitude'] : null;
    $lng = isset($space['longitude']) ? (float)$space['longitude'] : null;
    $cap = isset($space['capacity']) ? (int)$space['capacity'] : null;
    $avail = isset($space['available_spaces']) ? (int)$space['available_spaces'] : null;
    $status = trim($space['status'] ?? 'available');
    $ownerId = !empty($space['owner_id']) ? trim($space['owner_id']) : null;

    // Validate structure
    if (!$id || !$name || $lat === null || $lng === null || $cap === null || $avail === null) {
        echo "  [-] Row {$idx}: Incomplete fields. Skipping.\n";
        continue;
    }

    // Check if ID already exists in Supabase
    if (in_array($id, $existingIds, true)) {
        $alreadyImported[] = ['id' => $id, 'name' => $name, 'reason' => 'UUID already exists in Supabase'];
        continue;
    }

    // Check for exact coordinate/name signature in live database
    $sig = sprintf('%s|%.4f|%.4f', strtolower($name), $lat, $lng);
    if (isset($existingSignatures[$sig])) {
        $alreadyImported[] = ['id' => $id, 'name' => $name, 'reason' => 'Identical lot already exists in Supabase (ID: ' . $existingSignatures[$sig] . ')'];
        continue;
    }

    // Check for batch-internal duplicate
    if (isset($seenSignaturesInBatch[$sig])) {
        $skippedDuplicates[] = [
            'id' => $id, 
            'name' => $name, 
            'duplicate_of' => $seenSignaturesInBatch[$sig]
        ];
        continue;
    }

    $seenSignaturesInBatch[$sig] = $id;

    $mappedRecord = [
        'id'               => $id,
        'name'             => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        'latitude'         => $lat,
        'longitude'        => $lng,
        'capacity'         => $cap,
        'available_spaces' => $avail,
        'status'           => in_array($status, ['available', 'full', 'closed'], true) ? $status : 'available',
        'owner_id'         => $ownerId // NULL for community spaces without matched auth accounts
    ];

    $validRecords[] = $mappedRecord;
}

echo "  Ready to import:       " . count($validRecords) . "\n";
echo "  Duplicate in batch:    " . count($skippedDuplicates) . "\n";
echo "  Already in Supabase:   " . count($alreadyImported) . "\n\n";

if (!empty($skippedDuplicates)) {
    echo "[!] Internal Duplicates Detected in Local File:\n";
    foreach ($skippedDuplicates as $d) {
        echo "  - ID {$d['id']} ('{$d['name']}') is duplicate of {$d['duplicate_of']}\n";
    }
    echo "\n";
}

if (!empty($alreadyImported)) {
    echo "[i] Already Present in Supabase (will not re-insert):\n";
    foreach ($alreadyImported as $a) {
        echo "  - ID {$a['id']} ('{$a['name']}'): {$a['reason']}\n";
    }
    echo "\n";
}

// 4. Export Mapped Data
if ($doExport) {
    $exportFile = DATA_DIR . '/export_mapped_spaces.json';
    file_put_contents($exportFile, json_encode($validRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "[✓] Mapped data exported to {$exportFile}\n\n";
}

// 5. Execute Live Import if requested
if ($doImport && !$isDryRun) {
    if (empty($validRecords)) {
        echo "[✓] No new records to import. Supabase is already up to date.\n";
    } else {
        echo "[4] Executing Live Import to Supabase...\n";
        $importedCount = 0;
        $failedCount = 0;

        foreach ($validRecords as $rec) {
            $res = supabase_request('/rest/v1/parking_spaces', 'POST', $rec);
            if ($res['success']) {
                $importedCount++;
                echo "  [✓] Imported: {$rec['id']} - '{$rec['name']}'\n";
            } else {
                $failedCount++;
                echo "  [!] Failed to import {$rec['id']}: " . ($res['error'] ?? 'Unknown error') . "\n";
            }
        }

        echo "\nImport Finished: {$importedCount} succeeded, {$failedCount} failed.\n";
    }
} else {
    echo "[i] Dry run complete. Run with --import to execute live database import.\n";
}

echo "========================================================\n";
