<?php

use App\Services\Assistant\FeatureIndex;
use Database\Seeders\Testing\TestDataset as T;

beforeEach(function () {
    seedTestDataset();
    $this->index = app(FeatureIndex::class);
});

it('has an index entry with description and keywords for every menu item', function () {
    $metadata = config('feature_index.features');

    foreach ($this->index->menuClasses() as $class) {
        expect(array_key_exists($class, $metadata))->toBeTrue("Menu item {$class} has no entry in config/feature_index.php");
        expect($metadata[$class]['description'])->not->toBeEmpty("{$class} needs a description")
            ->and($metadata[$class]['keywords'])->not->toBeEmpty("{$class} needs keywords");
    }
});

it('indexes every menu route with its real url, menu path and shape', function () {
    $entries = $this->index->entries();

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry)->toHaveKeys(['id', 'title', 'description', 'menuPath', 'url', 'requiredPermission', 'keywords', 'steps'])
            ->and($entry['url'])->toStartWith('http')
            ->and($entry['title'])->not->toBeEmpty()
            ->and($entry['menuPath'])->not->toBeEmpty();
    }

    expect($entries->pluck('id')->unique()->count())->toBe($entries->count());
});

it('has no stale metadata for classes that are not in the menu', function () {
    $menu = $this->index->menuClasses();

    foreach (array_keys(config('feature_index.features')) as $class) {
        expect(in_array($class, $menu, true))->toBeTrue("{$class} is described but not in the menu");
    }
});

it('finds the course creation screen for a super admin with the real deep link and steps', function () {
    $result = $this->index->search(datasetUser(T::SUPER_ADMIN), 'Where do I create a course?');

    expect($result['features'][0]['title'])->toBe('Create Course')
        ->and($result['features'][0]['menuPath'])->toBe('Academic → Courses → New')
        ->and($result['features'][0]['url'])->toEndWith('/courses/create')
        ->and($result['features'][0]['steps'])->not->toBeEmpty()
        ->and($result['features'][0]['requiredPermission'])->toBe('course:create');
});

it('does not offer the course creation screen to a student and names the roles that can', function () {
    $result = $this->index->search(datasetUser(T::STUDENT_ELIGIBLE), 'where do I create a course');

    expect(collect($result['features'])->pluck('title')->all())->not->toContain('Create Course')
        ->and($result['restricted'][0]['title'])->toBe('Create Course')
        ->and($result['restricted'][0]['roles'])->toContain('super_admin', 'department_head');
});

it('finds the result page for a student and never shows a student the staff result entry', function () {
    $student = datasetUser(T::STUDENT_ELIGIBLE);

    $mine = $this->index->search($student, 'where are my results');
    expect($mine['features'][0]['title'])->toBe('Result')->and($mine['features'][0]['url'])->toEndWith('/results');

    $entry = $this->index->search($student, 'enter marks');
    expect(collect($entry['features'])->pluck('title')->all())->not->toContain('Result entry');

    $teacher = $this->index->search(datasetUser(T::TEACHER), 'enter marks');
    expect($teacher['features'][0]['title'])->toBe('Result entry');
});

it('returns only features the user can access', function () {
    foreach ([T::STUDENT_ELIGIBLE, T::TEACHER, T::LIBRARIAN, T::ADMIN_OFFICE] as $email) {
        $user = datasetUser($email);
        $accessible = $this->index->entriesFor($user)->pluck('id');
        $found = collect($this->index->search($user, 'clearance')['features'])->pluck('id');

        expect($found->diff($accessible)->all())->toBe([], "{$email} must not be offered inaccessible screens");
    }

    $student = collect($this->index->search(datasetUser(T::STUDENT_ELIGIBLE), 'clearance')['features'])->pluck('title')->all();
    expect($student)->toContain('Apply')->and($student)->not->toContain('Clearance desk');
});

it('tolerates small typos and stop words', function () {
    $result = $this->index->search(datasetUser(T::SUPER_ADMIN), 'how can I find the semestre list');

    expect(collect($result['features'])->pluck('title')->all())->toContain('Semesters');
});

it('returns nothing for empty or nonsense queries', function () {
    expect($this->index->search(datasetUser(T::SUPER_ADMIN), 'the a of')['features'])->toBe([])
        ->and($this->index->search(datasetUser(T::SUPER_ADMIN), 'zzzxqv')['features'])->toBe([]);
});

it('leaves the signed-in user and panel untouched after a search', function () {
    $this->actingAs(datasetUser(T::SUPER_ADMIN));

    $this->index->search(datasetUser(T::STUDENT_ELIGIBLE), 'results');

    expect(auth()->id())->toBe(datasetUser(T::SUPER_ADMIN)->id);
});
