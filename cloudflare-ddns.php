<?php
define('CLOUDFLARE_API_KEY', 'Your cloudflare api key');
define('CLOUDFLARE_EMAIL', 'Your cloudflare email');
define('DOMAIN', 'example.com');
define('CLOUDFLARE_RECORD', 'AArecord.example.com');
define('CLOUDFLARE_RECORD_PROXIED', true);
define('CHECK_SECONDS', 120);








echo 'Cloudflare API Key: '.CLOUDFLARE_API_KEY.PHP_EOL.'Cloudflare email: '.CLOUDFLARE_EMAIL.PHP_EOL.'Domain: '.DOMAIN.PHP_EOL.'Record: '.CLOUDFLARE_RECORD.PHP_EOL.'Check every '.CHECK_SECONDS.' seconds.'.PHP_EOL.'Status: ';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.cloudflare.com/client/v4/zones?name='.urlencode(DOMAIN));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
$headers = array();
$headers[] = 'X-Auth-Email: '.CLOUDFLARE_EMAIL;
$headers[] = 'X-Auth-Key: '.CLOUDFLARE_API_KEY;
$headers[] = 'Content-Type: application/json';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$result = json_decode(curl_exec($ch), true);
curl_close($ch);
if (isset($result['result'][0]['id'])) {
  define('CLOUDFLARE_ID', $result['result'][0]['id']);
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://api.cloudflare.com/client/v4/zones/'.CLOUDFLARE_ID.'/dns_records?type=A&name='.urlencode(CLOUDFLARE_RECORD));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
  $headers = array();
  $headers[] = 'X-Auth-Email: '.CLOUDFLARE_EMAIL;
  $headers[] = 'X-Auth-Key: '.CLOUDFLARE_API_KEY;
  $headers[] = 'Content-Type: application/json';
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  $result = json_decode(curl_exec($ch), true);
  curl_close($ch);
  if (isset($result['result'][0]['id'])) {
    define('CLOUDFLARE_RECORD_ID', $result['result'][0]['id']);
    $oldIp = $result['result'][0]['content'];
    echo 'Running'.PHP_EOL;
    while (true) {
      $ip = file_get_contents('https://ipv4.myip.info/');
      if (filter_var($ip, FILTER_VALIDATE_IP) and $ip !== $oldIp) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.cloudflare.com/client/v4/zones/'.CLOUDFLARE_ID.'/dns_records/'.CLOUDFLARE_RECORD_ID);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('type' => 'A', 'name' => CLOUDFLARE_RECORD, 'content' => $ip, 'proxied' => CLOUDFLARE_RECORD_PROXIED)));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        $headers = array();
        $headers[] = 'X-Auth-Email: '.CLOUDFLARE_EMAIL;
        $headers[] = 'X-Auth-Key: '.CLOUDFLARE_API_KEY;
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = json_decode(curl_exec($ch), true);
        curl_close($ch);
        if ($result['success']) {
          echo "\nRecord updated.\nOld IP: $oldIp\nNew IP: $ip\n";
          $oldIp = $ip;
        }
      }
      sleep(CHECK_SECONDS);
    }
  } else {
    die('Record not found.'.PHP_EOL);
  }
} else {
  die('Error.'.PHP_EOL);
}
