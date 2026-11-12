<?php
/* ──────────────────────────────────────────────────────────────────────────
 * Online song metadata for the song info card, from open sources:
 *   MusicBrainz        release, date, label, track, ISRC, genres (no key)
 *   Cover Art Archive  a cover for songs without one embedded       (no key)
 *   Wikidata/Wikipedia artist bio and picture                       (no key)
 *   ListenBrainz       similar artists                              (no key)
 *   AcoustID           optional: identifies badly tagged files by their
 *                      audio fingerprint (needs fpcalc and a free key)
 *
 * api.php?action=songOnline&file=<file name>
 *
 * The song is found the same way lyrics.php does it (tags, then the file
 * name). Every answer, "not found" included, is cached in cache/online/;
 * network errors and rate limiting are not, so the next request tries again.
 * cover.php serves cache/online/<sha1>-cover.* for songs without a cover, but
 * never starts a lookup itself (every table row asks for a thumbnail).
 * ────────────────────────────────────────────────────────────────────────── */

const META_CACHE_DIR    = __DIR__ . '/cache/online';
const META_CACHE_FORMAT = 5;            // bump to invalidate old cache entries
const META_RETRY_AFTER  = 30 * 86400;   // look a "not found" up again after 30 days
const META_MAX_DIFF     = 5;            // seconds a recording's length may differ
const META_MB_API       = 'https://musicbrainz.org/ws/2';
const META_MB_INTERVAL  = 1.1;          // MusicBrainz allows one request per second
const META_USER_AGENT   = 'mp3-web/1.0 ( https://github.com/Darknetzz/mp3-web )';
const META_WIKI_LANGS   = ['en', 'no', 'nb', 'sv', 'da', 'de'];
const META_LB_SIMILAR   = 'https://labs.api.listenbrainz.org/similar-artists/json';
const META_LB_ALGORITHM = 'session_based_days_7500_session_300_contribution_5_threshold_10_limit_100_filter_True_skip_30';

/** Thrown when a source can't be reached or rate-limits us: don't cache the answer. */
class MetaNetworkError extends RuntimeException {}

/** Cache file base for a song (absolute path). */
function metaCacheBase(string $path): string {
    return META_CACHE_DIR . '/' . sha1(realpath($path) ?: $path);
}

/** The online cover saved for a song, or null. */
function metaCoverFile(string $path): ?string {
    foreach (glob(metaCacheBase($path) . '-cover.*') ?: [] as $f) {
        return $f;
    }
    return null;
}

/** GET a URL. Returns [httpCode, body]; code 0 = network error. */
function metaHttp(string $url, array $opts = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, $opts + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => META_USER_AGENT,
    ]);
    $body = curl_exec($ch);
    $code = $body === false ? 0 : (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, is_string($body) ? $body : ''];
}

/** GET JSON. 404 → null; other failures throw MetaNetworkError. */
function metaJson(string $url, array $opts = []): ?array {
    [$code, $body] = metaHttp($url, $opts + [CURLOPT_HTTPHEADER => ['Accept: application/json']]);
    if ($code === 404) return null;
    if ($code !== 200) throw new MetaNetworkError("HTTP $code from " . parse_url($url, PHP_URL_HOST));
    $json = json_decode($body, true);
    return is_array($json) ? $json : null;
}

