<?php
/**
 * ZedGuard — dosya bütünlüğü + malware izleyici
 * Paylaşımlı hosting (cPanel/Hostinger tarzı) hesapları için, cron ile
 * periyodik çalışan, harici bağımlılık gerektirmeyen tek dosyalık script.
 *
 * 3 katman:
 *  1) Kesin imza eşleşmesi (dosya adı/içerik)  -> OTOMATİK SİL + bildir
 *  2) Genel şüpheli davranış kalıpları (sadece YENİ/DEĞİŞEN dosyalarda) -> BİLDİR, silme
 *  3) Dosya ekleme/silme/boyut değişikliği -> BİLDİR
 *
 * Kurulum: README.md'ye bakın.
 *
 * ZEDGUARD_SELF_FINGERPRINT_v1_do_not_remove
 * (Bu satırı silmeyin — kendi kodunu tanımak ve kendini yanlışlıkla
 * "zararlı" sayıp silmemek için kullanılıyor. Bkz. matchesKnownMalware().)
 */

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    fwrite(STDERR, "config.php bulunamadı. config.example.php dosyasını config.php olarak kopyalayıp doldurun.\n");
    exit(1);
}
$config = require $configFile;

$TELEGRAM_TOKEN = $config['telegram_token'];
$TELEGRAM_CHAT_ID = $config['telegram_chat_id'];
$BASE = rtrim($config['sites_base_dir'], '/');
// ZedGuard'in kendi kurulu oldugu klasoru (isim ne olursa olsun) otomatik
// haric tut - baseline.json her calismada yeniden yazildigi icin, haric
// tutulmazsa ZedGuard kendi kendini "her seferinde degisti" diye
// raporlayip gereksiz bildirim uretir.
$SELF_DIR_NAME = basename(__DIR__);
$EXCLUDE_DIRS = array_unique(array_merge($config['exclude_dirs'], [$SELF_DIR_NAME]));
$NOTIFY_ON_CLEAN = $config['notify_on_clean'] ?? true;
$CLEAN_REPORT_HOURS = $config['clean_report_hours'] ?? [0, 6, 12, 18];
$USOM_CHECK_ENABLED = $config['usom_check_enabled'] ?? true;
$URLHAUS_AUTH_KEY = $config['urlhaus_auth_key'] ?? '';
$SPAMHAUS_USERNAME = $config['spamhaus_username'] ?? '';
$SPAMHAUS_PASSWORD = $config['spamhaus_password'] ?? '';
$SPAMHAUS_TOKEN = ($SPAMHAUS_USERNAME && $SPAMHAUS_PASSWORD) ? spamhausLogin($SPAMHAUS_USERNAME, $SPAMHAUS_PASSWORD) : null;

$BASELINE_FILE = __DIR__ . '/baseline.json';

// --- Katman 1: kesin imza -> otomatik sil ---
// Not: Bu liste, bu projenin GitHub sayfasındaki gerçek bir olaydan
// (bkz. README "Neden bu araç ortaya çıktı") gelen imzaları içerir.
// Kendi ortamınızda karşılaştığınız yeni imzaları buraya ekleyebilirsiniz.
$KNOWN_MALWARE_NAMES = ['awp-niin.php', 'dragonshell.php', 'anc.php', 'kicau.php', 'alccc.php', 'iwo.txt', 'ss.php'];
$KNOWN_MALWARE_CONTENT = ['myzedd.tech', 'secured by zedd', 'kickbacks-backend', 'trygravity.ai'];

// --- Katman 2: genel şüpheli kalıplar (sadece bildirim) ---
$SUSPICIOUS_PATTERNS = [
    '/eval\s*\(\s*(base64_decode|gzinflate|str_rot13|gzuncompress)\s*\(/i' => 'obfuscated eval',
    '/\b(system|shell_exec|passthru|exec)\s*\(\s*\$_(GET|POST|REQUEST)/i' => 'doğrudan komut çalıştırma',
    '/\bassert\s*\(\s*\$_(GET|POST|REQUEST)/i' => 'assert ile kod çalıştırma',
    '/fsockopen\s*\(/i' => 'ham soket bağlantısı',
    '/[\x{3040}-\x{30ff}\x{4e00}-\x{9fff}]{2,}\s*=/u' => 'unicode değişken ismi (obfuscation belirtisi)',
];

