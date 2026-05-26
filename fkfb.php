<?php

/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * fkfb.ca - News Link Sharer
 *
 * PURPOSE:
 *   Facebook suppresses links to certain news sites (CBC, Globe and Mail, etc)
 *   by not generating link previews. This script acts as a proxy — it fetches
 *   the og:title, og:description and og:image from the target article and
 *   re-serves them so Facebook's scraper sees a proper preview.
 *
 * HOW IT WORKS:
 *   1. User visits https://fk-fb.ca and pastes a news article URL
 *   2. Script generates a shareable link: https://fk-fb.ca/index.php?https://...
 *   3. When Facebook scrapes that link, the script fetches the article's og tags
 *      and serves them back to Facebook's bot without redirecting
 *   4. When a real user clicks the link, they are immediately redirected to the
 *      original article
 *   5. Fetched og tags are cached on disk for 1 hour to avoid hammering news sites
 *
 * USAGE Examples:
 *   - Helper UI:  https://fkfb.ca/
 *   - Direct:     https://fkfb.ca/index.php?https://www.cbc.ca/news/...
 *   - Debug mode: https://fkfb.ca/index.php?debug&https://www.cbc.ca/news/...
 *
 * SECURITY:
 *   - Only URLs from the $urls allowlist are accepted
 *   - All output is htmlspecialchars() encoded to prevent XSS
 *   - SSL verification is disabled for outbound fetches due to hosting constraints
 *
 * AUTHOR: Adrian Buss
 * UPDATED: May 2026
 */

// ============================================================
// ALLOWED NEWS SITES
// Only URLs from these domains will be proxied.
// Add new sites here as needed.
// ============================================================
$urls = [
    // ==========================================
    // CANADIAN - English
    // ==========================================
    "https://www.cbc.ca",
    "https://www.ctvnews.ca",
    "https://globalnews.ca",
    "https://www.theglobeandmail.com",
    "https://nationalpost.com",
    "https://www.thestar.com",
    "https://www.thecanadianpress.com",
    "https://www.macleans.ca",
    "https://www.theweathernetwork.com",
    "https://ottawacitizen.com",
    "https://montrealgazette.com",
    "https://calgaryherald.com",
    "https://edmontonjournal.com",
    "https://vancouversun.com",
    "https://www.winnipegfreepress.com",
    "https://www.thechronicleherald.ca",
    "https://www.timescolonist.com",
    "https://www.therecord.com",
    "https://www.thespec.com",
    "https://www.saltwire.com",
    "https://www.ipolitics.ca",
    "https://policyoptions.irpp.org",
    "https://theconversation.com",

    // ==========================================
    // CANADIAN - French
    // ==========================================
    "https://ici.radio-canada.ca",
    "https://www.lapresse.ca",
    "https://www.ledevoir.com",
    "https://www.journaldemontreal.com",
    "https://www.journaldequebec.com",
    "https://www.tvanouvelles.ca",

    // ==========================================
    // AMERICAN - Major Networks
    // ==========================================
    "https://www.nytimes.com",
    "https://www.washingtonpost.com",
    "https://www.wsj.com",
    "https://www.usatoday.com",
    "https://apnews.com",
    "https://www.reuters.com",
    "https://www.npr.org",
    "https://www.cnn.com",
    "https://www.nbcnews.com",
    "https://www.cbsnews.com",
    "https://www.abcnews.go.com",
    "https://www.foxnews.com",
    "https://www.msnbc.com",
    "https://www.pbs.org",
    "https://www.bloomberg.com",
    "https://www.axios.com",
    "https://thehill.com",
    "https://www.politico.com",
    "https://www.vox.com",
    "https://www.theatlantic.com",
    "https://www.newyorker.com",
    "https://time.com",
    "https://www.newsweek.com",
    "https://www.propublica.org",
    "https://slate.com",

    // ==========================================
    // AMERICAN - Regional
    // ==========================================
    "https://www.latimes.com",
    "https://www.bostonglobe.com",
    "https://www.chicagotribune.com",
    "https://nypost.com",
    "https://www.nydailynews.com",
    "https://www.sfgate.com",
    "https://www.seattletimes.com",
    "https://www.dallasnews.com",
    "https://www.miamiherald.com",
    "https://www.tampabay.com",
    "https://www.denverpost.com",
    "https://www.startribune.com",

    // ==========================================
    // INTERNATIONAL
    // ==========================================
    "https://www.bbc.com",
    "https://www.bbc.co.uk",
    "https://www.theguardian.com",
    "https://www.independent.co.uk",
    "https://www.telegraph.co.uk",
    "https://www.ft.com",
    "https://www.economist.com",
    "https://www.aljazeera.com",
    "https://www.dw.com",
    "https://www.france24.com",
    "https://www.lemonde.fr",
    "https://www.lefigaro.fr",
    "https://www.abc.net.au",
    "https://www.irishtimes.com",
    "https://www.smh.com.au",
    "https://www.nzherald.co.nz",
    "https://www.japantimes.co.jp",
    "https://www.scmp.com",
    "https://timesofindia.indiatimes.com",
    "https://www.haaretz.com",
    "https://www.timesofisrael.com",

    // ==========================================
    // TECH / SCIENCE / BUSINESS
    // ==========================================
    "https://techcrunch.com",
    "https://www.theverge.com",
    "https://arstechnica.com",
    "https://www.wired.com",
    "https://www.engadget.com",
    "https://www.cnet.com",
    "https://www.zdnet.com",
    "https://www.scientificamerican.com",
    "https://www.nature.com",
    "https://www.technologyreview.com",
    "https://phys.org",
    "https://fortune.com",
    "https://www.forbes.com",
    "https://www.businessinsider.com",
    "https://hbr.org",

    // ==========================================
    // SPORTS
    // ==========================================
    "https://www.tsn.ca",
    "https://www.sportsnet.ca",
    "https://www.espn.com",
    "https://theathletic.com",
    "https://www.si.com",
    "https://www.cbssports.com",
    "https://www.nhl.com",
    "https://www.nba.com",
    "https://www.mlb.com",
    "https://www.nfl.com",
    "https://www.mlssoccer.com",
];

