<?php

namespace App\Services\ResultPortal;

use App\Exceptions\Domain\ResultPortalException;
use DOMDocument;
use DOMXPath;

/**
 * Parses the HTML fragment the portal returns for one lookup.
 *
 * Built from real pages: a header table of `<th>label</th><td>value</td>`
 * rows, a subject table of `serial | code | title | letter | point` rows and
 * an outcome cell ("Promoted", "Imp.", optionally GPA / CGPA and the backlog
 * subjects). Columns are read by position inside each row, labels by text, so
 * a different subject list or semester does not matter. Anything that is
 * neither a result nor the "not verified" message is `Unrecognised` and fails
 * loudly instead of guessing.
 */
class DuResultParser
{
    protected const NOT_VERIFIED = 'This is not verifyed';

    /**
     * @throws ResultPortalException
     */
    public function parse(string $html): PortalPage
    {
        if (str_contains($html, self::NOT_VERIFIED)) {
            return new PortalPage(PortalPageStatus::NotVerified);
        }

        $xpath = $this->xpath($html);

        $meta = [];

        foreach ($xpath->query('//tr[th and td and not(.//table)]') ?: [] as $row) {
            $label = trim($row->getElementsByTagName('th')->item(0)?->textContent ?? '');
            $value = trim(preg_replace('/\s+/', ' ', $row->getElementsByTagName('td')->item(0)?->textContent ?? ''));

            if ($label !== '') {
                $meta[$label] = $value;
            }
        }

        $subjects = [];

        foreach ($xpath->query('//table//tr[count(td) >= 5]') ?: [] as $row) {
            $cells = [];

            foreach ($row->getElementsByTagName('td') as $cell) {
                $cells[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
            }

            if (preg_match('/^\d+$/', $cells[0]) !== 1 || $cells[1] === '') {
                continue;
            }

            $letter = $cells[3] === '' ? null : strtoupper($cells[3]);
            $point = is_numeric($cells[4]) ? (float) $cells[4] : null;

            $subjects[] = [
                'code' => $this->normaliseCode($cells[1]),
                'title' => $cells[2],
                'letter' => $letter,
                'point' => $point ?? ($letter === 'F' ? 0.0 : null),
            ];
        }

        if ($meta === [] || ! isset($meta['Registration']) || $subjects === []) {
            throw ResultPortalException::layoutUnknown();
        }

        $outcomeText = trim(preg_replace('/\s+/', ' ', $xpath->query('//tr[th/table]/td')->item(0)?->textContent ?? ''));

        return new PortalPage(
            PortalPageStatus::Found,
            $meta,
            $subjects,
            $this->outcomeOf($outcomeText),
            preg_match('/(?<![A-Za-z])GPA:\s*([\d.]+)/', $outcomeText, $m) === 1 ? (float) $m[1] : null,
            preg_match('/CGPA:\s*([\d.]+)/', $outcomeText, $m) === 1 ? (float) $m[1] : null,
            $this->backlogOf($outcomeText),
        );
    }

    /**
     * "CSE 1201", "CSE-1201", "cse_1201" → "CSE-1201".
     */
    public function normaliseCode(string $code): string
    {
        return strtoupper(preg_replace('/[\s\-_]+/', '-', trim($code)));
    }

    protected function outcomeOf(string $text): ?string
    {
        $outcome = trim(preg_replace('/\s*(?:C?GPA:.*|[A-Z]{2,5}[- ]\d{4}.*)$/', '', $text));

        return $outcome === '' ? null : mb_substr($outcome, 0, 60);
    }

    /**
     * @return list<string>
     */
    protected function backlogOf(string $text): array
    {
        $beforeGpa = preg_replace('/C?GPA:.*$/', '', $text);

        preg_match_all('/\b([A-Z]{2,5})[- ](\d{4})\b/', $beforeGpa, $matches, PREG_SET_ORDER);

        return array_values(array_unique(array_map(fn (array $m): string => "{$m[1]}-{$m[2]}", $matches)));
    }

    protected function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.preg_replace('/<br\s*\/?>/i', ' ', $html));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($dom);
    }
}
