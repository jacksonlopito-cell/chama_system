<?php
/**
 * Migration Runner: Complete Kenya Administrative Hierarchy
 * 
 * Downloads the official IEBC dataset from GitHub and populates:
 *   - sub_counties (290 constituencies)
 *   - wards (1,450 wards)
 * 
 * Idempotent: safe to run multiple times.
 * 
 * Usage: php migrations/run_004.php
 */

require_once __DIR__ . '/../includes/config.php';

$db = getConnection();

echo "=== Kenya Administrative Hierarchy Migration (004) ===\n\n";

// ---- Step 1: Verify counties are intact ----
$countyCount = $db->query("SELECT COUNT(*) FROM counties")->fetchColumn();
if ($countyCount != 47) {
    die("ERROR: Expected 47 counties, found $countyCount. Aborting.\n");
}
echo "[OK] 47 counties present.\n";

// ---- Step 2: Fetch CSV ----
echo "Fetching IEBC data... ";
$csvUrl = 'https://raw.githubusercontent.com/warrenshiv/Counties-County-Sub-county-wards-in-Kenya/master/list%20of%20counties%2C%20constituencies%2C%20and%20wards.csv';
$csv = @file_get_contents($csvUrl);
if (!$csv) {
    // Try alternate source
    $csvUrl = 'https://raw.githubusercontent.com/its-kios09/osm-kenya-boundaries/main/src/data/wards.json';
    $json = @file_get_contents($csvUrl);
    if (!$json) {
        die("ERROR: Could not fetch data from GitHub.\n");
    }
    echo "fetched JSON.\n";
    $wards_data = json_decode($json, true);
    if (!$wards_data || !isset($wards_data['wards'])) {
        die("ERROR: Invalid JSON format.\n");
    }
    // The JSON format: { wards: [{ ward: "...", constituency: "...", county: "..." }] }
    $rows = [];
    // Need to resolve county names to IDs
    $countyMap = [];
    $counties = $db->query("SELECT id, name, LOWER(TRIM(name)) AS lname FROM counties")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($counties as $c) {
        $countyMap[strtolower(trim($c['name']))] = $c['id'];
    }
    // Also map common variations
    $countyMap['taita taveta'] = $countyMap['taita-taveta'] ?? 0;
    $countyMap['elgeyo/marakwet'] = $countyMap['elgeyo-marakwet'] ?? 0;
    $countyMap['tharaka nithi'] = $countyMap['tharaka-nithi'] ?? 0;
    $countyMap['trans nzoia'] = $countyMap['trans-nzoia'] ?? 0;
    
    foreach ($wards_data['wards'] as $w) {
        $countyName = strtolower(trim($w['county']));
        $cid = $countyMap[$countyName] ?? 0;
        if ($cid) {
            $rows[] = [$cid, trim($w['constituency']), trim($w['ward'])];
        } else {
            echo "WARNING: Unknown county '{$w['county']}' for ward '{$w['ward']}'\n";
        }
    }
    echo "Parsed " . count($rows) . " ward records from JSON.\n";
} else {
    echo "fetched CSV.\n";
    // Parse CSV
    $lines = explode("\n", trim($csv));
    array_shift($lines); // remove header
    $rows = [];
    foreach ($lines as $line) {
        $parts = str_getcsv($line);
        if (count($parts) >= 4 && !empty($parts[2]) && !empty($parts[3])) {
            $rows[] = [(int)$parts[1], trim($parts[2]), trim($parts[3])];
        }
    }
    echo "Parsed " . count($rows) . " ward records from CSV.\n";
}

if (count($rows) < 1400) {
    echo "WARNING: Expected ~1450 wards, found " . count($rows) . ". Proceeding anyway...\n";
}

// ---- Step 3: Build unique sub-county list ----
$subCounties = [];
foreach ($rows as $r) {
    $key = $r[0] . '|' . $r[1];
    $subCounties[$key] = ['county_id' => $r[0], 'name' => $r[1]];
}
$subCounties = array_values($subCounties);
echo "\n[INFO] Unique sub-counties (constituencies): " . count($subCounties) . "\n";
echo "[INFO] Ward records: " . count($rows) . "\n";

// ---- Step 4: Clear existing data (CASCADE deletes wards) ----
echo "\nClearing existing sub_counties and wards (CASCADE)... ";
$db->exec("DELETE FROM sub_counties");
$db->exec("ALTER TABLE sub_counties AUTO_INCREMENT = 1");
$db->exec("ALTER TABLE wards AUTO_INCREMENT = 1");
echo "done.\n";

