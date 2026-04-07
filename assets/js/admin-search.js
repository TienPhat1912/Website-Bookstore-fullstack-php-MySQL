(function () {
  function normalizeSearchText(text) {
    return String(text || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/đ/g, 'd')
      .replace(/[^a-z0-9]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function splitSearchTokens(text) {
    return Array.from(new Set(normalizeSearchText(text).split(' ').filter(Boolean)));
  }

  function tokenSimilarityScore(needle, candidate) {
    if (!needle || !candidate) {
      return 0;
    }

    if (needle === candidate) {
      return 160;
    }

    if (candidate.startsWith(needle) && needle.length >= 2) {
      return Math.max(125, 145 - Math.max(0, candidate.length - needle.length) * 4);
    }

    if (needle.length >= 3 && candidate.includes(needle)) {
      return 110;
    }

    if (candidate.length >= 3 && needle.length >= 4 && needle.includes(candidate)) {
      return 78;
    }

    var distance = levenshtein(needle, candidate);
    var maxLen = Math.max(needle.length, candidate.length);

    if (distance === 1) {
      if (maxLen <= 4) {
        return Math.abs(needle.length - candidate.length) === 1 &&
          needle[0] === candidate[0] &&
          needle.slice(-1) === candidate.slice(-1)
          ? 78
          : 0;
      }

      return 96;
    }

    if (distance === 2 && maxLen >= 6) {
      return 72;
    }

    var percent = similarityPercent(needle, candidate);
    if (maxLen >= 5 && Math.abs(needle.length - candidate.length) <= 2) {
      if (percent >= 86) {
        return 66;
      }
      if (percent >= 76) {
        return 54;
      }
    }

    return 0;
  }

  function orderedTokenBonus(queryTokens, candidateTokens) {
    if (queryTokens.length < 2 || !candidateTokens.length) {
      return 0;
    }

    var lastIndex = -1;
    var matched = 0;

    for (var q = 0; q < queryTokens.length; q++) {
      var found = false;
      for (var i = lastIndex + 1; i < candidateTokens.length; i++) {
        if (tokenSimilarityScore(queryTokens[q], candidateTokens[i]) >= 82) {
          lastIndex = i;
          matched++;
          found = true;
          break;
        }
      }

      if (!found) {
        break;
      }
    }

    if (matched === queryTokens.length) {
      return 130;
    }

    return matched >= 2 ? matched * 24 : 0;
  }

  function scoreShortSearchText(query, candidate) {
    if (!query || !candidate) {
      return 0;
    }

    var queryCompact = query.replace(/\s+/g, '');
    var candidateCompact = candidate.replace(/\s+/g, '');

    if (candidate === query) {
      return 420;
    }
    if (candidate.indexOf(query) === 0) {
      return 320;
    }
    if (candidate.indexOf(query) >= 0) {
      return 250;
    }
    if (queryCompact && candidateCompact.indexOf(queryCompact) >= 0) {
      return 220;
    }

    return 0;
  }

  function scoreSearchText(query, queryTokens, candidate) {
    if (!query || !candidate) {
      return 0;
    }

    var score = 0;
    var candidateCompact = candidate.replace(/\s+/g, '');
    var queryCompact = query.replace(/\s+/g, '');

    if (candidate === query) {
      score += 1100;
    } else if (candidate.indexOf(query) === 0) {
      score += 860;
    } else if (candidate.indexOf(query) >= 0) {
      score += 680;
    }

    if (queryCompact && queryCompact !== query && candidateCompact.indexOf(queryCompact) >= 0) {
      score += 180;
    }

    var candidateTokens = splitSearchTokens(candidate);
    var matchedTokens = 0;
    var strongMatches = 0;

    for (var q = 0; q < queryTokens.length; q++) {
      var bestTokenScore = 0;
      for (var c = 0; c < candidateTokens.length; c++) {
        bestTokenScore = Math.max(bestTokenScore, tokenSimilarityScore(queryTokens[q], candidateTokens[c]));
        if (bestTokenScore >= 150) {
          break;
        }
      }

      if (bestTokenScore <= 0) {
        continue;
      }

      matchedTokens++;
      score += bestTokenScore;

      if (bestTokenScore >= 82) {
        strongMatches++;
      }
    }

    var hasCompactPhraseMatch = queryCompact && candidateCompact.indexOf(queryCompact) >= 0;
    if (queryTokens.length > 1 && matchedTokens < queryTokens.length && !hasCompactPhraseMatch) {
      return 0;
    }

    if (queryTokens.length > 0) {
      score += Math.round((matchedTokens / queryTokens.length) * 140);
    }

    if (queryTokens.length > 1 && matchedTokens === queryTokens.length) {
      score += 180;
    }

    if (queryTokens.length > 1 && strongMatches === queryTokens.length) {
      score += 80;
    }

    score += orderedTokenBonus(queryTokens, candidateTokens);

    if (score > 0) {
      score -= Math.min(30, Math.max(0, candidateCompact.length - queryCompact.length) * 2);
    }

    return Math.max(score, 0);
  }

  function rankItems(items, query, projector, options) {
    var queryNorm = normalizeSearchText(query);
    var queryCompact = queryNorm.replace(/\s+/g, '');
    var isShortQuery = queryCompact.length < 2;
    var queryTokens = splitSearchTokens(queryNorm);
    var minScore = (options && options.minScore) || (isShortQuery ? 1 : 130);

    if (!queryNorm) {
      return items.slice();
    }

    var scored = items.reduce(function (acc, item, index) {
      var specs = projector(item);
      if (!Array.isArray(specs)) {
        specs = [specs];
      }

      var combinedParts = [];
      var bestScore = 0;
      var supportScore = 0;

      specs.forEach(function (spec) {
        var value = typeof spec === 'object' && spec !== null ? spec.value : spec;
        var weight = typeof spec === 'object' && spec !== null && spec.weight != null ? Number(spec.weight) : 1;
        var norm = normalizeSearchText(value);

        if (!norm) {
          return;
        }

        combinedParts.push(norm);
        var fieldScore = isShortQuery
          ? scoreShortSearchText(queryNorm, norm)
          : scoreSearchText(queryNorm, queryTokens, norm);

        if (fieldScore <= 0) {
          return;
        }

        var weightedScore = Math.round(fieldScore * weight);
        bestScore = Math.max(bestScore, weightedScore);
        supportScore += Math.round(weightedScore * 0.22);
      });

      if (!combinedParts.length) {
        return acc;
      }

      var combinedScore = isShortQuery
        ? scoreShortSearchText(queryNorm, combinedParts.join(' '))
        : scoreSearchText(queryNorm, queryTokens, combinedParts.join(' '));

      var rowScore = Math.max(bestScore, Math.round(combinedScore * 0.72));
      rowScore += Math.max(0, Math.min(180, supportScore - Math.round(bestScore * 0.22)));

      if ((!isShortQuery && rowScore < minScore) || (isShortQuery && rowScore <= 0)) {
        return acc;
      }

      acc.push({ item: item, score: rowScore, index: index });
      return acc;
    }, []);

    scored.sort(function (a, b) {
      if (a.score !== b.score) {
        return b.score - a.score;
      }
      return a.index - b.index;
    });

    if (!isShortQuery && scored.length) {
      var relativeMin = Math.max(minScore, Math.round(scored[0].score * 0.35));
      scored = scored.filter(function (entry) {
        return entry.score >= relativeMin;
      });
    }

    return scored.map(function (entry) {
      return entry.item;
    });
  }

  function levenshtein(a, b) {
    var matrix = [];
    var i;
    var j;

    for (i = 0; i <= b.length; i++) {
      matrix[i] = [i];
    }

    for (j = 0; j <= a.length; j++) {
      matrix[0][j] = j;
    }

    for (i = 1; i <= b.length; i++) {
      for (j = 1; j <= a.length; j++) {
        if (b.charAt(i - 1) === a.charAt(j - 1)) {
          matrix[i][j] = matrix[i - 1][j - 1];
        } else {
          matrix[i][j] = Math.min(
            matrix[i - 1][j - 1] + 1,
            matrix[i][j - 1] + 1,
            matrix[i - 1][j] + 1
          );
        }
      }
    }

    return matrix[b.length][a.length];
  }

  function similarityPercent(a, b) {
    if (!a && !b) {
      return 100;
    }

    var maxLen = Math.max(a.length, b.length) || 1;
    return ((maxLen - levenshtein(a, b)) / maxLen) * 100;
  }

  window.adminSearch = {
    normalizeText: normalizeSearchText,
    scoreText: function (query, text) {
      var queryNorm = normalizeSearchText(query);
      if (!queryNorm) {
        return 0;
      }
      var compact = queryNorm.replace(/\s+/g, '');
      return compact.length < 2
        ? scoreShortSearchText(queryNorm, normalizeSearchText(text))
        : scoreSearchText(queryNorm, splitSearchTokens(queryNorm), normalizeSearchText(text));
    },
    rankItems: rankItems
  };
})();
