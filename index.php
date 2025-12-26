<?php
// SQLite Database Setup
function initDatabase() {
    $dbFile = __DIR__ . '/tags.db';
    $dbExists = file_exists($dbFile);
    
    try {
        $db = new PDO('sqlite:' . $dbFile);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if (!$dbExists) {
            // Create tags table
            $db->exec("CREATE TABLE IF NOT EXISTS tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                tag TEXT NOT NULL UNIQUE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Create rate limit table
            $db->exec("CREATE TABLE IF NOT EXISTS rate_limit (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                timestamp INTEGER NOT NULL
            )");
            
            // Create index for faster lookups
            $db->exec("CREATE INDEX IF NOT EXISTS idx_rate_limit_ip ON rate_limit(ip_address, timestamp)");
            
            // Insert 20 example tags
            $exampleTags = [
                '123456',
                'password123',
                '123456789',
                'password',
                'iloveyou',
                'princess',
                'abc123',
                'babygirl',
                'qwerty',
                'iloveu',
                'chocolate',
                'butterfly',
                'liverpool',
                'football',
                'superman',
                '987654321',
                'spongebob',
                'beautiful',
                'blink182',
                'babygurl'
            ];
            
            $stmt = $db->prepare("INSERT OR IGNORE INTO tags (tag) VALUES (:tag)");
            foreach ($exampleTags as $tag) {
                $stmt->execute([':tag' => $tag]);
            }
        }
        
        return $db;
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return null;
    }
}

// Get user IP address
function getUserIP() {
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipHeaders as $header) {
        if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Handle multiple IPs in X-Forwarded-For
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            return $ip;
        }
    }
    
    return '0.0.0.0';
}

// Check rate limit (max 20 tags per 60 seconds per IP)
function checkRateLimit($db, $ipAddress) {
    if (!$db) {
        return ['allowed' => false, 'message' => 'Database error'];
    }
    
    try {
        $currentTime = time();
        $timeWindow = 60; // 60 seconds
        $maxRequests = 20; // Maximum 20 tags
        
        // Clean up old entries (older than 60 seconds)
        $cleanupTime = $currentTime - $timeWindow;
        $db->exec("DELETE FROM rate_limit WHERE timestamp < $cleanupTime");
        
        // Count requests from this IP in the last 60 seconds
        $stmt = $db->prepare("SELECT COUNT(*) FROM rate_limit WHERE ip_address = :ip AND timestamp >= :time_limit");
        $stmt->execute([
            ':ip' => $ipAddress,
            ':time_limit' => $currentTime - $timeWindow
        ]);
        
        $requestCount = $stmt->fetchColumn();
        
        if ($requestCount >= $maxRequests) {
            return [
                'allowed' => false, 
                'message' => 'Rate limit exceeded. Maximum 20 tags per minute.',
                'count' => $requestCount,
                'remaining' => 0
            ];
        }
        
        return [
            'allowed' => true,
            'count' => $requestCount,
            'remaining' => $maxRequests - $requestCount
        ];
    } catch (PDOException $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return ['allowed' => false, 'message' => 'Rate limit check failed'];
    }
}

// Record rate limit entry
function recordRateLimit($db, $ipAddress) {
    if (!$db) {
        return;
    }
    
    try {
        $stmt = $db->prepare("INSERT INTO rate_limit (ip_address, timestamp) VALUES (:ip, :timestamp)");
        $stmt->execute([
            ':ip' => $ipAddress,
            ':timestamp' => time()
        ]);
    } catch (PDOException $e) {
        error_log("Rate limit record error: " . $e->getMessage());
    }
}

// Get random tags from database
function getRandomTags($db, $count = 10) {
    if (!$db) {
        return [];
    }
    
    try {
        $stmt = $db->query("SELECT tag FROM tags ORDER BY RANDOM() LIMIT " . intval($count));
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        return [];
    }
}

