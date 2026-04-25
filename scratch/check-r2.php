<?php
require_once('../../../../wp-load.php');

$options = [
    'voiceqwen_r2_account_id',
    'voiceqwen_r2_access_key',
    'voiceqwen_r2_secret_key',
    'voiceqwen_r2_bucket_name'
];

echo "R2 Settings Check:\n";
foreach ($options as $opt) {
    $val = get_option($opt);
    $len = strlen($val);
    echo "$opt: " . ($len > 0 ? "EXISTS (length $len)" : "EMPTY") . "\n";
    if ($len > 0 && strpos($opt, 'key') === false) {
        echo "  Value: $val\n";
    }
}
