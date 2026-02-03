<?php
// Test: Listen-Verarbeitung

$markdown = "1. **ÖBV Schulbuch**: Test 1
2. **ÖBV Schulbuch**: Test 2
3. **DocCheck**: Test 3
4. **StudySmarter**: Test 4
5. **AMBOSS**: Test 5";

echo "=== INPUT ===\n";
echo $markdown . "\n\n";

// Simuliere die Listen-Verarbeitung
$html = $markdown;

// Fett
$html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

echo "=== NACH FETT-VERARBEITUNG ===\n";
echo $html . "\n\n";

// Listen-Verarbeitung
$lines_array = explode("\n", $html);
$processed_lines = array();
$in_ul = false;
$in_ol = false;

echo "=== ZEILEN ===\n";
foreach ($lines_array as $idx => $line) {
    echo "[$idx] '$line'\n";
    
    $is_ul_item = preg_match('/^[\*\-]\s+(.+)$/', $line, $ul_matches);
    $is_ol_item = preg_match('/^\d+\.\s+(.+)$/', $line, $ol_matches);
    
    echo "  is_ul_item: " . ($is_ul_item ? 'YES' : 'NO') . "\n";
    echo "  is_ol_item: " . ($is_ol_item ? 'YES' : 'NO') . "\n";
    
    if ($is_ul_item) {
        if (!$in_ul) {
            $processed_lines[] = '<ul>';
            $in_ul = true;
            echo "  -> OPEN <ul>\n";
        }
        if ($in_ol) {
            $processed_lines[] = '</ol>';
            $in_ol = false;
            echo "  -> CLOSE </ol>\n";
        }
        $processed_lines[] = '<li>' . $ul_matches[1] . '</li>';
        echo "  -> ADD <li>\n";
    } elseif ($is_ol_item) {
        if (!$in_ol) {
            $processed_lines[] = '<ol>';
            $in_ol = true;
            echo "  -> OPEN <ol>\n";
        }
        if ($in_ul) {
            $processed_lines[] = '</ul>';
            $in_ul = false;
            echo "  -> CLOSE </ul>\n";
        }
        $processed_lines[] = '<li>' . $ol_matches[1] . '</li>';
        echo "  -> ADD <li>\n";
    } else {
        if ($in_ul) {
            $processed_lines[] = '</ul>';
            $in_ul = false;
            echo "  -> CLOSE </ul> (non-list line)\n";
        }
        if ($in_ol) {
            $processed_lines[] = '</ol>';
            $in_ol = false;
            echo "  -> CLOSE </ol> (non-list line)\n";
        }
        $processed_lines[] = $line;
        echo "  -> ADD line as-is\n";
    }
}

if ($in_ul) {
    $processed_lines[] = '</ul>';
    echo "END: CLOSE </ul>\n";
}
if ($in_ol) {
    $processed_lines[] = '</ol>';
    echo "END: CLOSE </ol>\n";
}

$html = implode("\n", $processed_lines);

echo "\n=== OUTPUT ===\n";
echo $html . "\n";