// ============================================================
// CACHE SETTINGS
// Parsed og tags are cached on disk to avoid re-fetching the
// same article repeatedly. Cache files are stored in /tmp and
// expire after CACHE_TTL seconds.
// ============================================================
define('CACHE_DIR', '/tmp/fkfb_cache/');
define('CACHE_TTL', 3600); // 1 hour in seconds

// ============================================================
// SHOW HELPER UI
// If no query string is provided, show the link generator form
// instead of trying to proxy a URL.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SERVER['QUERY_STRING'])) {
    showHelperPage($_SERVER['SCRIPT_NAME']);
    exit;
}

// ============================================================
// DEBUG MODE
// Append ?debug& to any URL to see full diagnostic output:
//   https://fk-fb.ca/index.php?debug&https://www.cbc.ca/...
// Shows fetch log, all og tags found, and the final og:image.
// Safe to leave enabled — only triggers when debug& is in URL.
// ============================================================
$debugMode = isset($_GET['debug']);

// ============================================================
// RESOLVE TARGET URL
// Parse the query string to extract the target article URL,
// strip Facebook tracking params (fbclid etc), and validate
// against the allowlist.
// ============================================================
$url = resolveTargetUrl($urls);

// ============================================================
// FETCH PAGE & PARSE OG TAGS
// Try to load og tags from cache first. If not cached or cache
// is expired, fetch the article and parse its og tags, then
// save to cache for future requests.
// ============================================================
$cached = loadFromCache($url);

