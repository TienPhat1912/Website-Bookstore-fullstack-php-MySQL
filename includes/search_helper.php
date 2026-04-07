<?php

function normalize_search_text(string $text): string
{
    static $charMap = null;

    if ($charMap === null) {
        $charMap = array_merge(
            array_fill_keys([
                "\u{00E0}", "\u{00E1}", "\u{1EA1}", "\u{1EA3}", "\u{00E3}",
                "\u{00E2}", "\u{1EA7}", "\u{1EA5}", "\u{1EAD}", "\u{1EA9}", "\u{1EAB}",
                "\u{0103}", "\u{1EB1}", "\u{1EAF}", "\u{1EB7}", "\u{1EB3}", "\u{1EB5}",
            ], 'a'),
            array_fill_keys([
                "\u{00E8}", "\u{00E9}", "\u{1EB9}", "\u{1EBB}", "\u{1EBD}",
                "\u{00EA}", "\u{1EC1}", "\u{1EBF}", "\u{1EC7}", "\u{1EC3}", "\u{1EC5}",
            ], 'e'),
            array_fill_keys([
                "\u{00EC}", "\u{00ED}", "\u{1ECB}", "\u{1EC9}", "\u{0129}",
            ], 'i'),
            array_fill_keys([
                "\u{00F2}", "\u{00F3}", "\u{1ECD}", "\u{1ECF}", "\u{00F5}",
                "\u{00F4}", "\u{1ED3}", "\u{1ED1}", "\u{1ED9}", "\u{1ED5}", "\u{1ED7}",
                "\u{01A1}", "\u{1EDD}", "\u{1EDB}", "\u{1EE3}", "\u{1EDF}", "\u{1EE1}",
            ], 'o'),
            array_fill_keys([
                "\u{00F9}", "\u{00FA}", "\u{1EE5}", "\u{1EE7}", "\u{0169}",
                "\u{01B0}", "\u{1EEB}", "\u{1EE9}", "\u{1EF1}", "\u{1EED}", "\u{1EEF}",
            ], 'u'),
            array_fill_keys([
                "\u{1EF3}", "\u{00FD}", "\u{1EF5}", "\u{1EF7}", "\u{1EF9}",
            ], 'y'),
            ["\u{0111}" => 'd']
        );
    }

    $text = mb_strtolower(trim($text), 'UTF-8');
    $text = strtr($text, $charMap);
    $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);

    return trim($text);
}

function split_search_tokens(string $text): array
{
    if ($text === '') {
        return [];
    }

    $tokens = preg_split('/\s+/u', $text) ?: [];
    $tokens = array_filter($tokens, static fn(string $token): bool => $token !== '');

    return array_values(array_unique($tokens));
}

function token_similarity_score(string $needle, string $candidate): int
{
    if ($needle === '' || $candidate === '') {
        return 0;
    }

    if ($needle === $candidate) {
        return 160;
    }

    $needleLen = strlen($needle);
    $candidateLen = strlen($candidate);

    if ($needleLen >= 2 && str_starts_with($candidate, $needle)) {
        return max(125, 145 - max(0, $candidateLen - $needleLen) * 4);
    }

    if ($needleLen >= 3 && str_contains($candidate, $needle)) {
        return 110;
    }

    if ($candidateLen >= 3 && $needleLen >= 4 && str_contains($needle, $candidate)) {
        return 78;
    }

    $distance = levenshtein($needle, $candidate);
    $maxLen = max($needleLen, $candidateLen);

    if ($distance === 1) {
        if ($maxLen <= 4) {
            return abs($needleLen - $candidateLen) === 1
                && $needle[0] === $candidate[0]
                && substr($needle, -1) === substr($candidate, -1)
                ? 78
                : 0;
        }

        return 96;
    }

    if ($distance === 2 && $maxLen >= 6) {
        return 72;
    }

    if ($maxLen >= 5 && abs($needleLen - $candidateLen) <= 2) {
        similar_text($needle, $candidate, $percent);
        if ($percent >= 86) {
            return 66;
        }
        if ($percent >= 76) {
            return 54;
        }
    }

    return 0;
}

function ordered_token_bonus(array $queryTokens, array $candidateTokens): int
{
    if (count($queryTokens) < 2 || !$candidateTokens) {
        return 0;
    }

    $lastIndex = -1;
    $matched = 0;

    foreach ($queryTokens as $queryToken) {
        $found = false;
        for ($i = $lastIndex + 1, $total = count($candidateTokens); $i < $total; $i++) {
            if (token_similarity_score($queryToken, $candidateTokens[$i]) >= 82) {
                $lastIndex = $i;
                $matched++;
                $found = true;
                break;
            }
        }

        if (!$found) {
            break;
        }
    }

    if ($matched === count($queryTokens)) {
        return 130;
    }

    return $matched >= 2 ? $matched * 24 : 0;
}

