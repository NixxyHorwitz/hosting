<?php
/**
 * library/traffic.php
 * Site Traffic Tracker — catat setiap request ke site_traffic
 * 
 * Usage: require_once 'library/traffic.php'; track_traffic($conn);
 */

function get_real_ip(): string {
    $headers = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = trim(explode(',', $_SERVER[$h])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function parse_ua(string $ua): array {
    $ua = strtolower($ua);
    // Bot detection first
    if (preg_match('/bot|crawl|slurp|spider|facebookexternal|preview|curl|wget|python|scrapy|heritrix|archive/i', $ua)) {
        return ['device_type'=>'bot', 'os'=>'Bot', 'browser'=>'Bot'];
    }
    // Device type
    $device = 'desktop';
    if (preg_match('/tablet|ipad|kindle|silk/i', $ua)) $device = 'tablet';
    elseif (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/i', $ua)) $device = 'mobile';
    // OS
    $os = 'Unknown';
    if (str_contains($ua,'windows nt')) $os = 'Windows';
    elseif (str_contains($ua,'android'))    $os = 'Android';
    elseif (str_contains($ua,'iphone'))     $os = 'iOS';
    elseif (str_contains($ua,'ipad'))       $os = 'iPadOS';
    elseif (str_contains($ua,'mac os x'))   $os = 'macOS';
    elseif (str_contains($ua,'linux'))      $os = 'Linux';
    // Browser
    $br = 'Unknown';
    if (str_contains($ua,'edg'))            $br = 'Edge';
    elseif (str_contains($ua,'opr') || str_contains($ua,'opera')) $br = 'Opera';
    elseif (str_contains($ua,'chrome'))     $br = 'Chrome';
    elseif (str_contains($ua,'firefox'))    $br = 'Firefox';
    elseif (str_contains($ua,'safari'))     $br = 'Safari';
    elseif (str_contains($ua,'msie') || str_contains($ua,'trident')) $br = 'IE';
    return ['device_type'=>$device,'os'=>$os,'browser'=>$br];
}

function get_ip_geo(string $ip): array {
    // Use ip-api.com free tier (no key needed, 45 req/min)
    // Cache result in session to avoid repeated calls same session
    if (isset($_SESSION['_geo_ip']) && $_SESSION['_geo_ip'] === $ip && isset($_SESSION['_geo_data'])) {
        return $_SESSION['_geo_data'];
    }
    $ctx = stream_context_create(['http'=>['timeout'=>2,'ignore_errors'=>true]]);
    $raw = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,country,city,isp,query", false, $ctx);
    if ($raw) {
        $data = json_decode($raw, true);
        if (($data['status'] ?? '') === 'success') {
            $geo = ['country'=>$data['country']??'','city'=>$data['city']??'','isp'=>$data['isp']??''];
            $_SESSION['_geo_ip']   = $ip;
            $_SESSION['_geo_data'] = $geo;
            return $geo;
        }
    }
    return ['country'=>'','city'=>'','isp'=>''];
}

function track_traffic(mysqli $conn, ?int $user_id = null): void {
    // Skip tracking for admin requests (optional: remove this if you want admin tracked too)
    $page = $_SERVER['REQUEST_URI'] ?? '';
    if (str_starts_with($page, '/admin') && !defined('TRACK_ADMIN')) return;

    // Throttle: only track once per session+page (check session flag)
    $sess_key = '_tracked_' . md5($page);
    if (isset($_SESSION[$sess_key])) return;
    $_SESSION[$sess_key] = 1;

    $ip       = get_real_ip();
    $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer  = $_SERVER['HTTP_REFERER'] ?? '';
    $sid      = session_id();
    $is_logged= ($user_id !== null || isset($_SESSION['user_id'])) ? 1 : 0;
    $uid      = $user_id ?? ($_SESSION['user_id'] ?? null);

    $ua_data  = parse_ua($ua);
    $geo      = get_ip_geo($ip);

    $ip_e    = mysqli_real_escape_string($conn, $ip);
    $ua_e    = mysqli_real_escape_string($conn, substr($ua, 0, 500));
    $ref_e   = mysqli_real_escape_string($conn, substr($referer, 0, 500));
    $page_e  = mysqli_real_escape_string($conn, substr($page, 0, 500));
    $sid_e   = mysqli_real_escape_string($conn, $sid);
    $co_e    = mysqli_real_escape_string($conn, $geo['country']);
    $ci_e    = mysqli_real_escape_string($conn, $geo['city']);
    $isp_e   = mysqli_real_escape_string($conn, $geo['isp']);
    $dev_e   = mysqli_real_escape_string($conn, $ua_data['device_type']);
    $os_e    = mysqli_real_escape_string($conn, $ua_data['os']);
    $br_e    = mysqli_real_escape_string($conn, $ua_data['browser']);
    $uid_sql = $uid !== null ? (int)$uid : 'NULL';

    mysqli_query($conn, "INSERT INTO site_traffic 
        (session_id, user_id, ip, country, city, isp, device_type, os, browser, user_agent, referer, page_url, is_logged)
        VALUES ('$sid_e', $uid_sql, '$ip_e', '$co_e', '$ci_e', '$isp_e', '$dev_e', '$os_e', '$br_e', '$ua_e', '$ref_e', '$page_e', $is_logged)");
}
