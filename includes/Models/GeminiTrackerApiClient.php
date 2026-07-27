<?php
namespace AllI1D\GeminiTracker\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;

/**
 * Gemini Tracker API client.
 *
 * Gemini Tracker runs on UNIT3D (v9.2.0). The public API exposes a
 * "GET /torrents/filter" endpoint returning JSON resources shaped as
 * type / id / attributes, authenticated with the user's api_token.
 *
 * @see https://gemini-tracker.org/wikis/1
 */
class GeminiTrackerApiClient
{
    // @var Client
    private $client;
    private $baseUrl = 'https://gemini-tracker.org/api';
    private $apiKey = '';
    private $defaultParams = [
        'perPage'       => 100,
        'sortField'     => 'seeders',
        'sortDirection' => 'desc',
    ];

    // UNIT3D category_id mapping for this tracker (see wiki "Catégories").
    private $movieCategories  = [1, 7, 13];     // Films, Films Animations, Films Documentaires
    private $tvShowCategories = [2, 6, 14, 15];  // Séries, Series Animations, Series Documentaires, Émission TV

    public function __construct($apiKey = '')
    {
        $this->apiKey = $apiKey;
        $this->client = new Client();
    }

    /**
     * Test the connection to the Gemini Tracker API.
     * @return bool
     */
    public function testConnection()
    {
        try {
            $path = $this->baseUrl.'/torrents/filter?' . $this->buildQueryString(['name' => 'test']);
            error_log('Testing Gemini Tracker API connection with path: ' . $this->redact_url( $path ) );
            $response = $this->client->request('GET', $path, ['headers' => $this->headers()]);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            error_log('Gemini Tracker API connection test failed: ' . $this->redact_url( $e->getMessage() ));
            return false;
        }
    }

    /**
     * List torrents.
     * @param array $params
     * @return array|null
     */
    public function listTorrents($params = [])
    {
        try {
            $path = $this->baseUrl.'/torrents/filter?' . $this->buildQueryString($params);
            error_log('Requesting Gemini Tracker API with path: ' . $this->redact_url( $path ) );
            $response = $this->client->request('GET', $path, ['headers' => $this->headers()]);
            return $this->filter(json_decode($response->getBody()->getContents(), true), $params);
        } catch (RequestException $e) {
            error_log('Gemini Tracker API request failed: ' . $this->redact_url( $e->getMessage() ));
            return null;
        }
    }

    /**
     * Download the .torrent file for a given torrent resource (or id).
     * @param array|string $torrent The torrent resource from listTorrents(), or a raw id.
     * @return string|null
     */
    public function downloadTorrent($torrent)
    {
        $url = $this->resolve_download_url($torrent);
        if (null === $url) {
            error_log('Gemini Tracker API - unable to resolve a download URL for the torrent');
            return null;
        }
        try {
            error_log('Requesting Gemini Tracker API download with path: ' . $this->redact_url( $url ) );
            $response = $this->client->request('GET', $url, ['headers' => $this->headers()]);
            return $response->getBody()->getContents(); // Binary content of the .torrent
        } catch (RequestException $e) {
            error_log('Gemini Tracker API download failed: ' . $this->redact_url( $e->getMessage() ));
            return null;
        }
    }

    /**
     * Resolve the .torrent download URL from a torrent resource.
     *
     * UNIT3D resources expose attributes.download_link (rsskey embedded). When
     * absent we fall back to the canonical download route with the api_token.
     * @param array|string $torrent
     * @return string|null
     */
    private function resolve_download_url($torrent): ?string
    {
        if (is_array($torrent)) {
            if (!empty($torrent['attributes']['download_link'])) {
                return (string) $torrent['attributes']['download_link'];
            }
            if (!empty($torrent['download_link'])) {
                return (string) $torrent['download_link'];
            }
            $id = $torrent['id'] ?? ($torrent['attributes']['id'] ?? null);
        } else {
            $id = $torrent;
        }
        if (null === $id || '' === $id) {
            return null;
        }
        return sprintf('%s/torrents/download/%s?api_token=%s', $this->baseUrl, rawurlencode((string) $id), $this->apiKey);
    }

