<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "=== Testing Coordinate Conversions ===\n\n";

// Test 1: DMS format
echo "Test 1: DMS Format\n";
$dms = dms_to_decimal('8°09\'56.6"N', '4°15\'56.9"E');
if ($dms) {
    echo "✓ DMS conversion successful!\n";
    echo "  Input: 8°09'56.6\"N 4°15'56.9\"E\n";
    echo "  Output: {$dms['lat']}, {$dms['lng']}\n";
} else {
    echo "✗ DMS conversion failed\n";
}

// Test 2: Plus Code
echo "\nTest 2: Plus Code\n";
$plus = pluscode_to_decimal('6FRR5274+P6');
if ($plus) {
    echo "✓ Plus Code conversion successful!\n";
    echo "  Input: 6FRR5274+P6\n";
    echo "  Output: {$plus['lat']}, {$plus['lng']}\n";
} else {
    echo "✗ Plus Code conversion failed\n";
}

// Test 3: Parse coordinates (decimal)
echo "\nTest 3: Parse Coordinates (Decimal)\n";
$decimal = parse_coordinates('8.165722', '4.265806');
if ($decimal) {
    echo "✓ Decimal parsing successful!\n";
    echo "  Input: 8.165722, 4.265806\n";
    echo "  Output: {$decimal['lat']}, {$decimal['lng']}\n";
} else {
    echo "✗ Decimal parsing failed\n";
}

// Test 4: Parse coordinates (comma-separated)
echo "\nTest 4: Parse Coordinates (Comma-Separated)\n";
$comma = parse_coordinates('8.165722, 4.265806');
if ($comma) {
    echo "✓ Comma-separated parsing successful!\n";
    echo "  Input: 8.165722, 4.265806\n";
    echo "  Output: {$comma['lat']}, {$comma['lng']}\n";
} else {
    echo "✗ Comma-separated parsing failed\n";
}

// Test 5: Parse coordinates (Plus Code single input)
echo "\nTest 5: Parse Coordinates (Plus Code)\n";
$plusParse = parse_coordinates('6FRR5274+P6');
if ($plusParse) {
    echo "✓ Plus Code parsing successful!\n";
    echo "  Input: 6FRR5274+P6\n";
    echo "  Output: {$plusParse['lat']}, {$plusParse['lng']}\n";
} else {
    echo "✗ Plus Code parsing failed\n";
}

echo "\n=== All tests complete! ===\n";
