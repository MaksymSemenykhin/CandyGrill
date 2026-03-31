<?php

declare(strict_types=1);

/**
 * Placeholder until phase 2 JSON API (`command` router).
 */
header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
try {
    echo json_encode([
        'ok' => true,
        'stage' => '1.0',
        'message' => 'Phase 1: config + PDO + migrations. Run `composer migrate` against MySQL. Phase 2 adds the JSON command API.',
    ], JSON_THROW_ON_ERROR);
} catch (JsonException $e) {

}
