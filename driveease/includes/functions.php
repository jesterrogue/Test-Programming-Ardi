
<?php

function e($value) {
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}

function rupiah($nominal) {
    return 'Rp' . number_format(
        (float) $nominal,
        0,
        ',',
        '.'
    );
}

function asset($path) {
    return 'assets/' . ltrim($path, '/');
}
