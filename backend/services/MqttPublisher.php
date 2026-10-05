<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

function publishMqttCommand(string $topic, string $payload): void
{
    $host = trim((string) env('MQTT_HOST', ''));
    $port = (int) env('MQTT_PORT', '8883');
    $username = env('MQTT_USERNAME');
    $password = env('MQTT_PASSWORD');
    $useTls = filter_var(env('MQTT_TLS', 'true'), FILTER_VALIDATE_BOOL);
    $clientId = trim((string) env('MQTT_CLIENT_ID', 'pool-management-api')) . '-' . bin2hex(random_bytes(4));

    if ($host === '') {
        throw new RuntimeException('MQTT_HOST is not configured');
    }

    $mqtt = new MqttClient($host, $port, $clientId);
    $settings = (new ConnectionSettings())
        ->setUseTls($useTls)
        ->setKeepAliveInterval(30);

    if ($username !== null && $username !== '') {
        $settings->setUsername($username);
        $settings->setPassword($password ?? '');
    }

    $mqtt->connect($settings, true);
    try {
        $mqtt->publish($topic, $payload, 1, true);
    } finally {
        $mqtt->disconnect();
    }
}
