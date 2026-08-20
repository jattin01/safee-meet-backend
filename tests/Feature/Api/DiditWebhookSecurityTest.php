<?php

it('rejects an invalid Didit webhook signature', function () {
    config(['services.didit.webhook_secret' => 'test-webhook-secret']);

    $payload = json_encode([
        'event_id' => '00000000-0000-0000-0000-000000000001',
        'timestamp' => now()->timestamp,
        'session_id' => '00000000-0000-0000-0000-000000000002',
        'status' => 'Approved',
        'webhook_type' => 'status.updated',
        'decision' => new stdClass,
    ], JSON_UNESCAPED_SLASHES);

    $this->call(
        'POST',
        '/api/webhooks/didit',
        server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TIMESTAMP' => (string) now()->timestamp,
            'HTTP_X_SIGNATURE' => 'invalid',
        ],
        content: $payload,
    )->assertUnauthorized();
});