function score_search_candidate(string $query, array $queryTokens, string $title, string $author): int
{
    if ($query === '') {
        return 0;
    }

    $score = 0;
    $titleCompact = str_replace(' ', '', $title);
    $authorCompact = str_replace(' ', '', $author);
    $queryCompact = str_replace(' ', '', $query);

    if ($title === $query) {
        $score += 1200;
    } elseif (str_starts_with($title, $query)) {
        $score += 900;
    } elseif (str_contains($title, $query)) {
        $score += 720;
    }

    if ($author !== '') {
        if ($author === $query) {
            $score += 560;
        } elseif (str_starts_with($author, $query)) {
            $score += 380;
        } elseif (str_contains($author, $query)) {
            $score += 250;
        }
    }

    if ($queryCompact !== '' && $queryCompact !== $query) {
        if (str_contains($titleCompact, $queryCompact)) {
            $score += 210;
        } elseif ($authorCompact !== '' && str_contains($authorCompact, $queryCompact)) {
            $score += 120;
        }
    }

    $titleTokens = split_search_tokens($title);
    $authorTokens = split_search_tokens($author);
    $tokenCount = count($queryTokens);
    $matchedTokens = 0;
    $strongMatches = 0;

    foreach ($queryTokens as $queryToken) {
        $bestTitle = 0;
        foreach ($titleTokens as $candidateToken) {
            $bestTitle = max($bestTitle, token_similarity_score($queryToken, $candidateToken));
            if ($bestTitle >= 150) {
                break;
            }
        }

        $bestAuthor = 0;
        foreach ($authorTokens as $candidateToken) {
            $bestAuthor = max($bestAuthor, token_similarity_score($queryToken, $candidateToken));
            if ($bestAuthor >= 150) {
                break;
            }
        }

        $bestTokenScore = max($bestTitle, (int) round($bestAuthor * 0.72));
        if ($bestTokenScore <= 0) {
            continue;
        }

        $matchedTokens++;
        $score += $bestTokenScore;

        if ($bestTokenScore >= 82) {
            $strongMatches++;
        }
    }

    $hasCompactPhraseMatch =
        ($queryCompact !== '' && str_contains($titleCompact, $queryCompact)) ||
        ($authorCompact !== '' && $queryCompact !== '' && str_contains($authorCompact, $queryCompact));

    if ($tokenCount > 1 && $matchedTokens < $tokenCount && !$hasCompactPhraseMatch) {
        return 0;
    }

    if ($tokenCount > 0) {
        $score += (int) round(($matchedTokens / $tokenCount) * 150);
    }

    if ($tokenCount > 1 && $matchedTokens === $tokenCount) {
        $score += 200;
    }

    if ($tokenCount > 1 && $strongMatches === $tokenCount) {
        $score += 90;
    }

    $score += ordered_token_bonus($queryTokens, $titleTokens);
    $score += (int) round(ordered_token_bonus($queryTokens, $authorTokens) * 0.55);

    if ($score > 0) {
        $score -= min(40, max(0, strlen($titleCompact) - strlen($queryCompact)) * 2);
    }

    return max($score, 0);
}

function rank_book_search_rows(array $rows, string $query, int $minScore = 140): array
{
    $queryNorm = normalize_search_text($query);
    if (strlen(str_replace(' ', '', $queryNorm)) < 2) {
        return [];
    }

    $queryTokens = split_search_tokens($queryNorm);
    $scoredRows = [];

    foreach ($rows as $row) {
        $titleNorm = normalize_search_text((string) ($row['ten'] ?? ''));
        $authorNorm = normalize_search_text((string) ($row['tac_gia'] ?? ''));
        $score = score_search_candidate($queryNorm, $queryTokens, $titleNorm, $authorNorm);

        if ($score < $minScore) {
            continue;
        }

        $row['_search_score'] = $score;
        $row['_title_norm'] = $titleNorm;
        $scoredRows[] = $row;
    }

    usort($scoredRows, static function (array $a, array $b): int {
        if ($a['_search_score'] !== $b['_search_score']) {
            return $b['_search_score'] <=> $a['_search_score'];
        }

        return strlen($a['_title_norm']) <=> strlen($b['_title_norm']);
    });

    if ($scoredRows) {
        $minAcceptedScore = max($minScore, (int) round($scoredRows[0]['_search_score'] * 0.40));
        $scoredRows = array_values(array_filter(
            $scoredRows,
            static fn(array $row): bool => $row['_search_score'] >= $minAcceptedScore
        ));
    }

    return $scoredRows;
}

function paginate_rows(array $rows, int $page, int $perPage): array
{
    $total = count($rows);
    $totalPage = max(1, (int) ceil($total / max(1, $perPage)));
    $page = max(1, min($page, $totalPage));
    $offset = ($page - 1) * $perPage;

    return [
        'items' => array_slice($rows, $offset, $perPage),
        'total' => $total,
        'total_page' => $totalPage,
        'page' => $page,
        'offset' => $offset,
    ];
}