// WordPress çekirdeğindeki bilinen meşru fsockopen kullanıcıları — bunları
// "genel kalıp" alarmından muaf tutuyoruz (WP'siz kurulumlarda bu liste boş bırakılabilir).
$LEGIT_FSOCKOPEN_FILES = [
    'wp-admin/includes/file.php', 'wp-admin/includes/class-ftp-pure.php',
    'wp-includes/class-snoopy.php', 'wp-includes/class-pop3.php',
    'wp-includes/phpmailer/smtp.php', 'wp-includes/phpmailer/pop3.php',
    'wp-includes/simplepie/library/simplepie.php', 'wp-includes/simplepie/src/file.php',
    'wp-includes/simplepie/src/simplepie.php', 'wp-includes/ixr/class-ixr-client.php',
];

function discoverSites(string $base): array {
    $sites = [];
    foreach (scandir($base) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir("$base/$entry") && is_dir("$base/$entry/public_html")) {
            $sites[] = $entry;
        }
    }
    sort($sites);
    return $sites;
}

function sendTelegram(string $token, string $chatId, string $text): void {
    if (empty($token) || empty($chatId)) return;
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'];
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 10,
        ],
    ]);
    @file_get_contents($url, false, $ctx);
}

function scanRoot(string $root, array $excludeDirs): array {
    $result = [];
    if (!is_dir($root)) return $result;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relPath = substr($item->getPathname(), strlen($root) + 1);
        $parts = explode('/', $relPath);
        $skip = false;
        foreach ($parts as $p) {
            if (in_array(strtolower($p), $excludeDirs, true)) { $skip = true; break; }
        }
        if ($skip) continue;
        if ($item->isFile()) {
            $result[$relPath] = [
                'size' => $item->getSize(),
                'mtime' => $item->getMTime(),
            ];
        }
    }
    return $result;
}

function isCodeFile(string $path): bool {
    return (bool)preg_match('/\.(php|phtml|php[3-8]?|js|cgi|pl|sh)$/i', $path);
}

// ZedGuard'in kendi kaynak kodunu tanimlayan essiz parmak izi. Sadece bu
// script'in kendi kopyalarinda bulunur - herhangi bir gercek malware'de
// tesadufen gecmez. Bu sayede "kendi imza listesini icerigimde tasiyorum
// diye kendimi zararli sanma" sorunu (dosya adindan bagimsiz) cozulur.
const ZEDGUARD_FINGERPRINT = 'ZEDGUARD_SELF_FINGERPRINT_v1_do_not_remove';

function matchesKnownMalware(string $fullPath, string $relPath, array $names, array $contentNeedles): bool {
    $basename = strtolower(basename($relPath));
    if (in_array($basename, $names, true)) return true;
    if (!isCodeFile($relPath)) return false;
    if (!is_readable($fullPath) || filesize($fullPath) > 5 * 1024 * 1024) return false;
    $content = @file_get_contents($fullPath);
    if ($content === false) return false;
    // ONEMLI: Kendi kodumuzu (veya bir kopyasini/yedegini) asla zararli
    // sayma - kaynak kodumuz tespit ettigi imzalari string olarak
    // barindirdigi icin (myzedd.tech vb.) bu kontrol olmadan KENDINI
    // otomatik silebilir (yasandi, bkz. CHANGELOG v0.5).
    if (str_contains($content, ZEDGUARD_FINGERPRINT)) return false;
    $lower = strtolower($content);
    foreach ($contentNeedles as $needle) {
        if (str_contains($lower, strtolower($needle))) return true;
    }
    return false;
}

function checkSuspiciousPatterns(string $fullPath, string $relPath, array $patterns, array $legitFsockopenFiles): array {
    if (!isCodeFile($relPath)) return [];
    if (!is_readable($fullPath) || filesize($fullPath) > 5 * 1024 * 1024) return [];
    $content = @file_get_contents($fullPath);
    if ($content === false) return [];
    if (str_contains($content, ZEDGUARD_FINGERPRINT)) return [];
    $hits = [];
    $relLower = strtolower($relPath);
    foreach ($patterns as $pattern => $label) {
        if ($label === 'ham soket bağlantısı' && in_array($relLower, $legitFsockopenFiles, true)) continue;
        if (@preg_match($pattern, $content)) {
            $hits[] = $label;
        }
    }
    return $hits;
}

// Bir dosya icindeki domain benzeri stringleri cikarir (kaba ama pratik).
function extractDomains(string $content): array {
    if (!preg_match_all('/\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}\b/i', $content, $m)) {
        return [];
    }
    $domains = array_unique(array_map('strtolower', $m[0]));
    // php.net, wordpress.org gibi cok yaygin/zararsiz domainleri elemek icin
    // basit bir filtre - gerci USOM sorgusu zaten bunlari "temiz" donduruyor,
    // ama gereksiz API cagrisi yapmamak icin kaba bir on-eleme.
    $commonSafe = ['w3.org', 'php.net', 'wordpress.org', 'github.com', 'schema.org', 'googleapis.com'];
    return array_values(array_diff($domains, $commonSafe));
}

