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

function get_ship(): Ship {
    $shipSymbol = get_arg("--ship");
    if (!$shipSymbol) {
        echo("Requires an argument for --ship\n");
        exit();
    }
    return Ship::load($shipSymbol);
}

function get_field(array $array, string $field): array {
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

function tabs_for(string $str, int $max): string {
    return str_repeat("\t", $max - intval(strlen($str) / 8));
}

function display_json($json_data) {
    echo(json_encode($json_data, JSON_PRETTY_PRINT) . "\n");
}

function get_api($url) {
    $ch = curl_init($url);
    if (has_arg("--debug_api")) {
        echo("get_api $url\n");
    }

    $token = getenv("SPACETRADER_API_TOKEN");
    $headers = [
        'Authorization: Bearer ' . $token,
    ];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $server_output = curl_exec($ch);
    curl_close($ch);

    $json_data = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);

    if (isset($json_data['error'])) {
        $message = $json_data['error']['message'];
        $code = $json_data['error']['code'] ?? 0;
        if ($code == 429) {
            // Rate limited.
            // The burst rate of 10 has a 10 second reset.
            // Lets sleep for a bit to avoid hitting the rate limit again immediately.
            $retryAfter = $json_data['error']['data']['retryAfter'];
            // TODO retryAfter is often something like 0.17 which is too short if we want to make multiple requests.
            $wait = 5;
            echo("Hit rate limit, sleeping for $wait seconds before retrying\n");
            sleep($wait);
            return get_api($url);
        }
        echo("\nUrl:\n" . $url);
        display_json($json_data);
        throw new RuntimeException("error $code: $message");
    }
    // If debug is requested or an error occurs log the response.
    if (has_arg("--debug") || isset($json_data['error'])) {
        display_json($json_data);
    }

    return $json_data;
}

/**
{
"error": {
"message": "SpaceTraders is currently in maintenance mode and unavailable. This will hopefully only last a few minutes while we update or upgrade our servers. Check discord for updates https:\/\/discord.com\/invite\/jh6zurdWk5 and consider donating to keep the servers running https:\/\/donate.stripe.com\/28o29m5vxcri6OccMM",
"code": 503
}
}
 */

function post_api($url, $data = null) {
    $ch = curl_init($url);
    if (has_arg("--debug_api")) {
        echo("post_api $url\n");
    }

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

    try {
        $json_data = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        // A rate limit error doesn't always return json?
        // Return a 5 second cooldown so it can be retried later.
        throw new CooldownException($e->getMessage() . " from response: $server_output", 5);
    }
    // If debug is requested or an error occurs log the response.
    if (isset($json_data['error'])) {
        $message = $json_data['error']['message'];
        $code = $json_data['error']['code'] ?? 0;
        if (isset($json_data['error']['data']['cooldown'])) {
            $cooldown = $json_data['error']['data']['cooldown'];
            throw new CooldownException($message, $cooldown['remainingSeconds']);
        }
        if ($code == 4214) {
            $time = $json_data['error']['data']['secondsToArrival'];
            echo("Will arrive in $time seconds\n");
            throw new CooldownException($message, $time);
        }
        if ($code == 429) {
            // Rate limited.
            // The burst rate of 10 has a 10 second reset.
            // Lets sleep for a bit to avoid hitting the rate limit again immediately.
            $retryAfter = $json_data['error']['data']['retryAfter'];
            // TODO retryAfter is often something like 0.17 which is too short if we want to make multiple requests.
            $wait = 5;
            echo("Hit rate limit, sleeping for $wait seconds before retrying\n");
            sleep($wait);
            return post_api($url, $data);
        }
        echo("Url:\n" . $url . "\n");
        echo("Request:\n");
        display_json($data);
        echo("Response:\n");
        display_json($json_data);
        throw new RuntimeException("error $code: $message");
    }
    if (has_arg("--debug")) {
        display_json($json_data);
    }

    return $json_data;
}