// ---- Step 5: Add UNIQUE constraints if not exist ----
$dbname = 'chama_system';

$check1 = $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = '$dbname' AND TABLE_NAME = 'sub_counties' AND CONSTRAINT_NAME = 'uq_sub_county_county'")->fetchColumn();
if (!$check1) {
    $db->exec("ALTER TABLE sub_counties ADD CONSTRAINT uq_sub_county_county UNIQUE (county_id, name)");
    echo "[ADDED] Unique constraint on sub_counties(county_id, name)\n";
} else {
    echo "[OK] Unique constraint on sub_counties already exists\n";
}

$check2 = $db->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = '$dbname' AND TABLE_NAME = 'wards' AND CONSTRAINT_NAME = 'uq_ward_sub_county'")->fetchColumn();
if (!$check2) {
    $db->exec("ALTER TABLE wards ADD CONSTRAINT uq_ward_sub_county UNIQUE (sub_county_id, name)");
    echo "[ADDED] Unique constraint on wards(sub_county_id, name)\n";
} else {
    echo "[OK] Unique constraint on wards already exists\n";
}

// ---- Step 6: Insert sub-counties ----
echo "\nInserting sub-counties... ";
$insertSub = $db->prepare("INSERT INTO sub_counties (county_id, name) VALUES (?, ?)");
$count = 0;
foreach ($subCounties as $sc) {
    $insertSub->execute([$sc['county_id'], $sc['name']]);
    $count++;
}
echo "$count inserted.\n";

// ---- Step 7: Insert wards ----
echo "Inserting wards... ";
// Build lookup: county_id|sub_county_name => sub_county_id
$subLookup = [];
$allSubs = $db->query("SELECT id, county_id, name FROM sub_counties")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allSubs as $s) {
    $subLookup[$s['county_id'] . '|' . $s['name']] = $s['id'];
}

$insertWard = $db->prepare("INSERT INTO wards (sub_county_id, name) VALUES (?, ?)");
$wardCount = 0;
$errors = 0;
foreach ($rows as $r) {
    $key = $r[0] . '|' . $r[1];
    $subId = $subLookup[$key] ?? null;
    if ($subId) {
        try {
            $insertWard->execute([$subId, $r[2]]);
            $wardCount++;
        } catch (Exception $e) {
            $errors++;
        }
    } else {
        echo "\nWARNING: No sub_county found for key '$key' (ward: {$r[2]})";
        $errors++;
    }
}
echo "$wardCount inserted ($errors errors).\n";

// ---- Step 8: Verify ----
echo "\n=== VERIFICATION ===\n";
$cCount = $db->query("SELECT COUNT(*) FROM counties")->fetchColumn();
$scCount = $db->query("SELECT COUNT(*) FROM sub_counties")->fetchColumn();
$wCount = $db->query("SELECT COUNT(*) FROM wards")->fetchColumn();
$orphanSC = $db->query("SELECT COUNT(*) FROM sub_counties s LEFT JOIN counties c ON s.county_id=c.id WHERE c.id IS NULL")->fetchColumn();
$orphanW = $db->query("SELECT COUNT(*) FROM wards w LEFT JOIN sub_counties s ON w.sub_county_id=s.id WHERE s.id IS NULL")->fetchColumn();

echo "  Counties:     $cCount (expected 47)\n";
echo "  Sub-Counties: $scCount (expected 290)\n";
echo "  Wards:        $wCount (expected 1450)\n";
echo "  Orphan sub_counties: $orphanSC\n";
echo "  Orphan wards: $orphanW\n";

// Sub-counties per county distribution
echo "\n  Sub-counties per county:\n";
$dist = $db->query("SELECT c.name, COUNT(s.id) AS cnt FROM counties c LEFT JOIN sub_counties s ON s.county_id=c.id GROUP BY c.id ORDER BY c.name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($dist as $d) {
    printf("    %-25s %d\n", $d['name'], $d['cnt']);
}

// Check sub-counties with no wards
$noWards = $db->query("SELECT COUNT(*) FROM sub_counties s WHERE NOT EXISTS (SELECT 1 FROM wards w WHERE w.sub_county_id=s.id)")->fetchColumn();
echo "\n  Sub-counties with zero wards: $noWards (should be 0)\n";

if ($orphanSC == 0 && $orphanW == 0 && $noWards == 0 && $scCount == 290 && $wCount == 1450 && $cCount == 47) {
    echo "\n✅ MIGRATION COMPLETE — All checks passed.\n";
} else {
    echo "\n⚠️  Migration completed but check warnings above.\n";
}
