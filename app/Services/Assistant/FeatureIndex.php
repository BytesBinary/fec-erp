<?php

namespace App\Services\Assistant;

use App\Models\User;
use App\Support\Authorization\Authorizer;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * "Where do I do X?" index (spec §5.2). Generated from the Filament panel's
 * menu (pages and resources, with their real URLs), enriched with the
 * descriptions, keywords and steps of config/feature_index.php. Search is
 * keyword/fuzzy for now; the entry shape is ready for embeddings later.
 *
 * Entry: id, title, description, menuPath, url, requiredPermission,
 * keywords[], steps[] (+ class, kind for access checks).
 */
class FeatureIndex
{
    private const STOP_WORDS = ['a', 'an', 'the', 'i', 'me', 'my', 'we', 'you', 'to', 'do', 'does', 'how', 'where', 'what', 'which', 'can', 'could', 'would', 'should', 'is', 'are', 'am', 'it', 'of', 'in', 'on', 'for', 'and', 'or', 'find', 'go', 'open', 'see', 'show', 'want', 'need', 'please', 'new', 'add', 'make', 'get', 'page', 'menu', 'screen', 'look', 'check', 'view'];

    public function __construct(protected Authorizer $authorizer) {}

    protected function panel(): Panel
    {
        return Filament::getPanel('erp');
    }

    /**
     * Every menu item of the panel as an index entry (not filtered by user).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function entries(): Collection
    {
        $panel = $this->panel();
        $metadata = config('feature_index.features', []);
        $entries = collect();

        foreach ($panel->getPages() as $class) {
            if ($class::shouldRegisterNavigation()) {
                $entries->push($this->entry($class, 'page', $metadata[$class] ?? null, $this->safeUrl(fn () => $class::getUrl(panel: 'erp'))));
            }
        }

        foreach ($panel->getResources() as $class) {
            if (! $class::shouldRegisterNavigation()) {
                continue;
            }

            $resource = $metadata[$class] ?? null;
            $entries->push($this->entry($class, 'resource_index', $resource, $this->safeUrl(fn () => $class::getUrl('index', panel: 'erp'))));

            if (array_key_exists('create', $class::getPages())) {
                $create = $resource['create'] ?? null;
                $entries->push($this->entry($class, 'resource_create', $create, $this->safeUrl(fn () => $class::getUrl('create', panel: 'erp')), $create === null ? null : 'New'));
            }
        }

        return $entries->values();
    }

    /**
     * Menu classes the index must describe (used by the completeness test).
     *
     * @return list<class-string>
     */
    public function menuClasses(): array
    {
        $panel = $this->panel();
        $classes = [];

        foreach ([...$panel->getPages(), ...$panel->getResources()] as $class) {
            if ($class::shouldRegisterNavigation()) {
                $classes[] = $class;
            }
        }

        return array_values(array_diff($classes, config('feature_index.ignore', [])));
    }