// T.C. Siber Guvenlik Baskanligi (USOM) tehdit istihbarati API'si.
// Kimlik dogrulama gerektirmez. API degisirse/yanit vermezse sessizce atlanir
// (bu ozellik hicbir zaman aracin geri kalanini bozmamali).
function checkUsomThreatIntel(string $domain): ?array {
    $url = 'https://siberguvenlik.gov.tr/api/address/index?q=' . urlencode($domain);
    $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    if (!is_array($data) || !isset($data['totalCount'])) return null;
    if ($data['totalCount'] > 0 && !empty($data['models'][0])) {
        return $data['models'][0];
    }
    return null;
}

// URLhaus (abuse.ch) malware-dagitim host kontrolu. Ucretsiz ama bir
// Auth-Key gerektirir: https://auth.abuse.ch/ adresinden alinir.
// $authKey bos ise fonksiyon hicbir sey yapmadan null doner.
function checkUrlhaus(string $domain, string $authKey): ?array {
    if ($authKey === '') return null;
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Auth-Key: $authKey\r\nContent-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['host' => $domain]),
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents('https://urlhaus-api.abuse.ch/v1/host/', false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    if (!is_array($data) || ($data['query_status'] ?? '') !== 'ok') return null;
    return $data; // url_count, urls[] iceriyor
}

