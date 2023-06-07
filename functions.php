<?php

function has_arg($val): bool {
    global $argv;
    return in_array($val, $argv);
}

function get_arg($val) {
    global $argv;
    $i = array_search($val, $argv);
    if ($i === false) {
        return null;
    }
    return $argv[$i + 1];
}

function get_ship() {
    $shipSymbol = get_arg("--ship");
    if (!$shipSymbol) {
        echo("Requires an argument for --ship\n");
        exit();
    }
    return new Ship($shipSymbol);
}

function get_field($array, string $field): array {
    $result = [];
    foreach($array as $item) {
        $result[] = $item[$field];
    }
    return $result;
}

function get_symbols($data) {
    $s = [];
    foreach($data as $item) {
        $s[] = $item['symbol'];
    }
    return json_encode($s);
}

function get_api($url) {
    $ch = curl_init($url);

    $token = getenv("SPACETRADER_API_TOKEN");
    $headers = [
        'Authorization: Bearer ' . $token,
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $server_output = curl_exec($ch);
    curl_close($ch);

    $json_data = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);
    // If debug is requested or an error occurs log the response.
    if (has_arg("--debug") || isset($json_data['error'])) {
        echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
    }

    return $json_data;
}

function post_api($url, $data = null) {
    $ch = curl_init($url);

    $token = getenv("SPACETRADER_API_TOKEN");
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($data === null) {
        // POST without data.
        curl_setopt($ch, CURLOPT_POST, true);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $server_output = curl_exec($ch);
    curl_close($ch);

    $json_data = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);
    // If debug is requested or an error occurs log the response.
    if (isset($json_data['error'])) {
        $message = $json_data['error']['message'];
        $code = $json_data['error']['code'];
        if (isset($json_data['error']['data']['cooldown'])) {
            $cooldown = $json_data['error']['data']['cooldown'];
            throw new CooldownException($message, $cooldown);
        }
        if ($code == 4214) {
            $time = $json_data['error']['data']['secondsToArrival'];
            echo("Will arrive in $time seconds");
            throw new RuntimeException($message);
        }
        if ($json_data['error']['code'] ?? 0 === 400) {
            echo("\nRequest:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n");
        }
        echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
        throw new RuntimeException($json_data['error']['message']);
    }
    if (has_arg("--debug")) {
        echo("\n" . json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
    }

    return $json_data;
}