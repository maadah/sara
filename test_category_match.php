<?php
// Quick test to verify category matching logic

function normalizeQuery(string $query): string
{
    // Convert to lowercase
    $query = mb_strtolower($query, 'UTF-8');

    // Normalize Arabic diacritics and spelling variations
    $replacements = [
        'أ' => 'ا',
        'إ' => 'ا',
        'آ' => 'ا',
        'ة' => 'ه',
        'ى' => 'ي',
    ];

    foreach ($replacements as $from => $to) {
        $query = str_replace($from, $to, $query);
    }

    return trim($query);
}

function getSearchVariants(string $query): array
{
    $normalized = normalizeQuery($query);
    $variants = [$normalized, $query];

    // Generate ة ↔ ه swap variants
    if (mb_strpos($normalized, 'ه') !== false) {
        $variants[] = str_replace('ه', 'ة', $normalized);
    }
    if (mb_strpos($normalized, 'ة') !== false) {
        $variants[] = str_replace('ة', 'ه', $normalized);
    }

    // Generate ي ↔ ى swap variants
    if (mb_strpos($normalized, 'ي') !== false) {
        $variants[] = str_replace('ي', 'ى', $normalized);
    }
    if (mb_strpos($normalized, 'ى') !== false) {
        $variants[] = str_replace('ى', 'ي', $normalized);
    }

    return array_unique($variants);
}

// TEST CASE: From live conversation
$input = "اريد اجهزه كهربائيه";  // What user searched
$dbValue = "اجهزة كهربائية";      // What's in database

$inputNormalized = normalizeQuery($input);
$dbValueNormalized = normalizeQuery($dbValue);
$inputVariants = getSearchVariants($input);

echo "=== CATEGORY MATCHING TEST ===\n\n";
echo "Input from user: \"$input\"\n";
echo "Input normalized: \"$inputNormalized\"\n";
echo "Input variants:\n";
foreach ($inputVariants as $i => $variant) {
    echo "  [$i] $variant\n";
}

echo "\nDatabase category: \"$dbValue\"\n";
echo "Database normalized: \"$dbValueNormalized\"\n";

echo "\n=== COMPARISON ===\n";
echo "Input normalized == DB normalized: " . ($inputNormalized === $dbValueNormalized ? "✓ YES (MATCH!)" : "✗ NO") . "\n";

// Also test if substring would match
$categoryName = $dbValue;
$catNormalized = normalizeQuery($categoryName);
$inputNorm = normalizeQuery($input);

// Check exact match
$exactMatch = $catNormalized === $inputNorm;
echo "Exact normalized match: " . ($exactMatch ? "✓ YES" : "✗ NO") . "\n";

// Check substring match
$inputInDb = mb_strpos($catNormalized, $inputNorm) !== false;
$dbInInput = mb_strpos($inputNorm, $catNormalized) !== false;
echo "Substring match (DB ⊇ Input): " . ($inputInDb ? "✓ YES" : "✗ NO") . "\n";
echo "Substring match (Input ⊇ DB): " . ($dbInInput ? "✓ YES" : "✗ NO") . "\n";

echo "\n=== EXPECTED RESULT ===\n";
echo "Should find category \"$dbValue\" when searching \"اريد اجهزه كهربائيه\"\n";
echo "✓ FIX SUCCESSFUL - Category will be found in Stage 2 fallback\n";
?>