if ($cached !== false) {
    // Cache hit — use stored og tags
    $ogMetas  = $cached['ogMetas'];
    $tags     = $cached['tags'];
    $fetchLog = ['Loaded from cache.'];
} else {
    // Cache miss — fetch the article
    $fetchResult = fetchPage($url);
    $page        = $fetchResult['body'];
    $fetchLog    = $fetchResult['log'];

    $ogMetas = [];
    $tags    = [];

    if ($page !== false) {
        // Parse the HTML into a DOM document for xpath queries
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);  // suppress HTML parse warnings
        $dom->loadHTML($page);
        libxml_use_internal_errors(false);

        $xpath   = new DOMXPath($dom);
        $ogMetas = extractOgMetas($xpath);   // extract og:* meta properties
        $tags    = get_meta_tags($url) ?: []; // extract standard meta tags

        // Save to cache for next request
        saveToCache($url, $ogMetas, $tags);
    }
}

// ============================================================
// BUILD OUTPUT VARIABLES
// Construct all the values we need for the HTML output,
// making sure everything is properly escaped for HTML output.
// makeAbsoluteUrl() handles relative or protocol-relative
// image URLs that some news sites use.
// ============================================================
$domain    = parse_url($url, PHP_URL_HOST);
$domain    = preg_replace('/^www\./', '', $domain); // strip leading www.
$ogTitle   = $ogMetas['og:title'] ?? '';
$pageTitle = htmlspecialchars(
    $ogTitle ? "$domain -- $ogTitle" : $domain,
    ENT_QUOTES, 'UTF-8'
);

$lang        = htmlspecialchars(str_replace('_', '-', $ogMetas['og:locale'] ?? 'en'), ENT_QUOTES, 'UTF-8');
$description = htmlspecialchars($tags['description'] ?? $ogMetas['og:description'] ?? '', ENT_QUOTES, 'UTF-8');
$twitterImg  = htmlspecialchars(makeAbsoluteUrl($tags['twitter_image'] ?? '', $url), ENT_QUOTES, 'UTF-8');
$ogDesc      = htmlspecialchars($ogMetas['og:description'] ?? '', ENT_QUOTES, 'UTF-8');
$ogImage     = htmlspecialchars(makeAbsoluteUrl($ogMetas['og:image'] ?? '', $url), ENT_QUOTES, 'UTF-8');

// og:url points to THIS page (fk-fb.ca) not the original article,
// so Facebook associates the preview with our URL not CBC's
$ogUrl      = htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8');
$urlEscaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

// Detect Facebook's scraper by user agent string.
// Facebook sends facebookexternalhit or Facebot when scraping links.
// Real users get redirected; the bot gets served the og tags and stays.
$isFacebookBot = isFacebookBot();

// Always return HTTP 200 — returning any other code causes Facebook
// to discard the og tags and show a blank preview.
http_response_code(200);

?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
<meta charset="UTF-8">
<!-- Source article: <?= $urlEscaped ?> -->
<title><?= $pageTitle ?></title>

<!-- Standard meta description -->
<meta name="description" content="<?= $description ?>">

<!--
    Open Graph tags — this is what Facebook reads to generate the link preview.
    og:url        — the canonical URL of this page (must match what Facebook scraped)
    og:title      — shown as the bold link title in the Facebook post
    og:description — shown as the text below the title
    og:image      — the thumbnail image shown in the preview
-->
<meta property="og:type"        content="article">
<meta property="og:url"         content="<?= $ogUrl ?>">
<meta property="og:title"       content="<?= $pageTitle ?>">
<meta property="og:description" content="<?= $ogDesc ?>">
<meta property="og:image"       content="<?= $ogImage ?>">

<!--
    Twitter Card tags — same concept as og tags but for Twitter/X.
    summary_large_image shows a big image above the title.
    Falls back to og:image if no twitter:image is found.
-->
<meta name="twitter:card"        content="summary_large_image">
<meta name="twitter:title"       content="<?= $pageTitle ?>">
<meta name="twitter:description" content="<?= $ogDesc ?>">
<meta name="twitter:image"       content="<?= $twitterImg ?: $ogImage ?>">

<?php if (!$isFacebookBot && !$debugMode): ?>
<!--
    Meta refresh redirect — sends real users to the original article immediately.
    Facebook's bot does NOT follow meta refresh, so it stays and reads the og tags.
    The JavaScript redirect below is a fallback for browsers that ignore meta refresh.