// Spamhaus Intelligence API. Ucretsiz DQS hesabi ile kullanici adi/sifre
// gerektirir: https://portal.spamhaus.com/auth/account-setup?ps=free_dqs_product
// Bearer token 24 saatte bir dolar, bu yuzden her calismada yeniden login
// olunur (basit ve guvenilir - ayrica token cache etmeye gerek yok).
function spamhausLogin(string $username, string $password): ?string {
    if ($username === '' || $password === '') return null;
    $ctx = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/json\r\n",
            'content' => json_encode(['username' => $username, 'password' => $password, 'realm' => 'intel']),
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents('https://api.spamhaus.org/api/v1/login', false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    return $data['token'] ?? null;
}

function checkSpamhaus(string $domain, ?string $token): ?array {
    if (!$token) return null;
    $ctx = stream_context_create([
        'http' => [
            'header'  => "Authorization: Bearer $token\r\n",
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    $resp = @file_get_contents("https://api.spamhaus.org/api/intel/v2/byobject/domain/" . urlencode($domain) . "/listing", false, $ctx);
    if ($resp === false) return null;
    $data = json_decode($resp, true);
    if (is_array($data) && ($data['is-listed'] ?? false)) {
        return $data;
    }
    return null;
}

// --- Ana akış ---
$baseline = [];
if (file_exists($BASELINE_FILE)) {
    $baseline = json_decode(file_get_contents($BASELINE_FILE), true) ?: [];
}

$isFirstRun = empty($baseline);
$SITES = discoverSites($BASE);
$newBaseline = [];
$report = [];
$anyChange = false;
$newSites = [];
$removedSites = [];
$autoDeleted = [];
$suspiciousFlags = [];

foreach ($SITES as $site) {
    $root = "$BASE/$site/public_html";
    $current = scanRoot($root, $EXCLUDE_DIRS);

    if ($isFirstRun) {
        $newBaseline[$site] = $current;
        continue;
    }

    if (!isset($baseline[$site])) {
        $newSites[] = $site;
        $newBaseline[$site] = $current;
        continue;
    }

    $old = $baseline[$site];
    $added = array_diff(array_keys($current), array_keys($old));
    $removed = array_diff(array_keys($old), array_keys($current));
    $modified = [];
    foreach ($current as $path => $info) {
        if (isset($old[$path]) && $old[$path]['size'] !== $info['size']) {
            $modified[] = $path;
        }
    }

    $toCheck = array_merge($added, $modified);
    foreach ($toCheck as $relPath) {
        if (!isset($current[$relPath])) continue;
        $fullPath = "$root/$relPath";
        if (matchesKnownMalware($fullPath, $relPath, $KNOWN_MALWARE_NAMES, $KNOWN_MALWARE_CONTENT)) {
            @unlink($fullPath);
            $autoDeleted[] = "$site: $relPath";
            unset($current[$relPath]);
            continue;
        }
        $hits = checkSuspiciousPatterns($fullPath, $relPath, $SUSPICIOUS_PATTERNS, $LEGIT_FSOCKOPEN_FILES);
        if ($hits) {
            $flagText = "$site: $relPath — " . implode(', ', $hits);
            if ($USOM_CHECK_ENABLED || $URLHAUS_AUTH_KEY !== '' || $SPAMHAUS_TOKEN) {
                $content = @file_get_contents($fullPath);
                if ($content !== false) {
                    foreach (array_slice(extractDomains($content), 0, 5) as $domain) {
                        if ($USOM_CHECK_ENABLED) {
                            $verdict = checkUsomThreatIntel($domain);
                            if ($verdict) {
                                $flagText .= "\n    🚨 USOM'da kayıtlı: $domain (tip: {$verdict['desc']}, kritiklik: {$verdict['criticality_level']}/10)";
                            }
                        }
                        if ($URLHAUS_AUTH_KEY !== '') {
                            $uh = checkUrlhaus($domain, $URLHAUS_AUTH_KEY);
                            if ($uh && ($uh['url_count'] ?? 0) > 0) {
                                $flagText .= "\n    🚨 URLhaus'ta kayıtlı: $domain ({$uh['url_count']} zararlı URL)";
                            }
                        }
                        if ($SPAMHAUS_TOKEN) {
                            $sh = checkSpamhaus($domain, $SPAMHAUS_TOKEN);
                            if ($sh) {
                                $flagText .= "\n    🚨 Spamhaus'ta kayıtlı: $domain";
                            }
                        }
                    }
                }
            }
            $suspiciousFlags[] = $flagText;
        }
    }

    $newBaseline[$site] = $current;

    $deletedForSite = array_map(
        fn($s) => substr($s, strlen("$site: ")),
        array_filter($autoDeleted, fn($s) => str_starts_with($s, "$site: "))
    );
    $addedFiltered = array_diff($added, $deletedForSite);
    if ($addedFiltered || $removed || $modified) {
        $anyChange = true;
        $lines = ["⚠️ <b>$site</b> — DEĞİŞİKLİK TESPİT EDİLDİ"];
        foreach ($addedFiltered as $p) $lines[] = "  + YENİ: $p";
        foreach ($removed as $p) $lines[] = "  - SİLİNDİ: $p";
        foreach ($modified as $p) $lines[] = "  ~ DEĞİŞTİ: $p";
        $report[] = implode("\n", $lines);
    }
}

foreach (array_keys($baseline) as $oldSite) {
    if (!in_array($oldSite, $SITES, true)) {
        $removedSites[] = $oldSite;
    }
}

file_put_contents($BASELINE_FILE, json_encode($newBaseline));

if ($isFirstRun) {
    sendTelegram($TELEGRAM_TOKEN, $TELEGRAM_CHAT_ID, "🛡️ ZedGuard kuruldu. Baseline alındı (" . count($SITES) . " site). Periyodik taramalar başlıyor.");
    exit(0);
}

if ($newSites) {
    sendTelegram($TELEGRAM_TOKEN, $TELEGRAM_CHAT_ID, "ℹ️ Yeni site tespit edildi ve izlemeye alındı: " . implode(', ', $newSites));
}
if ($removedSites) {
    sendTelegram($TELEGRAM_TOKEN, $TELEGRAM_CHAT_ID, "ℹ️ Artık bulunmayan site(ler) izlemeden düşürüldü: " . implode(', ', $removedSites));
}
if ($autoDeleted) {
    sendTelegram($TELEGRAM_TOKEN, $TELEGRAM_CHAT_ID, "🗑️ <b>OTOMATİK TEMİZLENDİ (bilinen imza)</b>\n\n" . implode("\n", $autoDeleted));
}
if ($suspiciousFlags) {
    $msg = "🔎 <b>ŞÜPHELİ (elle kontrol edin, silinmedi)</b>\n\n" . implode("\n", $suspiciousFlags);
    if (strlen($msg) > 3800) $msg = substr($msg, 0, 3800) . "\n...(devamı kesildi)";
    sendTelegram($TELEGRAM_TOKEN, $TELEGRAM_CHAT_ID, $msg);
}
if ($anyChange) {
    $msg = "⚠️ <b>DOSYA DEĞİŞİKLİĞİ — " . date('Y-m-d H:i') . "</b>\n\n" . implode("\n\n", $report);
    if (strlen($msg) > 3800) $msg = substr($msg, 0, 3800) . "\n...(devamı kesildi)";
    sendTelegram($TELEGRAM_TOKEN, $TELEGRAM_CHAT_ID, $msg);
} elseif (!$autoDeleted && !$suspiciousFlags) {
    $hour = (int)date('H');
    $shouldNotify = $NOTIFY_ON_CLEAN || (in_array($hour, $CLEAN_REPORT_HOURS, true) && (int)date('i') < 15);
    if ($shouldNotify) {
        sendTelegram($TELEGRAM_TOKEN, $TELEGRAM_CHAT_ID, "✅ Tarama yapıldı — " . date('Y-m-d H:i') . " — tüm siteler temiz (" . count($SITES) . " site kontrol edildi)");
    }
}
