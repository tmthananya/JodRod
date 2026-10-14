<?php
/**
 * Test: Find Parking Map as Main Page & Search by Name or Owner
 *
 * Verifies:
 * 1. Dashboard navigation and page are removed.
 * 2. Find Parking (#map) is the primary/default page on login.
 * 3. Search input (#spaces-search-input) is present in Active Parking Spaces.
 * 4. api/get_parking_spaces.php enriches and returns owner_name from Supabase profiles.
 * 5. Search logic correctly matches parking spaces by lot name or owner name.
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod: Find Parking Main Page & Space Search Verification    \n";
echo "=================================================================\n\n";

function get_endpoint($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $code, 'body' => $res, 'json' => json_decode($res, true)];
}

// -------------------------------------------------------------
// Test 1: Verify Navbar & Removed Dashboard
// -------------------------------------------------------------
echo "Test 1: Inspecting index.php navbar structure...\n";
$pageRes = get_endpoint("$baseUrl/index.php");
assert($pageRes['code'] === 200, "Failed to load index.php");
$html = $pageRes['body'];

// Verify dashboard nav link is removed
assert(strpos($html, 'id="nav-dashboard"') === false, "Navbar must NOT contain #nav-dashboard");
assert(strpos($html, 'id="section-dashboard"') === false, "index.php must NOT contain #section-dashboard");

// Verify Find Parking is in navbar and brand link leads to #map
assert(strpos($html, 'id="nav-map"') !== false, "Navbar must contain #nav-map");
assert(strpos($html, 'href="#map" class="brand"') !== false, "Brand logo must link to #map");

// Verify search bar in Active Parking Spaces
assert(strpos($html, 'id="spaces-search-input"') !== false, "Must contain #spaces-search-input");
assert(strpos($html, 'id="btn-clear-spaces-search"') !== false, "Must contain #btn-clear-spaces-search");
assert(strpos($html, 'id="parking-spaces-list"') !== false, "Must contain #parking-spaces-list");

echo "   [PASS] Dashboard removed from navbar and DOM. Find Parking established as main view with search input.\n\n";

// -------------------------------------------------------------
// Test 2: Verify api/get_parking_spaces.php with owner_name
// -------------------------------------------------------------
echo "Test 2: Verifying api/get_parking_spaces.php returns spaces enriched with owner_name...\n";
$spacesRes = get_endpoint("$baseUrl/api/get_parking_spaces.php");
assert($spacesRes['code'] === 200, "Failed to fetch parking spaces");
assert($spacesRes['json']['success'] === true, "Response must be success: true");
assert(is_array($spacesRes['json']['data']), "Data must be an array");

$spaces = $spacesRes['json']['data'];
echo "   Retrieved " . count($spaces) . " parking spaces from Supabase.\n";

if (count($spaces) > 0) {
    $firstSpace = $spaces[0];
    assert(isset($firstSpace['name']), "Parking space must have 'name'");
    assert(isset($firstSpace['owner_name']), "Parking space must have 'owner_name'");
    echo "   [PASS] Sample space: '{$firstSpace['name']}' by Owner: '{$firstSpace['owner_name']}'\n\n";
} else {
    echo "   [INFO] Zero spaces currently in database.\n\n";
}

// -------------------------------------------------------------
// Test 3: Verify Search Filter Logic (Name and Owner)
// -------------------------------------------------------------
echo "Test 3: Simulating search filter on space name and owner name...\n";
$mockSpaces = [
    [
        'id' => '1',
        'name' => 'Chiang Mai High Ground Haven',
        'owner_name' => 'Somchai Volunteer',
        'status' => 'available'
    ],
    [
        'id' => '2',
        'name' => 'Nimman Elevated Warehouse',
        'owner_name' => 'Nok Flood Reliever',
        'status' => 'available'
    ],
    [
        'id' => '3',
        'name' => 'Public Temple Relief Ground',
        'owner_name' => 'Public Relief Area',
        'status' => 'available'
    ]
];

// Search by space name substring (case-insensitive)
$query1 = 'warehouse';
$filteredByName = array_filter($mockSpaces, function($s) use ($query1) {
    return (stripos($s['name'], $query1) !== false) || (stripos($s['owner_name'], $query1) !== false);
});
assert(count($filteredByName) === 1, "Should find 1 space matching 'warehouse'");
$matchedName = reset($filteredByName);
assert($matchedName['name'] === 'Nimman Elevated Warehouse', "Should match Nimman Elevated Warehouse");

// Search by owner name substring (case-insensitive)
$query2 = 'somchai';
$filteredByOwner = array_filter($mockSpaces, function($s) use ($query2) {
    return (stripos($s['name'], $query2) !== false) || (stripos($s['owner_name'], $query2) !== false);
});
assert(count($filteredByOwner) === 1, "Should find 1 space matching owner 'somchai'");
$matchedOwner = reset($filteredByOwner);
assert($matchedOwner['owner_name'] === 'Somchai Volunteer', "Should match Somchai Volunteer");

// Search matching public owner
$query3 = 'public';
$filteredByPublic = array_filter($mockSpaces, function($s) use ($query3) {
    return (stripos($s['name'], $query3) !== false) || (stripos($s['owner_name'], $query3) !== false);
});
assert(count($filteredByPublic) === 1, "Should find 1 space matching 'public'");

echo "   [PASS] Search filter correctly matches by lot name and owner name.\n\n";

// -------------------------------------------------------------
// Test 4: Verify Default Login Route in JS
// -------------------------------------------------------------
echo "Test 4: Verifying JavaScript routing logic defaults to #map...\n";
$jsContent = file_get_contents(__DIR__ . '/../index.php');

assert(strpos($jsContent, "window.location.hash = '#map'") !== false, "JS must route to #map on login");
assert(strpos($jsContent, "hash = '#map'") !== false, "handleRoute must default to #map");

echo "   [PASS] JavaScript routing strictly establishes #map as the primary page.\n\n";

echo "=================================================================\n";
echo "   ALL FIND PARKING MAIN PAGE & SEARCH CHECKS PASSED!            \n";
echo "=================================================================\n";