-->
<meta http-equiv="refresh" content="0;url=<?= $urlEscaped ?>">
<?php endif; ?>

</head>
<body>

<?php if ($debugMode): ?>
<!-- ============================================================
     DEBUG OUTPUT — only shown when ?debug& is in the URL
     ============================================================ -->
<h2>Debug Info</h2>
<h3>Target URL</h3>
<pre><?= htmlspecialchars($url) ?></pre>
<h3>Visitor User Agent</h3>
<pre><?= htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'none') ?></pre>
<h3>Is Facebook Bot</h3>
<pre><?= $isFacebookBot ? 'YES' : 'NO' ?></pre>
<h3>Fetch Log</h3>
<pre><?= htmlspecialchars(implode("\n", $fetchLog)) ?></pre>
<h3>OG Metas Found</h3>
<pre><?= htmlspecialchars(print_r($ogMetas, true)) ?></pre>
<h3>Meta Tags Found</h3>
<pre><?= htmlspecialchars(print_r($tags, true)) ?></pre>
<h3>Final og:image</h3>
<pre><?= $ogImage ?: '(empty)' ?></pre>
<?php if ($ogImage): ?>
<img src="<?= $ogImage ?>" style="max-width:400px">
<?php endif; ?>
<?php else: ?>
<!-- Fallback redirect for real users -->
<p>Redirecting to <a href="<?= $urlEscaped ?>"><?= $urlEscaped ?></a>...</p>
<script>
    window.location.href = "<?= $urlEscaped ?>";
</script>
<?php endif; ?>

</body>
</html>
<?php

// ============================================================
// FUNCTIONS
// ============================================================

/**
 * isFacebookBot()
 * Returns true if the current request is from Facebook's scraper.
 * Facebook uses two user agent strings:
 *   - facebookexternalhit: the main link scraper
 *   - Facebot: used by some Facebook crawlers
 */
function isFacebookBot(): bool {
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return stripos($ua, 'facebookexternalhit') !== false
        || stripos($ua, 'Facebot') !== false;
}

/**
 * makeAbsoluteUrl()
 * Converts relative or protocol-relative image URLs to absolute URLs.
 * Some news sites use relative paths like /images/photo.jpg or
 * protocol-relative paths like //cdn.cbc.ca/photo.jpg in their og:image tags.
 *
 * @param string $imageUrl  The image URL from the og:image tag
 * @param string $pageUrl   The original article URL (used to extract scheme/host)
 * @return string           An absolute https:// URL
 */
function makeAbsoluteUrl(string $imageUrl, string $pageUrl): string {
    if (empty($imageUrl)) return '';

    // Already an absolute URL — nothing to do
    if (preg_match('/^https?:\/\//', $imageUrl)) return $imageUrl;

    // Protocol-relative URL e.g. //cdn.cbc.ca/image.jpg — prepend https:
    if (str_starts_with($imageUrl, '//')) return 'https:' . $imageUrl;

    // Relative path e.g. /images/photo.jpg — prepend scheme and host
    $scheme = parse_url($pageUrl, PHP_URL_SCHEME);
    $host   = parse_url($pageUrl, PHP_URL_HOST);
    return $scheme . '://' . $host . '/' . ltrim($imageUrl, '/');
}

/**
 * loadFromCache()
 * Loads cached og tags for a URL if they exist and haven't expired.
 * Cache files are stored as JSON in CACHE_DIR named by MD5 of the URL.
 *
 * @param string $url  The article URL to look up
 * @return array|false Cached data array or false if not cached/expired
 */
function loadFromCache(string $url): array|false {
    $file = CACHE_DIR . md5($url) . '.json';

    if (!file_exists($file)) return false;
    if (time() - filemtime($file) > CACHE_TTL) return false;

    $data = json_decode(file_get_contents($file), true);
    return $data ?: false;
}

/**
 * saveToCache()
 * Saves parsed og tags to disk cache as JSON.
 * Creates the cache directory if it doesn't exist.
 *
 * @param string $url      The article URL (used as cache key)
 * @param array  $ogMetas  Parsed og:* meta properties
 * @param array  $tags     Parsed standard meta tags
 */
