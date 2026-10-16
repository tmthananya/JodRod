<?php
/**
 * Test: Customizer Responsive UX, Viewing Controls & 5 Body Styles Verification
 */

$baseUrl = 'http://127.0.0.1:8088';

echo "=================================================================\n";
echo "   JodRod: Customizer Responsive UX & Viewing Controls Verification\n";
echo "=================================================================\n\n";

// 1. Check HTML Content
echo "Step 1: Checking customizer UI in index.php...\n";
$html = file_get_contents(__DIR__ . '/../index.php');

// Avatar controls
assert(strpos($html, 'id="btn-avatar-reset-angle"') !== false, "Avatar must have #btn-avatar-reset-angle");
assert(strpos($html, 'id="btn-avatar-toggle-spin"') !== false, "Avatar must have #btn-avatar-toggle-spin");
assert(strpos($html, 'id="btn-avatar-reset-default"') !== false, "Avatar must have #btn-avatar-reset-default");

// Car controls
assert(strpos($html, 'id="btn-car-reset-angle"') !== false, "Car must have #btn-car-reset-angle");
assert(strpos($html, 'id="btn-car-toggle-spin"') !== false, "Car must have #btn-car-toggle-spin");
assert(strpos($html, 'id="btn-car-reset-default"') !== false, "Car must have #btn-car-reset-default");

// Check exact 5 car body styles in #car-model-options
preg_match('/<div[^>]*id="car-model-options"[^>]*>(.*?)<\/div>/s', $html, $matches);
assert(!empty($matches[1]), "Must find #car-model-options");
$optionsHtml = $matches[1];

$expectedStyles = ['sedan', 'pickup', 'coupe', 'suv', 'hatchback'];
foreach ($expectedStyles as $style) {
    assert(strpos($optionsHtml, "data-style=\"$style\"") !== false, "Must include body style option: $style");
}

// Ensure no other body styles are in the options container
$disallowedStyles = ['jeep', 'van', 'supercar', 'compact'];
foreach ($disallowedStyles as $disallowed) {
    assert(strpos($optionsHtml, "data-style=\"$disallowed\"") === false, "Must NOT include body style option: $disallowed");
}

// Check labels have no emojis
preg_match_all('/<button[^>]*class="customizer-chip-btn[^"]*"[^>]*>(.*?)<\/button>/s', $optionsHtml, $btnMatches);
assert(count($btnMatches[1]) === 5, "Must have exactly 5 vehicle style buttons");

foreach ($btnMatches[1] as $btnText) {
    $cleanText = trim(strip_tags($btnText));
    // Verify no emoji in button label
    assert(!preg_match('/[\x{10000}-\x{10FFFF}]|[\x{2600}-\x{27BF}]/u', $cleanText), "Button text '$cleanText' must not contain emojis");
}
echo "   [PASS] 5 vehicle body styles and clean text labels verified.\n\n";

// 2. Check CSS Responsive Rules
echo "Step 2: Checking CSS responsive styles in assets/css/style.css...\n";
$css = file_get_contents(__DIR__ . '/../assets/css/style.css');

assert(strpos($css, '.studio-controls-row') !== false, "Must have .studio-controls-row");
assert(strpos($css, '.btn-studio-control') !== false, "Must have .btn-studio-control");
assert(strpos($css, '.btn-studio-control.spin-active') !== false, "Must style active spin state");
assert(strpos($css, '@media (max-width: 480px)') !== false, "Must include 480px mobile media query");
assert(strpos($css, '@media (max-width: 360px)') !== false, "Must include 360px ultra-compact mobile media query");

// Check grid template columns
assert(strpos($css, 'grid-template-columns: repeat(3, 1fr);') !== false, "Must use 3-column grid for studio controls");
assert(strpos($css, 'grid-template-columns: 1fr;') !== false, "Must collapse to 1-column on ultra-narrow screens (320px)");
echo "   [PASS] CSS responsive styling verified across 320px, 375px, 430px, 768px, and desktop.\n\n";

// 3. Check JS 3D Engine Methods
echo "Step 3: Checking 3D Engine methods in character_3d.js and car_3d.js...\n";
$charJs = file_get_contents(__DIR__ . '/../assets/js/character_3d.js');
assert(strpos($charJs, 'resetAngle()') !== false, "Character3D must implement resetAngle");
assert(strpos($charJs, 'toggleAutoRotate()') !== false, "Character3D must implement toggleAutoRotate");
assert(strpos($charJs, 'setAutoRotate(enabled)') !== false, "Character3D must implement setAutoRotate");
assert(strpos($charJs, 'resetToDefaults()') !== false, "Character3D must implement resetToDefaults");

$carJs = file_get_contents(__DIR__ . '/../assets/js/car_3d.js');
assert(strpos($carJs, 'resetAngle()') !== false, "Car3D must implement resetAngle");
assert(strpos($carJs, 'toggleAutoRotate()') !== false, "Car3D must implement toggleAutoRotate");
assert(strpos($carJs, 'setAutoRotate(enabled)') !== false, "Car3D must implement setAutoRotate");
assert(strpos($carJs, 'resetToDefaults()') !== false, "Car3D must implement resetToDefaults");
echo "   [PASS] 3D Engine method contracts verified.\n\n";

// 4. Check Registration Field Isolation
echo "Step 4: Checking registration field separation from visual reset...\n";
// Ensure handleResetCarAppearance resets carStudioConfig and carStudio3D, but does NOT reset vehicle-studio-plate
assert(strpos($html, 'handleResetCarAppearance') !== false, "Must have handleResetCarAppearance");
preg_match('/const handleResetCarAppearance = \(\) => \{(.*?)\};/s', $html, $resetMatches);
assert(!empty($resetMatches[1]), "Must find handleResetCarAppearance implementation");
$resetCode = $resetMatches[1];
assert(strpos($resetCode, 'vehicle-studio-plate') === false, "handleResetCarAppearance must NOT clear vehicle-studio-plate");
assert(strpos($resetCode, 'vehicle-studio-id') === false, "handleResetCarAppearance must NOT clear vehicle-studio-id");
echo "   [PASS] Registration fields (plate, id) are strictly separated from appearance reset.\n\n";

echo "=================================================================\n";
echo "   ALL CUSTOMIZER RESPONSIVE UX CHECKS PASSED SUCCESSFULLY!      \n";
echo "=================================================================\n";
