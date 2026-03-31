<?php

declare(strict_types=1);

/**
 * Phase 0.1 placeholder — replaced in phase 2 with the real JSON API entrypoint.
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
try {
    echo json_encode([
        'ok' => true,
        'stage' => '0.1',
        'message' => 'Docker / Composer stack is up. Implement phase 1 (config + DB) next.',
    ], JSON_THROW_ON_ERROR);
} catch (JsonException $e) {

}