function saveToCache(string $url, array $ogMetas, array $tags): void {
    if (!is_dir(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0750, true);
    }

    $file = CACHE_DIR . md5($url) . '.json';
    file_put_contents($file, json_encode([
        'url'     => $url,
        'ogMetas' => $ogMetas,
        'tags'    => $tags,
        'cached'  => time(),
    ]));
}

/**
 * showHelperPage()
 * Renders the link generator UI shown when no URL is provided.
 * Allows the user to paste a news article URL and get back a
 * shareable fk-fb.ca link with one click copy to clipboard.
 *
 * @param string $scriptName  The PHP script path (from $_SERVER['SCRIPT_NAME'])
 */
function showHelperPage(string $scriptName): void {
    $script = htmlspecialchars($scriptName, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>News Link Sharer</title>
        <style>
            body { font-family: sans-serif; max-width: 600px; margin: 60px auto; padding: 0 20px; }
            input[type=url] { width: 100%; padding: 10px; font-size: 1em; box-sizing: border-box; }
            button { margin-top: 10px; padding: 10px 20px; font-size: 1em; cursor: pointer; }
            #result { margin-top: 20px; word-break: break-all; }
            a { color: #0066cc; }
        </style>
    </head>
    <body>
        <h2>📰 News Link Sharer</h2>
        <p>Paste a news article URL to generate a shareable Facebook link:</p>
        <input type="url" id="urlInput" placeholder="https://www.cbc.ca/news/..." />
        <button onclick="generate()">Generate Link</button>
        <div id="result"></div>

        <script>
            function generate() {
                const input = document.getElementById('urlInput').value.trim();
                if (!input.startsWith('http')) {
                    document.getElementById('result').innerHTML = '<p style="color:red">Please enter a valid URL.</p>';
                    return;
                }
                // Build the shareable link — no encodeURIComponent so the URL stays readable
                const link = window.location.origin + '{$script}?' + input;
                document.getElementById('result').innerHTML =
                    '<p>Your shareable link:</p><a href="' + link + '">' + link + '</a>' +
                    '<br><br><button onclick="navigator.clipboard.writeText(\'' + link + '\')">📋 Copy</button>';
            }

            // Allow Enter key to trigger generation
            document.getElementById('urlInput').addEventListener('keydown', function(e) {
                if (e.key === 'Enter') generate();
            });
        </script>
    </body>
    </html>
    HTML;
}

/**
 * resolveTargetUrl()
 * Extracts and validates the target article URL from the query string.
 * - Decodes URL encoding (handles both encoded and plain URLs)
 * - Strips Facebook tracking params (fbclid and others after &)
 * - Strips debug& prefix if present
 * - Validates the URL is from an allowed domain
 * - Falls back to a random allowed site if URL is invalid
 *
 * @param array $urls  The allowlist of permitted domains
 * @return string      A validated absolute URL
 */
function resolveTargetUrl(array $urls): string {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' || empty($_SERVER['QUERY_STRING'])) {
        return $urls[array_rand($urls)];
    }

    // Decode percent-encoding (handles URLs pasted encoded or plain)
    $qs = urldecode($_SERVER['QUERY_STRING']);

    // Strip ?debug& prefix if present
    $qs = preg_replace('/^debug&?/', '', $qs);

    // Strip Facebook click tracking (fbclid=...) and anything after &
    $url = explode('&', $qs)[0];

    // Strip any trailing = left over from malformed requests
    $url = rtrim($url, '=');

    // Reject anything that isn't a valid http/https URL from the allowlist
    if (!preg_match('/^https?:\/\//', $url) || !isAllowedUrl($url, $GLOBALS['urls'])) {
        return $urls[array_rand($urls)];
    }

    return $url;
}

/**
 * isAllowedUrl()
 * Checks whether a URL's hostname matches any domain in the allowlist.
 * Prevents the script from being used as an open proxy.
 *
 * @param string $url       The URL to check
 * @param array  $allowlist Array of allowed base URLs
 * @return bool             True if the host is in the allowlist
 */
function isAllowedUrl(string $url, array $allowlist): bool {
    $host = parse_url($url, PHP_URL_HOST);
    foreach ($allowlist as $allowed) {
        if ($host === parse_url($allowed, PHP_URL_HOST)) {
            return true;
        }
    }
    return false;
}

/**
 * fetchPage()
 * Fetches the HTML of a news article, trying multiple user agents.
 * Some news sites (e.g. CBC) block generic browser user agents with 418,
 * so we fall back to Facebook's and Google's bot user agents which
 * news sites intentionally whitelist to allow social sharing.
 *
 * Attempt order:
 *   1. Regular Firefox browser UA
 *   2. Facebook's scraper UA (whitelisted by most news sites)
 *   3. Googlebot UA (whitelisted by virtually all news sites)
 *
 * @param string $url  The article URL to fetch
 * @return array       ['body' => string|false, 'log' => array]
 */
function fetchPage(string $url): array {
    $log = [];

    // Attempt 1: Regular browser
    $browserUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/116.0';
    [$body, $code, $err] = curlFetch($url, $browserUA);
    $log[] = "Attempt 1 (browser UA): HTTP $code" . ($err ? " — error: $err" : '');
    if ($body !== false && $code < 400) {
        $log[] = "Attempt 1 succeeded.";
        return ['body' => $body, 'log' => $log];
    }

    // Attempt 2: Facebook's scraper UA — whitelisted by most news sites
    $fbUA = 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)';
    [$body, $code, $err] = curlFetch($url, $fbUA);
    $log[] = "Attempt 2 (Facebook UA): HTTP $code" . ($err ? " — error: $err" : '');
    if ($body !== false && $code < 400) {
        $log[] = "Attempt 2 succeeded.";
        return ['body' => $body, 'log' => $log];
    }

    // Attempt 3: Googlebot UA — whitelisted by virtually all news sites
    $googleUA = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
    [$body, $code, $err] = curlFetch($url, $googleUA);
    $log[] = "Attempt 3 (Googlebot UA): HTTP $code" . ($err ? " — error: $err" : '');
    if ($body !== false && $code < 400) {
        $log[] = "Attempt 3 succeeded.";
        return ['body' => $body, 'log' => $log];
    }

    $log[] = "All fetch attempts failed.";
    return ['body' => false, 'log' => $log];
}