// Insert new tag into database with rate limiting
function insertTag($db, $text, $ipAddress) {
    if (!$db || empty($text)) {
        return ['success' => false, 'message' => 'Invalid input'];
    }
    
    // Check rate limit
    $rateLimitCheck = checkRateLimit($db, $ipAddress);
    if (!$rateLimitCheck['allowed']) {
        return [
            'success' => false, 
            'message' => $rateLimitCheck['message'],
            'rate_limited' => true
        ];
    }
    
    try {
        // Truncate to 30 characters if longer
        $tag = mb_substr(trim($text), 0, 30);
        
        if (empty($tag)) {
            return ['success' => false, 'message' => 'Empty tag'];
        }
        
        $stmt = $db->prepare("INSERT OR IGNORE INTO tags (tag) VALUES (:tag)");
        $stmt->execute([':tag' => $tag]);
        
        // Record this request in rate limit table
        recordRateLimit($db, $ipAddress);
        
        // Check if tag was actually inserted (not a duplicate)
        if ($stmt->rowCount() > 0) {
            return [
                'success' => true, 
                'message' => 'Tag saved to database', 
                'tag' => $tag,
                'remaining' => $rateLimitCheck['remaining'] - 1
            ];
        } else {
            return [
                'success' => true, 
                'message' => 'Tag already exists', 
                'tag' => $tag, 
                'duplicate' => true,
                'remaining' => $rateLimitCheck['remaining'] - 1
            ];
        }
    } catch (PDOException $e) {
        error_log("Insert error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error'];
    }
}

// Get total tag count
function getTagCount($db) {
    if (!$db) {
        return 0;
    }
    
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM tags");
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// Generate sitemap.xml
function generateSitemap($db) {
    if (!$db) {
        return '';
    }
    
    try {
        // Get base URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = $_SERVER['SCRIPT_NAME'];
        $baseUrl = $protocol . '://' . $host . $scriptName;
        
        // Get all tags
        $stmt = $db->query("SELECT tag, created_at FROM tags ORDER BY created_at DESC");
        $tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Start XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Add homepage
        $xml .= '  <url>' . "\n";
        $xml .= '    <loc>' . htmlspecialchars($baseUrl) . '</loc>' . "\n";
        $xml .= '    <changefreq>daily</changefreq>' . "\n";
        $xml .= '    <priority>1.0</priority>' . "\n";
        $xml .= '  </url>' . "\n";
        
        // Add each tag URL
        foreach ($tags as $tag) {
            $tagUrl = $baseUrl . '?txt=' . urlencode($tag['tag']) . '&nosave=1';
            $lastmod = date('Y-m-d', strtotime($tag['created_at']));
            
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . htmlspecialchars($tagUrl) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . $lastmod . '</lastmod>' . "\n";
            $xml .= '    <changefreq>weekly</changefreq>' . "\n";
            $xml .= '    <priority>0.8</priority>' . "\n";
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    } catch (PDOException $e) {
        error_log("Sitemap generation error: " . $e->getMessage());
        return '';
    }
}

// Check if sitemap is requested
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$isSitemapRequest = (
    strpos($requestUri, 'sitemap.xml') !== false || 
    isset($_GET['sitemap']) ||
    (isset($_GET['action']) && $_GET['action'] === 'sitemap')
);

if ($isSitemapRequest) {
    header('Content-Type: application/xml; charset=UTF-8');
    $db = initDatabase();
    echo generateSitemap($db);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $db = initDatabase();
    $userIP = getUserIP();
    
    if ($_POST['action'] === 'save_tag') {
        $text = $_POST['text'] ?? '';
        $result = insertTag($db, $text, $userIP);
        echo json_encode($result);
        exit;
    }
    
    if ($_POST['action'] === 'get_tag_count') {
        echo json_encode(['count' => getTagCount($db)]);
        exit;
    }
}

// Initialize database and get random tags
$db = initDatabase();
$randomTags = getRandomTags($db, 10);
$totalTags = getTagCount($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xsukax MD5 Generator</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Noto Sans', Helvetica, Arial, sans-serif; background: #ffffff; color: #24292f; line-height: 1.6; }
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem 1rem; }
        .header { text-align: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid #d0d7de; }
        .header h1 { font-size: 1.75rem; font-weight: 600; color: #24292f; margin-bottom: 0.25rem; }
        .header p { color: #656d76; font-size: 0.875rem; }
        .card { background: #f6f8fa; border: 1px solid #d0d7de; border-radius: 6px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .input-wrapper { margin-bottom: 1rem; }
        .label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; color: #24292f; }
        .input-area { width: 100%; min-height: 120px; padding: 8px 12px; border: 1px solid #d0d7de; border-radius: 6px; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace; font-size: 0.875rem; line-height: 1.5; resize: vertical; transition: border-color 0.2s, box-shadow 0.2s; background: #ffffff; }
        .input-area:focus { outline: none; border-color: #0969da; box-shadow: inset 0 0 0 1px #0969da; }
        .button-group { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 5px 16px; font-size: 0.875rem; font-weight: 500; line-height: 20px; white-space: nowrap; vertical-align: middle; cursor: pointer; user-select: none; border: 1px solid; border-radius: 6px; transition: all 0.15s ease-in-out; text-decoration: none; }
        .btn-primary { color: #ffffff; background-color: #1f883d; border-color: rgba(27, 31, 36, 0.15); box-shadow: 0 1px 0 rgba(27, 31, 36, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.03); }
        .btn-primary:hover { background-color: #1a7f37; border-color: rgba(27, 31, 36, 0.15); box-shadow: 0 1px 0 rgba(27, 31, 36, 0.1), inset 0 1px 0 rgba(255, 255, 255, 0.03); }
        .btn-primary:active { background-color: #188033; box-shadow: inset 0 1px 0 rgba(20, 70, 32, 0.2); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
        .btn-secondary { color: #24292f; background-color: #f6f8fa; border-color: rgba(27, 31, 36, 0.15); box-shadow: 0 1px 0 rgba(27, 31, 36, 0.04), inset 0 1px 0 rgba(255, 255, 255, 0.25); }
        .btn-secondary:hover { background-color: #f3f4f6; border-color: rgba(27, 31, 36, 0.15); }
        .btn-secondary:active { background-color: #ebecf0; box-shadow: inset 0 1px 0 rgba(27, 31, 36, 0.15); }
        .output-box { background: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; padding: 12px; font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace; font-size: 0.875rem; word-break: break-all; color: #0969da; user-select: all; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.75rem; margin-top: 1rem; }
        .stat-item { background: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; padding: 0.625rem; }
        .stat-label { font-size: 0.75rem; color: #656d76; margin-bottom: 0.125rem; }
        .stat-value { font-size: 0.875rem; font-weight: 600; color: #24292f; }
        .tag-cloud { background: #f6f8fa; border: 1px solid #d0d7de; border-radius: 6px; padding: 1.25rem; margin-bottom: 1.5rem; }
        .tag-cloud-title { font-size: 0.875rem; font-weight: 600; color: #24292f; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .tags-container { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; justify-content: center; min-height: 80px; }
        .tag { display: inline-block; padding: 4px 12px; font-size: 0.8125rem; color: #0969da; background: #ddf4ff; border: 1px solid #54aeff66; border-radius: 24px; text-decoration: none; transition: all 0.15s ease-in-out; font-weight: 500; }
        .tag:hover { background: #b6e3ff; border-color: #54aeff; transform: translateY(-1px); }
        .tag.size-1 { font-size: 0.75rem; opacity: 0.8; }
        .tag.size-2 { font-size: 0.8125rem; opacity: 0.9; }
        .tag.size-3 { font-size: 0.875rem; }
        .tag.size-4 { font-size: 0.9375rem; font-weight: 600; }
        .tag.size-5 { font-size: 1rem; font-weight: 600; }
        .notification { position: fixed; top: 1rem; right: 1rem; background: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; padding: 12px 16px; box-shadow: 0 8px 24px rgba(140, 149, 159, 0.2); display: flex; align-items: center; gap: 8px; z-index: 1000; animation: slideIn 0.2s ease-out; font-size: 0.875rem; }
        .notification.success { border-left: 3px solid #1f883d; }
        .notification.info { border-left: 3px solid #0969da; }
        .notification.warning { border-left: 3px solid #bf8700; }
        .notification.error { border-left: 3px solid #d1242f; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .footer { text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #d0d7de; color: #656d76; font-size: 0.75rem; }
        .footer a { color: #0969da; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        .hidden { display: none; }
        .refresh-btn { color: #656d76; background: none; border: none; padding: 0; cursor: pointer; font-size: 1rem; transition: transform 0.2s; }
        .refresh-btn:hover { transform: rotate(90deg); color: #24292f; }
        .db-badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 2px 8px; background: #ddf4ff; border: 1px solid #54aeff66; border-radius: 12px; font-size: 0.6875rem; font-weight: 500; color: #0969da; margin-left: 0.5rem; }
        .auto-save-indicator { display: inline-flex; align-items: center; gap: 0.25rem; padding: 2px 8px; background: #d1f4e0; border: 1px solid #1f883d66; border-radius: 12px; font-size: 0.6875rem; font-weight: 500; color: #1f883d; }
        .option-group { background: #ffffff; border: 1px solid #d0d7de; border-radius: 6px; padding: 0.75rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem; }
        .checkbox-wrapper { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .checkbox-wrapper input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; accent-color: #1f883d; }
        .checkbox-wrapper label { font-size: 0.875rem; color: #24292f; cursor: pointer; user-select: none; }
        .checkbox-wrapper .description { font-size: 0.75rem; color: #656d76; margin-left: 1.5rem; }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>🔐 xsukax MD5 Generator</h1>
            <p>Client-side MD5 hash generation • Privacy-first • Fully offline • Rate limited: 20 tags/minute • <a href="?sitemap" style="color: #0969da; text-decoration: none;" target="_blank">📄 Sitemap</a></p>
        </header>

        <?php if (!empty($randomTags)): ?>
        <section class="tag-cloud">
            <div class="tag-cloud-title">
                <span>🏷️ Quick Hash Tags</span>
                <span class="db-badge">📊 <?php echo $totalTags; ?> tags</span>
                <button class="refresh-btn" onclick="location.reload()" title="Refresh tags">↻</button>
            </div>
            <div class="tags-container">
                <?php 
                foreach ($randomTags as $tag): 
                    $tag = trim($tag);
                    if (empty($tag)) continue;
                    
                    // Parse tag format: "text|url" or just "text"
                    $parts = explode('|', $tag, 2);
                    $tagText = $parts[0];
                    
                    if (isset($parts[1])) {
                        // Custom URL provided
                        $tagUrl = $parts[1];
                        // Add nosave parameter if it's a relative URL starting with ?
                        if (strpos($tagUrl, '?') === 0) {
                            $tagUrl .= (strpos($tagUrl, '&') !== false ? '&' : '&') . 'nosave=1';
                        }
                    } else {
                        // Auto-generate URL with nosave parameter
                        $tagUrl = '?txt=' . urlencode($tagText) . '&nosave=1';
                    }
                    
                    // Random size class for visual variety
                    $sizeClass = 'size-' . rand(1, 5);
                ?>
                <a href="<?php echo htmlspecialchars($tagUrl); ?>" class="tag <?php echo $sizeClass; ?>">
                    <?php echo htmlspecialchars($tagText); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <main class="card">
            <div class="option-group">
                <div class="checkbox-wrapper">
                    <input type="checkbox" id="autoSave" checked>
                    <label for="autoSave">Auto-save generated text as tag</label>
                </div>
                <span class="auto-save-indicator" id="autoSaveStatus">✓ Enabled</span>
            </div>
            <div class="checkbox-wrapper" style="margin-left: 1rem; margin-bottom: 1rem;">
                <span class="description">Automatically adds generated text to database (max 30 chars) • Protected by rate limiting (20 tags per minute per user) • Use ?txt=text to hash & save • Use ?txt=text&nosave=1 to hash without saving</span>
            </div>

            <div class="input-wrapper">
                <label class="label" for="textInput">Input Text</label>
                <textarea id="textInput" class="input-area" placeholder="Enter text to generate MD5 hash..." autofocus><?php echo isset($_GET['txt']) ? htmlspecialchars($_GET['txt']) : ''; ?></textarea>
            </div>

            <div class="button-group">
                <button id="generateBtn" class="btn btn-primary">Generate Hash</button>
                <button id="clearBtn" class="btn btn-secondary">Clear</button>
                <button id="copyBtn" class="btn btn-secondary">Copy Hash</button>
            </div>

            <div id="outputSection" class="hidden">
                <label class="label">MD5 Hash</label>
                <div id="hashOutput" class="output-box"></div>
                
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-label">Input Length</div>
                        <div class="stat-value" id="inputLength">0 chars</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Hash Length</div>
                        <div class="stat-value">32 chars</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Algorithm</div>
                        <div class="stat-value">MD5</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Total Tags</div>
                        <div class="stat-value" id="totalTagsCount"><?php echo $totalTags; ?></div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="footer">
            <p>Created by <a href="https://github.com/xsukax" target="_blank">xsukax</a> • Client-side processing • SQLite tag storage • <?php echo $totalTags; ?> tags in database • Rate limited for security • <a href="?sitemap" target="_blank">Sitemap.xml</a></p>
        </footer>
    </div>

    <script>
        // MD5 Implementation (RFC 1321)
        function md5(string) {
            function rotateLeft(value, shift) {
                return (value << shift) | (value >>> (32 - shift));
            }

            function addUnsigned(x, y) {
                const lsw = (x & 0xFFFF) + (y & 0xFFFF);
                const msw = (x >> 16) + (y >> 16) + (lsw >> 16);
                return (msw << 16) | (lsw & 0xFFFF);
            }

            function md5F(x, y, z) { return (x & y) | (~x & z); }
            function md5G(x, y, z) { return (x & z) | (y & ~z); }
            function md5H(x, y, z) { return x ^ y ^ z; }
            function md5I(x, y, z) { return y ^ (x | ~z); }

            function md5FF(a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(md5F(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            }

            function md5GG(a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(md5G(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            }

            function md5HH(a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(md5H(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            }

            function md5II(a, b, c, d, x, s, ac) {
                a = addUnsigned(a, addUnsigned(addUnsigned(md5I(b, c, d), x), ac));
                return addUnsigned(rotateLeft(a, s), b);
            }

            function convertToWordArray(string) {
                const wordArray = [];
                const length = string.length;
                for (let i = 0; i < length; i++) {
                    const charCode = string.charCodeAt(i);
                    if (charCode < 128) {
                        wordArray.push(charCode);
                    } else if (charCode < 2048) {
                        wordArray.push((charCode >> 6) | 192);
                        wordArray.push((charCode & 63) | 128);
                    } else if (((charCode & 0xFC00) === 0xD800) && (i + 1 < length) && ((string.charCodeAt(i + 1) & 0xFC00) === 0xDC00)) {
                        const surrogatePair = 0x10000 + ((charCode & 0x03FF) << 10) + (string.charCodeAt(++i) & 0x03FF);
                        wordArray.push((surrogatePair >> 18) | 240);
                        wordArray.push(((surrogatePair >> 12) & 63) | 128);
                        wordArray.push(((surrogatePair >> 6) & 63) | 128);
                        wordArray.push((surrogatePair & 63) | 128);
                    } else {
                        wordArray.push((charCode >> 12) | 224);
                        wordArray.push(((charCode >> 6) & 63) | 128);
                        wordArray.push((charCode & 63) | 128);
                    }
                }
                return wordArray;
            }

            const utf8Bytes = convertToWordArray(string);
            const messageLenBytes = utf8Bytes.length;
            const numberOfWords = (((messageLenBytes + 8) >>> 6) + 1) << 4;
            const wordArray = new Array(numberOfWords);

            for (let i = 0; i < numberOfWords; i++) {
                wordArray[i] = 0;
            }

            for (let i = 0; i < messageLenBytes; i++) {
                wordArray[i >>> 2] |= utf8Bytes[i] << ((i % 4) * 8);
            }

            wordArray[messageLenBytes >>> 2] |= 0x80 << ((messageLenBytes % 4) * 8);
            wordArray[numberOfWords - 2] = messageLenBytes << 3;
            wordArray[numberOfWords - 1] = messageLenBytes >>> 29;

            let a = 0x67452301;
            let b = 0xEFCDAB89;
            let c = 0x98BADCFE;
            let d = 0x10325476;

            const S11 = 7, S12 = 12, S13 = 17, S14 = 22;
            const S21 = 5, S22 = 9, S23 = 14, S24 = 20;
            const S31 = 4, S32 = 11, S33 = 16, S34 = 23;
            const S41 = 6, S42 = 10, S43 = 15, S44 = 21;

            for (let i = 0; i < numberOfWords; i += 16) {
                const aa = a, bb = b, cc = c, dd = d;

                a = md5FF(a, b, c, d, wordArray[i + 0], S11, 0xD76AA478);
                d = md5FF(d, a, b, c, wordArray[i + 1], S12, 0xE8C7B756);
                c = md5FF(c, d, a, b, wordArray[i + 2], S13, 0x242070DB);
                b = md5FF(b, c, d, a, wordArray[i + 3], S14, 0xC1BDCEEE);
                a = md5FF(a, b, c, d, wordArray[i + 4], S11, 0xF57C0FAF);
                d = md5FF(d, a, b, c, wordArray[i + 5], S12, 0x4787C62A);
                c = md5FF(c, d, a, b, wordArray[i + 6], S13, 0xA8304613);
                b = md5FF(b, c, d, a, wordArray[i + 7], S14, 0xFD469501);
                a = md5FF(a, b, c, d, wordArray[i + 8], S11, 0x698098D8);
                d = md5FF(d, a, b, c, wordArray[i + 9], S12, 0x8B44F7AF);
                c = md5FF(c, d, a, b, wordArray[i + 10], S13, 0xFFFF5BB1);
                b = md5FF(b, c, d, a, wordArray[i + 11], S14, 0x895CD7BE);
                a = md5FF(a, b, c, d, wordArray[i + 12], S11, 0x6B901122);
                d = md5FF(d, a, b, c, wordArray[i + 13], S12, 0xFD987193);
                c = md5FF(c, d, a, b, wordArray[i + 14], S13, 0xA679438E);
                b = md5FF(b, c, d, a, wordArray[i + 15], S14, 0x49B40821);
                a = md5GG(a, b, c, d, wordArray[i + 1], S21, 0xF61E2562);
                d = md5GG(d, a, b, c, wordArray[i + 6], S22, 0xC040B340);
                c = md5GG(c, d, a, b, wordArray[i + 11], S23, 0x265E5A51);
                b = md5GG(b, c, d, a, wordArray[i + 0], S24, 0xE9B6C7AA);
                a = md5GG(a, b, c, d, wordArray[i + 5], S21, 0xD62F105D);
                d = md5GG(d, a, b, c, wordArray[i + 10], S22, 0x02441453);
                c = md5GG(c, d, a, b, wordArray[i + 15], S23, 0xD8A1E681);
                b = md5GG(b, c, d, a, wordArray[i + 4], S24, 0xE7D3FBC8);
                a = md5GG(a, b, c, d, wordArray[i + 9], S21, 0x21E1CDE6);
                d = md5GG(d, a, b, c, wordArray[i + 14], S22, 0xC33707D6);
                c = md5GG(c, d, a, b, wordArray[i + 3], S23, 0xF4D50D87);
                b = md5GG(b, c, d, a, wordArray[i + 8], S24, 0x455A14ED);
                a = md5GG(a, b, c, d, wordArray[i + 13], S21, 0xA9E3E905);
                d = md5GG(d, a, b, c, wordArray[i + 2], S22, 0xFCEFA3F8);
                c = md5GG(c, d, a, b, wordArray[i + 7], S23, 0x676F02D9);
                b = md5GG(b, c, d, a, wordArray[i + 12], S24, 0x8D2A4C8A);
                a = md5HH(a, b, c, d, wordArray[i + 5], S31, 0xFFFA3942);
                d = md5HH(d, a, b, c, wordArray[i + 8], S32, 0x8771F681);
                c = md5HH(c, d, a, b, wordArray[i + 11], S33, 0x6D9D6122);
                b = md5HH(b, c, d, a, wordArray[i + 14], S34, 0xFDE5380C);
                a = md5HH(a, b, c, d, wordArray[i + 1], S31, 0xA4BEEA44);
                d = md5HH(d, a, b, c, wordArray[i + 4], S32, 0x4BDECFA9);
                c = md5HH(c, d, a, b, wordArray[i + 7], S33, 0xF6BB4B60);
                b = md5HH(b, c, d, a, wordArray[i + 10], S34, 0xBEBFBC70);
                a = md5HH(a, b, c, d, wordArray[i + 13], S31, 0x289B7EC6);
                d = md5HH(d, a, b, c, wordArray[i + 0], S32, 0xEAA127FA);
                c = md5HH(c, d, a, b, wordArray[i + 3], S33, 0xD4EF3085);
                b = md5HH(b, c, d, a, wordArray[i + 6], S34, 0x04881D05);
                a = md5HH(a, b, c, d, wordArray[i + 9], S31, 0xD9D4D039);
                d = md5HH(d, a, b, c, wordArray[i + 12], S32, 0xE6DB99E5);
                c = md5HH(c, d, a, b, wordArray[i + 15], S33, 0x1FA27CF8);
                b = md5HH(b, c, d, a, wordArray[i + 2], S34, 0xC4AC5665);
                a = md5II(a, b, c, d, wordArray[i + 0], S41, 0xF4292244);
                d = md5II(d, a, b, c, wordArray[i + 7], S42, 0x432AFF97);
                c = md5II(c, d, a, b, wordArray[i + 14], S43, 0xAB9423A7);
                b = md5II(b, c, d, a, wordArray[i + 5], S44, 0xFC93A039);
                a = md5II(a, b, c, d, wordArray[i + 12], S41, 0x655B59C3);
                d = md5II(d, a, b, c, wordArray[i + 3], S42, 0x8F0CCC92);
                c = md5II(c, d, a, b, wordArray[i + 10], S43, 0xFFEFF47D);
                b = md5II(b, c, d, a, wordArray[i + 1], S44, 0x85845DD1);
                a = md5II(a, b, c, d, wordArray[i + 8], S41, 0x6FA87E4F);
                d = md5II(d, a, b, c, wordArray[i + 15], S42, 0xFE2CE6E0);
                c = md5II(c, d, a, b, wordArray[i + 6], S43, 0xA3014314);
                b = md5II(b, c, d, a, wordArray[i + 13], S44, 0x4E0811A1);
                a = md5II(a, b, c, d, wordArray[i + 4], S41, 0xF7537E82);
                d = md5II(d, a, b, c, wordArray[i + 11], S42, 0xBD3AF235);
                c = md5II(c, d, a, b, wordArray[i + 2], S43, 0x2AD7D2BB);
                b = md5II(b, c, d, a, wordArray[i + 9], S44, 0xEB86D391);

                a = addUnsigned(a, aa);
                b = addUnsigned(b, bb);
                c = addUnsigned(c, cc);
                d = addUnsigned(d, dd);
            }

            function wordToHex(word) {
                let hex = '';
                for (let i = 0; i < 4; i++) {
                    const byte = (word >>> (i * 8)) & 0xFF;
                    hex += byte.toString(16).padStart(2, '0');
                }
                return hex;
            }

            return wordToHex(a) + wordToHex(b) + wordToHex(c) + wordToHex(d);
        }

        // DOM Elements
        const textInput = document.getElementById('textInput');
        const generateBtn = document.getElementById('generateBtn');
        const clearBtn = document.getElementById('clearBtn');
        const copyBtn = document.getElementById('copyBtn');
        const hashOutput = document.getElementById('hashOutput');
        const outputSection = document.getElementById('outputSection');
        const inputLength = document.getElementById('inputLength');
        const autoSaveCheckbox = document.getElementById('autoSave');
        const autoSaveStatus = document.getElementById('autoSaveStatus');
        const totalTagsCount = document.getElementById('totalTagsCount');

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <span style="font-size: 16px;">${type === 'success' ? '✓' : type === 'warning' ? '⚠' : type === 'error' ? '✕' : 'ℹ'}</span>
                <span>${message}</span>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideIn 0.2s ease-out reverse';
                setTimeout(() => notification.remove(), 200);
            }, 3000);
        }

        // Update page title
        function updateTitle(text, hash) {
            if (text && hash) {
                const truncatedText = text.length > 30 ? text.substring(0, 30) + '...' : text;
                document.title = `xsukax MD5 Generator - ${hash}`;
            } else {
                document.title = 'xsukax MD5 Generator';
            }
        }

        // Save tag to database
        async function saveTagToDatabase(text) {
            if (!autoSaveCheckbox.checked || !text.trim()) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('action', 'save_tag');
                formData.append('text', text);

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    if (result.duplicate) {
                        showNotification('Tag already in database', 'info');
                    } else {
                        const remainingMsg = result.remaining !== undefined ? ` (${result.remaining} remaining)` : '';
                        showNotification(`Tag saved: "${result.tag}"${remainingMsg}`, 'success');
                        // Update tag count
                        updateTagCount();
                    }
                } else if (result.rate_limited) {
                    showNotification(result.message, 'error');
                } else {
                    showNotification(result.message || 'Failed to save tag', 'warning');
                }
            } catch (error) {
                console.error('Error saving tag:', error);
                showNotification('Error saving tag', 'error');
            }
        }

        // Update tag count
        async function updateTagCount() {
            try {
                const formData = new FormData();
                formData.append('action', 'get_tag_count');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (result.count !== undefined) {
                    totalTagsCount.textContent = result.count;
                }
            } catch (error) {
                console.error('Error getting tag count:', error);
            }
        }

        // Generate MD5 hash
        async function generateHash() {
            const text = textInput.value;
            
            if (!text) {
                showNotification('Please enter text to hash', 'info');
                textInput.focus();
                return;
            }

            const hash = md5(text);
            hashOutput.textContent = hash;
            inputLength.textContent = `${text.length} char${text.length !== 1 ? 's' : ''}`;
            outputSection.classList.remove('hidden');
            updateTitle(text, hash);
            showNotification('Hash generated', 'success');

            // Update URL with text parameter
            const url = new URL(window.location);
            url.searchParams.set('txt', text);
            if (!autoSaveCheckbox.checked) {
                url.searchParams.set('nosave', '1');
            } else {
                url.searchParams.delete('nosave');
            }
            window.history.pushState({}, '', url);

            // Save to database if auto-save is enabled
            await saveTagToDatabase(text);
        }

        // Clear input
        function clearInput() {
            textInput.value = '';
            hashOutput.textContent = '';
            outputSection.classList.add('hidden');
            updateTitle('', '');
            textInput.focus();
            
            // Clear URL parameters
            const url = new URL(window.location);
            url.searchParams.delete('txt');
            url.searchParams.delete('nosave');
            window.history.replaceState({}, '', url);
        }

        // Copy hash to clipboard
        async function copyHash() {
            const hash = hashOutput.textContent;
            
            if (!hash) {
                showNotification('No hash to copy', 'info');
                return;
            }

            try {
                await navigator.clipboard.writeText(hash);
                showNotification('Hash copied!', 'success');
            } catch (err) {
                const textarea = document.createElement('textarea');
                textarea.value = hash;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showNotification('Hash copied!', 'success');
            }
        }

        // Update auto-save status indicator
        function updateAutoSaveStatus() {
            if (autoSaveCheckbox.checked) {
                autoSaveStatus.textContent = '✓ Enabled';
                autoSaveStatus.style.background = '#d1f4e0';
                autoSaveStatus.style.borderColor = '#1f883d66';
                autoSaveStatus.style.color = '#1f883d';
            } else {
                autoSaveStatus.textContent = '✕ Disabled';
                autoSaveStatus.style.background = '#f6f8fa';
                autoSaveStatus.style.borderColor = '#d0d7de';
                autoSaveStatus.style.color = '#656d76';
            }
            
            // Update URL when checkbox changes
            const url = new URL(window.location);
            if (url.searchParams.has('txt')) {
                if (!autoSaveCheckbox.checked) {
                    url.searchParams.set('nosave', '1');
                } else {
                    url.searchParams.delete('nosave');
                }
                window.history.replaceState({}, '', url);
            }
        }

        // Event listeners
        generateBtn.addEventListener('click', generateHash);
        clearBtn.addEventListener('click', clearInput);
        copyBtn.addEventListener('click', copyHash);
        autoSaveCheckbox.addEventListener('change', updateAutoSaveStatus);
        
        textInput.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.key === 'Enter') {
                generateHash();
            }
        });

        // Real-time hashing (without auto-save on input)
        textInput.addEventListener('input', () => {
            if (textInput.value) {
                const hash = md5(textInput.value);
                hashOutput.textContent = hash;
                inputLength.textContent = `${textInput.value.length} char${textInput.value.length !== 1 ? 's' : ''}`;
                outputSection.classList.remove('hidden');
                updateTitle(textInput.value, hash);
                
                // Update URL during real-time typing
                const url = new URL(window.location);
                url.searchParams.set('txt', textInput.value);
                if (!autoSaveCheckbox.checked) {
                    url.searchParams.set('nosave', '1');
                } else {
                    url.searchParams.delete('nosave');
                }
                window.history.replaceState({}, '', url);
            } else {
                outputSection.classList.add('hidden');
                updateTitle('', '');
                
                // Clear URL parameters when input is empty
                const url = new URL(window.location);
                url.searchParams.delete('txt');
                url.searchParams.delete('nosave');
                window.history.replaceState({}, '', url);
            }
        });

        // Initialize from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        const hasTxt = urlParams.has('txt');
        const hasNoSave = urlParams.has('nosave');

        // Set auto-save checkbox based on URL parameter
        if (hasNoSave) {
            autoSaveCheckbox.checked = false;
        }

        // Initialize auto-save status
        updateAutoSaveStatus();

        // If URL has txt parameter, generate hash
        if (textInput.value && hasTxt) {
            generateHash();
        }
    </script>
</body>
</html>