    /**
     * Build the authentication headers.
     * @return array
     */
    private function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
        ];
    }

    private function redact_url( string $url ): string {
        // Redact secrets passed as query parameters.
        $url = preg_replace(
            '/([?&](?:passkey|rsskey|api_key|apikey|api_token|token|key)=)[^&]+/',
            '$1***',
            $url
        );
        // Redact the rsskey embedded in download links (/torrent/download/{id}.{rsskey}).
        $url = preg_replace(
            '#(/torrent/download/\d+\.)[A-Za-z0-9]+#',
            '$1***',
            $url
        );
        return $url;
    }

    private function buildQueryString($params)
    {
        $params = array_merge($this->defaultParams, $params);
        $params = $this->whatToQuery($params);
        return http_build_query($params);
    }

    /**
     * Map the generic search parameters to Gemini Tracker (UNIT3D) filter params.
     * @param array $params
     * @return array
     */
    private function whatToQuery($params)
    {
        if (isset($params['type'])) {
            if ($params['type'] === 'movie') {
                $params['categories'] = $this->movieCategories;
            } elseif ($params['type'] === 'tvshow') {
                $params['categories'] = $this->tvShowCategories;
                $params = $this->saisonEtEpisodes($params);
            }
            unset($params['type']);
        }
        // 'lang' is not a UNIT3D filter param; it is only used client-side in filter().
        unset($params['lang']);
        return $params;
    }

    /**
     * Add season and episode filters for TV shows.
     * @param array $params
     * @return array
     */
    private function saisonEtEpisodes($params)
    {
        if (isset($params['saison'])) {
            if (intval($params['saison']) > 0) {
                $params['seasonNumber'] = intval($params['saison']);
            }
            unset($params['saison']);
        }
        if (isset($params['episode'])) {
            if (intval($params['episode']) > 0) {
                $params['episodeNumber'] = intval($params['episode']);
            }
            unset($params['episode']);
        }
        return $params;
    }

    /**
     * Filter the API response by name and language.
     * @param array $response
     * @param array $params
     * @return array
     */
    private function filter($response, $params)
    {
        if (!isset($response['data']) || !is_array($response['data']) || count($response['data']) === 0) {
            return [];
        }
        $what = isset($params['name']) ? str_replace([' '], '.', strtolower($params['name'])) : '';
        $lang = isset($params['lang']) ? $params['lang'] : null;
        $requested_title = isset($params['name']) ? (string) $params['name'] : '';
        $type = isset($params['type']) ? $params['type'] : null;
        $year = ('movie' === $type && isset($params['year']) && '' !== $params['year'] && null !== $params['year'])
            ? intval($params['year'])
            : null;
        $saison = ('tvshow' === $type && isset($params['saison']) && '' !== $params['saison'] && null !== $params['saison'])
            ? intval($params['saison'])
            : null;
        $episode = ('tvshow' === $type && isset($params['episode']) && '' !== $params['episode'] && null !== $params['episode'])
            ? intval($params['episode'])
            : null;
        $results = [];
        foreach ($response['data'] as $torrent) {
            $name = $this->torrent_name($torrent);
            // Skip torrents whose name does not contain the searched title (fast pre-filter).
            if ($what !== '' && $name !== '' && stripos($name, $what) === false) {
                continue;
            }
            // Language filter on the torrent name.
            if ($lang !== null && $name !== '' && !$this->matches_lang($name, $lang)) {
                continue;
            }
            // Stricter title/year/season/episode validation, delegated to the core plugin.
            $is_match = apply_filters('alli1d_torrent_matches_title', true, [
                'torrent_name' => $name,
                'title'        => $requested_title,
                'year'         => $year,
                'saison'       => $saison,
                'episode'      => $episode,
            ]);
            if (!$is_match) {
                do_action('alli1d_torrent_rejected', [
                    'torrent_name' => $name,
                    'title'        => $requested_title,
                    'reason'       => 'title_mismatch',
                ]);
                continue;
            }
            $results[] = $torrent;
        }
        return ['data' => $results];
    }

    /**
     * Extract the torrent display name from a UNIT3D JSON resource.
     * @param array $torrent
     * @return string
     */
    private function torrent_name($torrent): string
    {
        if (isset($torrent['attributes']['name'])) {
            return (string) $torrent['attributes']['name'];
        }
        if (isset($torrent['name'])) {
            return (string) $torrent['name'];
        }
        return '';
    }

    /**
     * Check whether a torrent name matches one of the requested language tags.
     * @param string $name
     * @param string $lang Comma-separated tags, e.g. "VFF,TRUEFRENCH,FRENCH".
     * @return bool
     */
    private function matches_lang(string $name, string $lang): bool
    {
        $name = strtolower($name);
        foreach (explode(',', strtolower($lang)) as $tag) {
            $tag = trim($tag);
            if ($tag !== '' && stripos($name, $tag) !== false) {
                return true;
            }
        }
        // MULTI releases satisfy any French-audio request.
        return stripos($name, 'multi') !== false;
    }
}