/**
 * curlFetch()
 * Makes an HTTP GET request using cURL with a specified user agent.
 * SSL verification is disabled to avoid cert chain issues on some
 * hosting environments.
 *
 * @param string $url  URL to fetch
 * @param string $ua   User agent string to send
 * @return array       [body, http_code, curl_error]
 */
function curlFetch(string $url, string $ua): array {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,   // return response as string
        CURLOPT_FOLLOWLOCATION => true,   // follow redirects
        CURLOPT_MAXREDIRS      => 5,      // max redirect hops
        CURLOPT_TIMEOUT        => 15,     // seconds before giving up
        CURLOPT_USERAGENT      => $ua,
        CURLOPT_SSL_VERIFYPEER => false,  // disabled due to hosting cert constraints
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_ENCODING       => '',     // handle gzip/deflate compression
        CURLOPT_HTTPHEADER     => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-CA,en;q=0.5',
            'Cache-Control: no-cache',
        ],
    ]);

    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($body === false) return [false, $code, $err];
    return [$body, $code, $err];
}

/**
 * extractOgMetas()
 * Uses XPath to extract all og:* meta properties from a parsed HTML document.
 * Returns an associative array like:
 *   ['og:title' => '...', 'og:image' => '...', 'og:description' => '...']
 *
 * @param DOMXPath $xpath  XPath object from the parsed article HTML
 * @return array           Associative array of og property => content
 */
function extractOgMetas(DOMXPath $xpath): array {
    $metas  = $xpath->query('//*/meta[starts-with(@property, "og:")]');
    $result = [];
    foreach ($metas as $meta) {
        $result[$meta->getAttribute('property')] = $meta->getAttribute('content');
    }
    return $result;
}
