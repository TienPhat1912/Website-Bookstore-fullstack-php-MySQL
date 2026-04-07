<?php

function admin_normalize_search_text(string $text): string
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

function admin_split_search_tokens(string $text): array
{
    if ($text === '') {
        return [];
    }

    $tokens = preg_split('/\s+/u', $text) ?: [];
    $tokens = array_filter($tokens, static fn(string $token): bool => $token !== '');

    return array_values(array_unique($tokens));
}

function admin_token_similarity_score(string $needle, string $candidate): int
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

function admin_ordered_token_bonus(array $queryTokens, array $candidateTokens): int
{
    if (count($queryTokens) < 2 || !$candidateTokens) {
        return 0;
    }

    $lastIndex = -1;
    $matched = 0;

    foreach ($queryTokens as $queryToken) {
        $found = false;
        for ($i = $lastIndex + 1, $total = count($candidateTokens); $i < $total; $i++) {
            if (admin_token_similarity_score($queryToken, $candidateTokens[$i]) >= 82) {
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

function admin_score_short_search_text(string $query, string $candidate): int
{
    if ($query === '' || $candidate === '') {
        return 0;
    }

    $queryCompact = str_replace(' ', '', $query);
    $candidateCompact = str_replace(' ', '', $candidate);

    if ($candidate === $query) {
        return 420;
    }

    if (str_starts_with($candidate, $query)) {
        return 320;
    }

    if (str_contains($candidate, $query)) {
        return 250;
    }

    if ($queryCompact !== '' && str_contains($candidateCompact, $queryCompact)) {
        return 220;
    }

    return 0;
}

function admin_score_search_text(string $query, array $queryTokens, string $candidate): int
{
    if ($query === '' || $candidate === '') {
        return 0;
    }

    $score = 0;
    $candidateCompact = str_replace(' ', '', $candidate);
    $queryCompact = str_replace(' ', '', $query);

    if ($candidate === $query) {
        $score += 1100;
    } elseif (str_starts_with($candidate, $query)) {
        $score += 860;
    } elseif (str_contains($candidate, $query)) {
        $score += 680;
    }

    if ($queryCompact !== '' && $queryCompact !== $query && str_contains($candidateCompact, $queryCompact)) {
        $score += 180;
    }

    $candidateTokens = admin_split_search_tokens($candidate);
    $tokenCount = count($queryTokens);
    $matchedTokens = 0;
    $strongMatches = 0;

    foreach ($queryTokens as $queryToken) {
        $bestTokenScore = 0;

        foreach ($candidateTokens as $candidateToken) {
            $bestTokenScore = max($bestTokenScore, admin_token_similarity_score($queryToken, $candidateToken));
            if ($bestTokenScore >= 150) {
                break;
            }
        }

        if ($bestTokenScore <= 0) {
            continue;
        }

        $matchedTokens++;
        $score += $bestTokenScore;

        if ($bestTokenScore >= 82) {
            $strongMatches++;
        }
    }

    $hasCompactPhraseMatch = $queryCompact !== '' && str_contains($candidateCompact, $queryCompact);
    if ($tokenCount > 1 && $matchedTokens < $tokenCount && !$hasCompactPhraseMatch) {
        return 0;
    }

    if ($tokenCount > 0) {
        $score += (int) round(($matchedTokens / $tokenCount) * 140);
    }

    if ($tokenCount > 1 && $matchedTokens === $tokenCount) {
        $score += 180;
    }

    if ($tokenCount > 1 && $strongMatches === $tokenCount) {
        $score += 80;
    }

    $score += admin_ordered_token_bonus($queryTokens, $candidateTokens);

    if ($score > 0) {
        $score -= min(30, max(0, strlen($candidateCompact) - strlen($queryCompact)) * 2);
    }

    return max($score, 0);
}

function admin_fuzzy_filter_rows(array $rows, string $query, callable $fieldExtractor, array $options = []): array
{
    $queryNorm = admin_normalize_search_text($query);
    if ($queryNorm === '') {
        return array_values($rows);
    }

    $queryCompact = str_replace(' ', '', $queryNorm);
    $isShortQuery = strlen($queryCompact) < 2;
    $queryTokens = admin_split_search_tokens($queryNorm);
    $minScore = (int) ($options['min_score'] ?? ($isShortQuery ? 1 : 130));
    $scoredRows = [];

    foreach ($rows as $index => $row) {
        $fieldSpecs = $fieldExtractor($row);
        if ($fieldSpecs === null || $fieldSpecs === false) {
            continue;
        }

        if (!is_array($fieldSpecs) || isset($fieldSpecs['value'])) {
            $fieldSpecs = [$fieldSpecs];
        }

        $combinedParts = [];
        $bestScore = 0;
        $supportScore = 0;

        foreach ($fieldSpecs as $fieldSpec) {
            if (is_array($fieldSpec)) {
                $value = (string) ($fieldSpec['value'] ?? '');
                $weight = (float) ($fieldSpec['weight'] ?? 1.0);
            } else {
                $value = (string) $fieldSpec;
                $weight = 1.0;
            }

            $fieldNorm = admin_normalize_search_text($value);
            if ($fieldNorm === '') {
                continue;
            }

            $combinedParts[] = $fieldNorm;
            $fieldScore = $isShortQuery
                ? admin_score_short_search_text($queryNorm, $fieldNorm)
                : admin_score_search_text($queryNorm, $queryTokens, $fieldNorm);

            if ($fieldScore <= 0) {
                continue;
            }

            $weightedScore = (int) round($fieldScore * $weight);
            $bestScore = max($bestScore, $weightedScore);
            $supportScore += (int) round($weightedScore * 0.22);
        }

        if (!$combinedParts) {
            continue;
        }

        $combinedNorm = implode(' ', $combinedParts);
        $combinedScore = $isShortQuery
            ? admin_score_short_search_text($queryNorm, $combinedNorm)
            : admin_score_search_text($queryNorm, $queryTokens, $combinedNorm);

        $rowScore = max($bestScore, (int) round($combinedScore * 0.72));
        $rowScore += max(0, min(180, $supportScore - (int) round($bestScore * 0.22)));

        if ((!$isShortQuery && $rowScore < $minScore) || ($isShortQuery && $rowScore <= 0)) {
            continue;
        }

        $row['_admin_search_score'] = $rowScore;
        $row['_admin_search_index'] = $index;
        $scoredRows[] = $row;
    }

    usort($scoredRows, static function (array $a, array $b): int {
        if ($a['_admin_search_score'] !== $b['_admin_search_score']) {
            return $b['_admin_search_score'] <=> $a['_admin_search_score'];
        }

        return $a['_admin_search_index'] <=> $b['_admin_search_index'];
    });

    if (!$isShortQuery && $scoredRows) {
        $relativeMinScore = max($minScore, (int) round($scoredRows[0]['_admin_search_score'] * 0.35));
        $scoredRows = array_values(array_filter(
            $scoredRows,
            static fn(array $row): bool => $row['_admin_search_score'] >= $relativeMinScore
        ));
    }

    return array_map(static function (array $row): array {
        unset($row['_admin_search_score'], $row['_admin_search_index']);
        return $row;
    }, $scoredRows);
}

function admin_paginate_rows(array $rows, int $page, int $perPage): array
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
