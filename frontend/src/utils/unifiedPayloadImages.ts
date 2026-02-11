/**
 * Collect image URL candidates from unified_payload (or any nested object).
 * Used for FloorplanViewer; candidate paths are for debugging.
 * Only collects strings that look like http(s) URLs — no requests to donor domains.
 */

const URL_REGEX = /^https?:\/\//i;

/** Keys often used for image/gallery data in payloads */
const IMAGE_KEY_HINTS = new Set([
  'images',
  'gallery',
  'image',
  'img',
  'floor_plan',
  'floor_plan_url',
  'plan',
  'plan_url',
  'photo',
  'picture',
  'url',
  'src',
  'thumb',
  'thumbnail',
  'file',
]);

export interface UnifiedPayloadImageResult {
  urls: string[];
  candidatePaths: string[];
}

function walk(
  obj: unknown,
  path: string,
  urls: string[],
  candidatePaths: string[]
): void {
  if (obj == null) return;

  if (typeof obj === 'string') {
    if (URL_REGEX.test(obj)) {
      urls.push(obj);
      candidatePaths.push(path);
    }
    return;
  }

  if (Array.isArray(obj)) {
    obj.forEach((item, i) => walk(item, `${path}[${i}]`, urls, candidatePaths));
    return;
  }

  if (typeof obj === 'object') {
    for (const [key, value] of Object.entries(obj)) {
      const nextPath = path ? `${path}.${key}` : key;
      const hint = IMAGE_KEY_HINTS.has(key) && typeof value === 'string' && URL_REGEX.test(value);
      walk(value, nextPath, urls, candidatePaths);
      if (hint && path) candidatePaths.push(nextPath);
    }
  }
}

/**
 * Extract all http(s) URL strings and the paths where they were found.
 * Use urls for display; use candidatePaths for debugging (e.g. console in dev).
 */
export function getImageCandidatesFromUnified(payload: Record<string, unknown> | null | undefined): UnifiedPayloadImageResult {
  const urls: string[] = [];
  const candidatePaths: string[] = [];
  if (payload) walk(payload, 'unified_payload', urls, candidatePaths);
  return { urls, candidatePaths };
}