    /**
     * Entries the user may open.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function entriesFor(User $user): Collection
    {
        return $this->asUser($user, fn (): Collection => $this->entries()->filter(fn (array $entry): bool => $this->accessible($entry))->values());
    }

    /**
     * Keyword/fuzzy search. `features` are what the user may open; `restricted`
     * are matches the user may not use, with the roles that can.
     *
     * @return array{features: list<array<string, mixed>>, restricted: list<array<string, mixed>>}
     */
    public function search(User $user, string $query, int $limit = 5): array
    {
        $tokens = $this->tokens($query);

        if ($tokens === []) {
            return ['features' => [], 'restricted' => []];
        }

        $all = $this->entries();
        $accessibleIds = $this->entriesFor($user)->pluck('id')->all();

        $scored = $all->map(fn (array $entry): array => $entry + ['score' => $this->score($entry, $tokens, mb_strtolower($query))])
            ->filter(fn (array $entry): bool => $entry['score'] > 0)
            ->sortByDesc('score')
            ->values();

        $best = (int) ($scored->first()['score'] ?? 0);

        $features = $scored->filter(fn (array $entry): bool => in_array($entry['id'], $accessibleIds, true) && $entry['score'] >= $best * 0.7)->take($limit)->map(fn (array $entry): array => $this->public($entry))->values()->all();
        $restricted = $scored->reject(fn (array $entry): bool => in_array($entry['id'], $accessibleIds, true))->take(3)->map(fn (array $entry): array => $this->public($entry) + ['roles' => $entry['requiredPermission'] ? $this->authorizer->rolesHolding($entry['requiredPermission']) : []])->values()->all();

        return ['features' => $features, 'restricted' => $restricted];
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    protected function entry(string $class, string $kind, ?array $metadata, ?string $url, ?string $suffix = null): array
    {
        $label = $class::getNavigationLabel();
        $group = $class::getNavigationGroup();
        $group = $group instanceof UnitEnum ? $group->name : $group;
        $path = collect([$group, $label, $suffix])->filter()->implode(' → ');

        return [
            'id' => Str::slug(Str::replace('\\', '-', Str::after($class, 'App\\Filament\\'))).($kind === 'resource_create' ? '-create' : ''),
            'title' => $kind === 'resource_create' ? 'Create '.Str::singular($label) : $label,
            'description' => $metadata['description'] ?? '',
            'menuPath' => $path,
            'url' => $url,
            'requiredPermission' => $metadata['permission'] ?? null,
            'keywords' => $metadata['keywords'] ?? [],
            'steps' => $metadata['steps'] ?? [],
            'class' => $class,
            'kind' => $kind,
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function accessible(array $entry): bool
    {
        $class = $entry['class'];

        return match ($entry['kind']) {
            'page' => $class::canAccess(),
            'resource_create' => $class::canCreate(),
            default => $class::canViewAny(),
        };
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return array<string, mixed>
     */
    protected function public(array $entry): array
    {
        return array_diff_key($entry, ['class' => 1, 'kind' => 1, 'score' => 1]);
    }

    /**
     * @return list<string>
     */
    protected function tokens(string $query): array
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word): bool => strlen($word) > 1 && ! in_array($word, self::STOP_WORDS, true))));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  list<string>  $tokens
     */
    protected function score(array $entry, array $tokens, string $query): int
    {
        $titleWords = preg_split('/[^a-z0-9]+/', mb_strtolower($entry['title'].' '.$entry['menuPath']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $keywordWords = collect($entry['keywords'])->flatMap(fn (string $keyword): array => preg_split('/[^a-z0-9]+/', mb_strtolower($keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [])->unique()->all();
        $description = mb_strtolower($entry['description']);
        $score = 0;

        foreach ($entry['keywords'] as $keyword) {
            if (str_contains($keyword, ' ') && str_contains($query, mb_strtolower($keyword))) {
                $score += 8;
            }
        }

        foreach ($tokens as $token) {
            $score += $this->tokenScore($token, $titleWords, 5);
            $score += $this->tokenScore($token, $keywordWords, 3);

            if (strlen($token) > 3 && str_contains($description, $token)) {
                $score += 1;
            }
        }

        return $score;
    }

    /**
     * @param  list<string>  $words
     */
    protected function tokenScore(string $token, array $words, int $weight): int
    {
        foreach ($words as $word) {
            if ($word === $token || Str::singular($word) === Str::singular($token)) {
                return $weight;
            }
        }

        foreach ($words as $word) {
            if (strlen($token) >= 5 && strlen($word) >= 5 && levenshtein($word, $token) <= (strlen($token) >= 7 ? 2 : 1)) {
                return max(1, $weight - 2);
            }
        }

        return 0;
    }

    protected function safeUrl(callable $callback): ?string
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Runs `$callback` with `$user` as the signed-in web user and the ERP panel
     * as the current panel, then restores the previous state.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function asUser(User $user, callable $callback): mixed
    {
        $guard = Auth::guard('web');
        $previous = $guard->user();
        $previousPanel = Filament::getCurrentPanel();

        $guard->setUser($user);
        Filament::setCurrentPanel($this->panel());

        try {
            return $callback();
        } finally {
            $previous !== null ? $guard->setUser($previous) : $guard->forgetUser();
            Filament::setCurrentPanel($previousPanel);
        }
    }
}
