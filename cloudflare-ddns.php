<?php
// Basic settings
define("CLOUDFLARE_EMAIL", "YOUR EMAIL HERE"); // Your CF Email
define("CLOUDFLARE_KEY", "YOUR KEY HERE"); // Your CF Key from My Profile > API Keys > Global API Key
define("CLOUDFLARE_ZONE_INDEX", 0); // Zone index starting from 0
define('CLOUDFLARE_ZONE_NAME', 'DOMAIN HERE'); // Zone name(for security reason)
define('CLOUDFLARE_RECORD_NAME', 'RECORD NAME HERE'); // Record name
// Advanced settings
define('CLOUDFLARE_RECORD_TYPE', 'A'); // It can be "A"(IPv4) or "AAAA"(IPv6)
define('CLOUDFLARE_RECORD_PROXIED', false); // Enable/Disable cloudflare protection
define('CLOUDFLARE_RECORD_TTL', 1); // Record TTL 1 = Automatic
define('IPCHECK_WAIT_SECONDS', 60); // IP check timeout 0 = Automatic(ALPHA)
define('IPUPD_FAILED_RETRY_AFTER_SECONDS', 10); // Record update failed timeout retry
define('IPCHECK_URL', 'https://ifconfig.me'); // Public IP check URL

/* DO NOT CHANGE ANYTHING FROM NOW ON */

if(!(CLOUDFLARE_RECORD_TYPE == "A" or CLOUDFLARE_RECORD_TYPE == "AAAA")) {
    die("Please set the record type on A or AAAA.".PHP_EOL);
} elseif(CLOUDFLARE_RECORD_TYPE == "A") {
    $valIp = FILTER_FLAG_IPV4;
} elseif(CLOUDFLARE_RECORD_TYPE == "AAAA") {
    $valIp = FILTER_FLAG_IPV6;
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "X-Auth-Email: ".CLOUDFLARE_EMAIL,
        "X-Auth-Key: ".CLOUDFLARE_KEY,
        "Content-Type: application/json"
    ],
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones"
]);
$r = json_decode(curl_exec($curl), true);
$timeout = IPCHECK_WAIT_SECONDS;
$timeoutAI = false;

if($r["success"] && isset($r["result"][CLOUDFLARE_ZONE_INDEX]) &&
    $r["result"][CLOUDFLARE_ZONE_INDEX] &&
    $r["result"][CLOUDFLARE_ZONE_INDEX]["name"] == CLOUDFLARE_ZONE_NAME) {
    $zone = $r["result"][CLOUDFLARE_ZONE_INDEX];
    $zone_id = $zone["id"];
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/".$zone_id."/dns_records?".http_build_query([
            "type" => CLOUDFLARE_RECORD_TYPE,
            "name" => CLOUDFLARE_RECORD_NAME
        ])
    ]);
    $r = json_decode(curl_exec($curl), true);
    if($r["success"] && $r["result"][0]) {
        $record = $r["result"][0];
        $record_id = $record["id"];
        $oldIp = $record["content"];
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/".$zone_id."/dns_records/".$record_id,
            CURLOPT_CUSTOMREQUEST => "PUT",
        ]);
        while(true) {
            $ip = file_get_contents(IPCHECK_URL);
            if(filter_var($ip, FILTER_VALIDATE_IP, $valIp)) {
                $time_seconds = $timeout;
                $hours = floor($time_seconds / 3600);
                $time_seconds -= (3600 * $hours);
                $minutes = floor($time_seconds / 60);
                $time_seconds -= (60 * $minutes);
                $timeoutString = ($hours ? $hours." hours" : "").($minutes ? ($hours ? ", " : "").$minutes." minutes" : "").($time_seconds ? ($minutes ? ", " : "").$time_seconds." seconds" : "");
                if($oldIp != $ip) {
                    if($timeoutAI) {
                        if($timeout !== (++$timeoutCount * $timeout)) {
                            $timeout = (++$timeoutCount * $timeout);
                            $time_seconds = $timeout;
                            $hours = floor($time_seconds / 3600);
                            $time_seconds -= (3600 * $hours);
                            $minutes = floor($time_seconds / 60);
                            $time_seconds -= (60 * $minutes);
                            $timeoutString = ($hours ? $hours." hours" : "").($minutes ? ($hours ? ", " : "").$minutes." minutes" : "").($time_seconds ? ($minutes ? ", " : "").$time_seconds." seconds" : "");
                            echo "New timeout value: ".$timeoutString." seconds".PHP_EOL;
                        }
                    }
                    $timeoutCount = 0;
                    echo "IP changed! Updating record...".PHP_EOL;
                    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
                        "type" => CLOUDFLARE_RECORD_TYPE,
                        "name" => CLOUDFLARE_RECORD_NAME,
                        "content" => $ip,
                        "ttl" => CLOUDFLARE_RECORD_TTL,
                        "proxied" => CLOUDFLARE_RECORD_PROXIED,
                    ]));
                    $r = json_decode(curl_exec($curl), true);
                    if(!$r["success"]) {
                        echo "Record update failed! Trying again in ".IPUPD_FAILED_RETRY_AFTER_SECONDS." seconds...".PHP_EOL;
                        sleep(IPUPD_FAILED_RETRY_AFTER_SECONDS);
                    } else {
                        echo "Record successfully updated!".PHP_EOL."Next IP check in ".$timeoutString."...".PHP_EOL;
                        sleep($timeout);
                    }
                } else {
                    echo "IP didn't change. Next check in ".$timeoutString."...".PHP_EOL;
                    if($timeoutAI) $timeoutCount++;
                    sleep($timeout);
                }
            } else {
                die("IP returned from URL ".IPCHECK_URL." is not valid.".PHP_EOL);
            }
        }
    } else {
        foreach($r["errors"] as $error) {
            echo $error["code"].": ".$error["message"].PHP_EOL;
        }
        die("Failed while trying to get the record ID.".PHP_EOL);
    }
} else {
    die("Zone not found or zone index doesn't correspond to the zone name.".PHP_EOL);
}