/** GET a MusicBrainz endpoint, keeping to its rate limit across PHP requests. */
function metaMb(string $endpoint, array $query): ?array {
    $lock = @fopen(META_CACHE_DIR . '/.musicbrainz', 'c+');
    if ($lock) {
        flock($lock, LOCK_EX);
        $last = (float) stream_get_contents($lock);
        $wait = $last + META_MB_INTERVAL - microtime(true);
        if ($wait > 0) usleep((int) ($wait * 1e6));
    }
    $url = META_MB_API . $endpoint . '?' . http_build_query($query + ['fmt' => 'json']);
    try {
        try {
            return metaJson($url);
        } catch (MetaNetworkError $e) {
            // MusicBrainz answers 503 now and then when busy; one more try.
            usleep(2000000);
            return metaJson($url);
        }
    } finally {
        if ($lock) {
            ftruncate($lock, 0);
            rewind($lock);
            fwrite($lock, (string) microtime(true));
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

/** A Lucene phrase for a MusicBrainz search. */
function metaPhrase(string $s): string {
    return '"' . addcslashes($s, '"\\') . '"';
}

/** "A feat. B" from a MusicBrainz artist credit. */
function metaCreditName(array $credit): string {
    $out = '';
    foreach ($credit as $c) {
        $out .= ($c['name'] ?? ($c['artist']['name'] ?? '')) . ($c['joinphrase'] ?? '');
    }
    return trim($out);
}

/** How good a release is to show for a song: an official album or single,
 *  not a compilation, mix or live recording, the earlier the better. Lower is better. */
function metaReleaseRank(array $rel, string $localAlbum = ''): array {
    $rg    = $rel['release-group'] ?? [];
    $type  = $rg['primary-type'] ?? '';
    $types = ['Album' => 0, 'EP' => 1, 'Single' => 2];
    $date  = (string) ($rel['date'] ?? '');
    return [
        $localAlbum !== '' && lyricsNorm((string) ($rel['title'] ?? '')) === lyricsNorm($localAlbum) ? 0 : 1,
        ($rel['status'] ?? '') === 'Official' ? 0 : 1,
        empty($rg['secondary-types']) ? 0 : 1,
        $types[$type] ?? 3,
        $date === '' ? '9999' : $date,
    ];
}

/** The best release of a recording, or null. */
function metaBestRelease(array $releases, string $localAlbum = ''): ?array {
    $best = null;
    foreach ($releases as $rel) {
        if (!is_array($rel)) continue;
        if ($best === null || metaReleaseRank($rel, $localAlbum) < metaReleaseRank($best, $localAlbum)) {
            $best = $rel;
        }
    }
    return $best;
}

/** Find the song's MusicBrainz recording by artist/title. Returns the search hit or null. */
function metaSearchRecording(array $candidates, ?float $duration, string $haystack, string $localAlbum): ?array {
    foreach ($candidates as [$artist, $title]) {
        $query = 'recording:' . metaPhrase($title) . ($artist !== '' ? ' AND artist:' . metaPhrase($artist) : '');
        $json  = metaMb('/recording', ['query' => $query, 'limit' => 25]);
        $best  = null;
        foreach ($json['recordings'] ?? [] as $r) {
            if ((int) ($r['score'] ?? 0) < 70) continue;
            // Reuse the lyrics matcher: same title, and a credited artist in our tags/file name.
            $match = ['trackName' => (string) ($r['title'] ?? ''), 'artistName' => metaCreditName($r['artist-credit'] ?? [])];
            if (!lyricsResultMatches($match, $title, $haystack)) continue;
            $length = isset($r['length']) ? $r['length'] / 1000 : null;
            if ($duration && $length && abs($length - $duration) > META_MAX_DIFF) continue;
            $rel  = metaBestRelease($r['releases'] ?? [], $localAlbum);
            $rank = array_merge($rel ? metaReleaseRank($rel, $localAlbum) : [1, 1, 1, 9, '9999'], [-(int) ($r['score'] ?? 0)]);
            if ($best === null || $rank < $best[0]) $best = [$rank, $r];
        }
        if ($best) return $best[1];
    }
    return null;
}

/** Identify a song by its audio fingerprint. Returns MusicBrainz recording ids, best first. */
function metaAcoustId(string $path): array {
    $key = trim((string) getConfig('acoustid_key'));
    if ($key === '' || !function_exists('exec')) return [];
    $fpcalc = trim((string) @shell_exec('command -v fpcalc 2>/dev/null'));
    if ($fpcalc === '') return [];
    $out = [];
    @exec(escapeshellarg($fpcalc) . ' -json ' . escapeshellarg($path) . ' 2>/dev/null', $out);
    $fp = json_decode(implode('', $out), true);
    if (empty($fp['fingerprint']) || empty($fp['duration'])) return [];
    $json = metaJson('https://api.acoustid.org/v2/lookup', [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'client' => $key, 'meta' => 'recordingids',
            'duration' => (int) round($fp['duration']), 'fingerprint' => $fp['fingerprint'],
        ]),
    ]);
    $ids = [];
    foreach ($json['results'] ?? [] as $res) {
        if ((float) ($res['score'] ?? 0) < 0.8) continue;
        foreach ($res['recordings'] ?? [] as $rec) {
            if (!empty($rec['id'])) $ids[] = $rec['id'];
        }
    }
    return array_values(array_unique($ids));
}

/** Short bio and picture from Wikipedia, via the artist's Wikidata item. */
function metaArtistBio(string $wikidataUrl): ?array {
    if (!preg_match('~/(Q\d+)$~', $wikidataUrl, $m)) return null;
    $json  = metaJson('https://www.wikidata.org/w/api.php?' . http_build_query([
        'action' => 'wbgetentities', 'ids' => $m[1], 'props' => 'sitelinks', 'format' => 'json',
    ]));
    $links = $json['entities'][$m[1]]['sitelinks'] ?? [];
    $pick  = null;
    foreach (META_WIKI_LANGS as $lang) {
        if (!empty($links[$lang . 'wiki']['title'])) { $pick = [$lang, $links[$lang . 'wiki']['title']]; break; }
    }
    if (!$pick) {
        foreach ($links as $site => $l) {
            if (preg_match('/^([a-z]{2,3})wiki$/', $site, $sm)) { $pick = [$sm[1], $l['title']]; break; }
        }
    }
    if (!$pick) return null;
    [$lang, $title] = $pick;
    $sum = metaJson("https://$lang.wikipedia.org/api/rest_v1/page/summary/" . rawurlencode(str_replace(' ', '_', $title)));
    if (!$sum || ($sum['type'] ?? '') === 'disambiguation' || empty($sum['extract'])) return null;
    return [
        'title'   => (string) ($sum['title'] ?? $title),
        'extract' => (string) $sum['extract'],
        'thumb'   => (string) ($sum['thumbnail']['source'] ?? ''),
        'url'     => (string) ($sum['content_urls']['desktop']['page'] ?? "https://$lang.wikipedia.org/wiki/" . rawurlencode($title)),
        'lang'    => $lang,
    ];
}

/** Every artist credited in the library, normalized → as written. */
function metaLibraryArtists(): array {
    $artists = [];
    foreach (musicLibrary() as $file) {
        $meta = getSongMeta($file);
        $names = preg_split('/\s*(?:,|&|\bx\b|\band\b|\bog\b|\bft\.?|\bfeat\.?|\bfeaturing\b|\bvs\.?)\s*/iu', (string) $meta['artist']);
        foreach ($names as $n) {
            $k = lyricsNorm($n);
            if (mb_strlen($k) >= 2) $artists[$k] = trim($n);
        }
        // Untagged files: "Artist - Title.mp3"
        if ($meta['artist'] === '' && preg_match('/^(.+?)\s+[-–—]\s+/u', pathinfo($file, PATHINFO_FILENAME), $m)) {
            $k = lyricsNorm($m[1]);
            if (mb_strlen($k) >= 2) $artists[$k] = trim($m[1]);
        }
    }
    return $artists;
}

/** Artists similar to this one, from ListenBrainz. Never throws: it's a "labs" API. */
function metaSimilarArtists(string $mbid): array {
    try {
        $json = metaJson(META_LB_SIMILAR . '?' . http_build_query(['artist_mbids' => $mbid, 'algorithm' => META_LB_ALGORITHM]));
    } catch (MetaNetworkError $e) {
        return [];
    }
    $library = metaLibraryArtists();
    $out = [];
    foreach ($json ?? [] as $a) {
        if (!is_array($a) || empty($a['name']) || ($a['artist_mbid'] ?? '') === $mbid) continue;
        $out[] = [
            'name'      => (string) $a['name'],
            'mbid'      => (string) ($a['artist_mbid'] ?? ''),
            'inLibrary' => isset($library[lyricsNorm($a['name'])]),
        ];
        if (count($out) >= 12) break;
    }
    // Ones you already have first, otherwise ListenBrainz's order
    usort($out, function ($a, $b) { return $b['inLibrary'] <=> $a['inLibrary']; });
    return $out;
}

/** Download a cover from the Cover Art Archive for a song without one. */
function metaSaveCover(string $path, ?string $releaseId, ?string $groupId): bool {
    $urls = [];
    if ($releaseId) $urls[] = "https://coverartarchive.org/release/$releaseId/front-500";
    if ($groupId)   $urls[] = "https://coverartarchive.org/release-group/$groupId/front-500";
    foreach ($urls as $url) {
        [$code, $data] = metaHttp($url, [CURLOPT_TIMEOUT => 20]);
        if ($code !== 200 || $data === '') continue;
        $info = @getimagesizefromstring($data);
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
        if (!$ext) continue;
        @file_put_contents(metaCacheBase($path) . '-cover.' . $ext, $data, LOCK_EX);
        metaForgetCovers($path);   // cover.php remembered "no cover"
        return true;
    }
    return false;
}

/** Drop cover.php's cached covers (and "no cover" markers) for a song whose
 *  online cover came or went. Only called for songs without an embedded cover. */
function metaForgetCovers(string $path): void {
    foreach (glob(__DIR__ . '/cache/covers/' . sha1(realpath($path) ?: $path) . '-*') ?: [] as $f) @unlink($f);
}

/** Forget a song's cached lookup (its match changed). */
function metaForgetLookup(string $path): void {
    @unlink(metaCacheBase($path) . '.json');
}

/** Does the file have a cover embedded? */
function metaHasEmbeddedCover(string $path): bool {
    try {
        return (bool) (new wapmorgan\Mp3Info\Mp3Info($path, true))->hasCover;
    } catch (Throwable $e) {
        return false;
    }
}

/** Look a song up. Returns the record to cache; throws MetaNetworkError. */
function metaLookup(string $path): array {
    $duration = null;
    $haystack = '';
    $candidates = lyricsCandidates($path, $duration, $haystack);
    $local = getSongMeta($path);
    $notFound = ['v' => META_CACHE_FORMAT, 'found' => false, 'checked' => time()];

    // 1. Which recording is it?
    $recId  = null;
    $source = 'search';
    $pinned = songOverride($path)['mbid'] ?? '';
    $ids = $pinned === '' ? metaAcoustId($path) : [];
    if ($pinned !== '') {
        $recId  = $pinned;     // chosen by hand in the song editor
        $source = 'pinned';
    } elseif ($ids) {
        $recId  = $ids[0];
        $source = 'acoustid';
    } else {
        $hit = metaSearchRecording($candidates, $duration, $haystack, (string) $local['album']);
        if ($hit) $recId = $hit['id'];
    }
    if (!$recId) return $notFound;

    // 2. The recording, its best release and its artist
    $rec = metaMb('/recording/' . $recId, ['inc' => 'artist-credits+releases+release-groups+genres+tags+isrcs+media']);
    if (!$rec) return $notFound;
    $rel = metaBestRelease($rec['releases'] ?? [], (string) $local['album']);

    $label = '';
    if ($rel) {
        $full = metaMb('/release/' . $rel['id'], ['inc' => 'labels']);
        foreach ($full['label-info'] ?? [] as $li) {
            if (!empty($li['label']['name']) && $li['label']['name'] !== '[no label]') { $label = $li['label']['name']; break; }
        }
    }
    $track = '';
    foreach ($rel['media'] ?? [] as $medium) {
        foreach ($medium['tracks'] ?? ($medium['track'] ?? []) as $t) {
            $track = (string) ($t['number'] ?? '');
            $total = (int) ($medium['track-count'] ?? 0);
            if ($track !== '' && $total) $track .= '/' . $total;
            break 2;
        }
    }

    $artistCredit = $rec['artist-credit'][0]['artist'] ?? null;
    $artist = null;
    if ($artistCredit) {
        $artist = metaMb('/artist/' . $artistCredit['id'], ['inc' => 'url-rels+genres']);
    }

    $names = function ($list) {
        usort($list, function ($a, $b) { return ($b['count'] ?? 0) <=> ($a['count'] ?? 0); });
        return array_slice(array_values(array_unique(array_map(function ($g) { return (string) $g['name']; }, $list))), 0, 5);
    };
    $genres = $names($rec['genres'] ?? []);
    if (!$genres && $artist) $genres = $names($artist['genres'] ?? []);

    // 3. Bio, similar artists, cover
    $bio = null;
    foreach ($artist['relations'] ?? [] as $r) {
        if (($r['type'] ?? '') === 'wikidata' && !empty($r['url']['resource'])) {
            try {
                $bio = metaArtistBio($r['url']['resource']);
            } catch (MetaNetworkError $e) {
                $bio = null;   // the MusicBrainz part is what matters; keep it
            }
            break;
        }
    }
    $similar = $artistCredit ? metaSimilarArtists($artistCredit['id']) : [];

    // Only an official, non-compilation release really is "this song's" album:
    // its title, track number and cover are used for the song. Others are just shown.
    $official = $rel && ($rel['status'] ?? '') === 'Official' && empty($rel['release-group']['secondary-types']);

    $hasCover = metaCoverFile($path) !== null;
    if ((!$official || songOverrideCoverFile($path)) && !metaHasEmbeddedCover($path)) {
        // A cover saved by an earlier lookup no longer holds up: drop it, and cover.php's copies.
        if ($hasCover) @unlink(metaCoverFile($path));
        metaForgetCovers($path);
        $hasCover = false;
    }
    if (!$hasCover && $official && !songOverrideCoverFile($path) && !metaHasEmbeddedCover($path)) {
        $hasCover = metaSaveCover($path, $rel['id'] ?? null, $rel['release-group']['id'] ?? null);
    }

    $date = (string) ($rel['date'] ?? ($rec['first-release-date'] ?? ''));
    return [
        'v'         => META_CACHE_FORMAT,
        'found'     => true,
        'checked'   => time(),
        'source'    => $source,
        'recording' => [
            'id'    => $rec['id'],
            'title' => (string) ($rec['title'] ?? ''),
            'url'   => 'https://musicbrainz.org/recording/' . $rec['id'],
        ],
        'artist'    => [
            'name'   => metaCreditName($rec['artist-credit'] ?? []),
            'id'     => $artistCredit['id'] ?? '',
            'url'    => $artistCredit ? 'https://musicbrainz.org/artist/' . $artistCredit['id'] : '',
        ],
        'album'     => (string) ($rel['title'] ?? ''),
        'albumType' => implode(' · ', array_filter(array_merge(
            [(string) ($rel['release-group']['primary-type'] ?? '')],
            (array) ($rel['release-group']['secondary-types'] ?? []),
            [($rel['status'] ?? 'Official') !== 'Official' ? (string) $rel['status'] : '']
        ))),
        'albumOfficial' => $official,
        'albumUrl'  => $rel ? 'https://musicbrainz.org/release/' . $rel['id'] : '',
        'date'      => $date,
        'year'      => substr((string) ($rec['first-release-date'] ?? $date), 0, 4),
        'label'     => $label,
        'track'     => $track,
        'isrc'      => (string) ($rec['isrcs'][0] ?? ''),
        'genres'    => $genres,
        'bio'       => $bio,
        'similar'   => $similar,
        'onlineCover' => $hasCover,
    ];
}

/** The cached record for a song if it is still fresh, else null. */
function metaCached(string $path): ?array {
    $cacheFile = metaCacheBase($path) . '.json';
    if (!is_file($cacheFile)) return null;
    $cached = json_decode((string) @file_get_contents($cacheFile), true);
    $fresh  = is_array($cached)
        && ($cached['v'] ?? 0) === META_CACHE_FORMAT
        && @filemtime($cacheFile) >= @filemtime($path)
        && (!empty($cached['found']) || time() - (int) ($cached['checked'] ?? 0) < META_RETRY_AFTER);
    return $fresh ? $cached : null;
}

/** The record for a song: from the cache, or looked up and cached ($force: always look up).
 *  Throws MetaNetworkError (nothing is cached then). */
function metaRefresh(string $path, bool $force = false): array {
    if (!is_dir(META_CACHE_DIR) && !@mkdir(META_CACHE_DIR, 0775, true)) {
        throw new MetaNetworkError("Can't write to cache/online/.");
    }
    if (!$force && ($cached = metaCached($path))) {
        return $cached + ['cached' => true];
    }
    $record = metaLookup($path);
    @file_put_contents(metaCacheBase($path) . '.json', json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $record + ['cached' => false];
}

/** API: online metadata for a song, from the cache or the sources above. */
function getSongOnline($song): void {
    if (!getConfig('online_metadata')) {
        apiResponse("error", "Online metadata is turned off.", ['disabled' => true]);
    }
    $path = lyricsSongPath($song);
    if ($path === null) {
        apiResponse("error", "Song not found.");
    }
    if ($cached = metaCached($path)) {
        apiResponse("success", "ok", $cached + ['cached' => true]);
    }

    // Looking up takes a few seconds (MusicBrainz's rate limit); don't hold the session lock.
    session_write_close();
    @set_time_limit(90);
    try {
        $record = metaRefresh($path);
    } catch (MetaNetworkError $e) {
        error_log('mp3-web online metadata: ' . $e->getMessage());
        apiResponse("error", "Couldn't reach MusicBrainz. Try again later.");
    }
    apiResponse("success", "ok", $record);
}
