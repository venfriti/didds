<?php

namespace Webkul\Analytics\Support;

class ReferrerClassifier
{
    /**
     * Ordered host-substring => label map. First match wins.
     *
     * @var array<string, string>
     */
    protected static array $knownHosts = [
        'google.' => 'Google / Organic',
        'bing.' => 'Bing / Organic',
        'yahoo.' => 'Yahoo / Organic',
        'duckduckgo.' => 'DuckDuckGo / Organic',
        'facebook.' => 'Facebook',
        'fb.' => 'Facebook',
        'instagram.' => 'Instagram',
        'twitter.' => 'Twitter / X',
        'x.com' => 'Twitter / X',
        't.co' => 'Twitter / X',
        'whatsapp.' => 'WhatsApp',
        'pinterest.' => 'Pinterest',
        'linkedin.' => 'LinkedIn',
        'tiktok.' => 'TikTok',
        'youtube.' => 'YouTube',
    ];

    /**
     * Classify a referrer into a human-readable source label.
     *
     * UTM source, when present, always takes precedence over the
     * referrer-derived classification. Same-site referrers (internal
     * navigation) and empty/missing referrers both resolve to null so
     * callers can decide how to treat "no external source" distinctly
     * from an actual classified source.
     */
    public static function classify(?string $referrer, ?string $currentHost, ?string $utmSource = null): ?string
    {
        if ($utmSource) {
            return ucfirst($utmSource);
        }

        if (! $referrer) {
            return 'Direct';
        }

        $referrerHost = self::parseHost($referrer);

        if (! $referrerHost) {
            return 'Direct';
        }

        if ($currentHost && self::isSameSite($referrerHost, $currentHost)) {
            return null;
        }

        foreach (self::$knownHosts as $needle => $label) {
            if (str_contains($referrerHost, $needle)) {
                return $label;
            }
        }

        return 'Referral: '.$referrerHost;
    }

    /**
     * Parse the host out of a referrer URL, or null if unparseable.
     */
    public static function parseHost(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        return strtolower(preg_replace('/^www\./', '', $host));
    }

    /**
     * Whether the referrer host matches the current site's host.
     */
    protected static function isSameSite(string $referrerHost, string $currentHost): bool
    {
        return $referrerHost === strtolower(preg_replace('/^www\./', '', $currentHost));
    }
}
