<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class LessonService
{
    public const SEED_VERSION = '2026-07-19c';

    public function __construct(private PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function sectionsWithLessons(bool $publishedOnly = true): array
    {
        $sections = $this->pdo->query('SELECT * FROM lesson_sections ORDER BY sort_order ASC, id ASC')->fetchAll() ?: [];
        $out = [];
        foreach ($sections as $section) {
            $sql = 'SELECT * FROM lessons WHERE section_id = :sid';
            if ($publishedOnly) {
                $sql .= ' AND published = 1';
            }
            $sql .= ' ORDER BY sort_order ASC, id ASC';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['sid' => (int) $section['id']]);
            $lessons = $stmt->fetchAll() ?: [];
            if ($publishedOnly && $lessons === []) {
                continue;
            }
            $section['lessons'] = $lessons;
            $out[] = $section;
        }

        $sql = 'SELECT * FROM lessons WHERE section_id IS NULL';
        if ($publishedOnly) {
            $sql .= ' AND published = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $orphans = $this->pdo->query($sql)->fetchAll() ?: [];
        if ($orphans) {
            $out[] = [
                'id' => 0,
                'title' => 'Geral',
                'slug' => 'geral',
                'lessons' => $orphans,
            ];
        }

        return $out;
    }

    /**
     * Flat list of lessons in reading order.
     *
     * @return list<array{slug:string,title:string}>
     */
    public function flatPublishedPages(): array
    {
        return $this->flatPages(true);
    }

    /**
     * @return list<array{slug:string,title:string}>
     */
    public function flatPages(bool $publishedOnly = true): array
    {
        $flat = [];
        foreach ($this->sectionsWithLessons($publishedOnly) as $section) {
            foreach (($section['lessons'] ?? []) as $lesson) {
                $flat[] = [
                    'slug' => (string) $lesson['slug'],
                    'title' => (string) $lesson['title'],
                ];
            }
        }

        return $flat;
    }

    /**
     * @return array{prev: ?array{slug:string,title:string}, next: ?array{slug:string,title:string}}
     */
    public function neighbors(string $slug, bool $publishedOnly = true): array
    {
        $pages = $this->flatPages($publishedOnly);
        $prev = null;
        $next = null;
        foreach ($pages as $i => $page) {
            if ($page['slug'] !== $slug) {
                continue;
            }
            $prev = $i > 0 ? $pages[$i - 1] : null;
            $next = isset($pages[$i + 1]) ? $pages[$i + 1] : null;
            break;
        }

        return ['prev' => $prev, 'next' => $next];
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lessons WHERE slug = :s LIMIT 1');
        $stmt->execute(['s' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lessons WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findSectionBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lesson_sections WHERE slug = :s LIMIT 1');
        $stmt->execute(['s' => $slug]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findSectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lesson_sections WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        return $this->pdo->query(
            'SELECT l.*, s.title AS section_title FROM lessons l
             LEFT JOIN lesson_sections s ON s.id = l.section_id
             ORDER BY COALESCE(s.sort_order, 999), l.sort_order, l.id'
        )->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listSections(): array
    {
        return $this->pdo->query('SELECT * FROM lesson_sections ORDER BY sort_order, id')->fetchAll() ?: [];
    }

    public function upsertSection(?int $id, string $title, string $slug, int $sortOrder): void
    {
        $now = gmdate('c');
        if ($id) {
            $stmt = $this->pdo->prepare('UPDATE lesson_sections SET title = :t, slug = :s, sort_order = :o WHERE id = :id');
            $stmt->execute(['t' => $title, 's' => $slug, 'o' => $sortOrder, 'id' => $id]);

            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO lesson_sections (title, slug, sort_order, created_at) VALUES (:t, :s, :o, :c)'
        );
        $stmt->execute(['t' => $title, 's' => $slug, 'o' => $sortOrder, 'c' => $now]);
    }

    public function upsertSectionBySlug(string $title, string $slug, int $sortOrder): int
    {
        $existing = $this->findSectionBySlug($slug);
        if ($existing) {
            $this->upsertSection((int) $existing['id'], $title, $slug, $sortOrder);

            return (int) $existing['id'];
        }
        $this->upsertSection(null, $title, $slug, $sortOrder);

        return (int) $this->pdo->lastInsertId();
    }

    public function upsertLesson(
        ?int $id,
        ?int $sectionId,
        string $title,
        string $slug,
        string $body,
        int $sortOrder,
        bool $published,
        bool $docsPublic,
    ): void {
        $now = gmdate('c');
        if ($id) {
            $stmt = $this->pdo->prepare(
                'UPDATE lessons SET section_id = :sid, title = :t, slug = :s, body_markdown = :b, sort_order = :o,
                 published = :p, docs_public = :d, updated_at = :u WHERE id = :id'
            );
            $stmt->execute([
                'sid' => $sectionId,
                't' => $title,
                's' => $slug,
                'b' => $body,
                'o' => $sortOrder,
                'p' => $published ? 1 : 0,
                'd' => $docsPublic ? 1 : 0,
                'u' => $now,
                'id' => $id,
            ]);

            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO lessons (section_id, title, slug, body_markdown, sort_order, published, docs_public, created_at, updated_at)
             VALUES (:sid, :t, :s, :b, :o, :p, :d, :c, :u)'
        );
        $stmt->execute([
            'sid' => $sectionId,
            't' => $title,
            's' => $slug,
            'b' => $body,
            'o' => $sortOrder,
            'p' => $published ? 1 : 0,
            'd' => $docsPublic ? 1 : 0,
            'c' => $now,
            'u' => $now,
        ]);
    }

    public function upsertLessonBySlug(
        ?int $sectionId,
        string $title,
        string $slug,
        string $body,
        int $sortOrder,
        bool $published = true,
        bool $docsPublic = false,
    ): void {
        $existing = $this->findBySlug($slug);
        $this->upsertLesson(
            $existing ? (int) $existing['id'] : null,
            $sectionId,
            $title,
            $slug,
            $body,
            $sortOrder,
            $published,
            $docsPublic
        );
    }

    public function deleteLesson(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lessons WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function sectionLessonCount(int $sectionId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM lessons WHERE section_id = :id');
        $stmt->execute(['id' => $sectionId]);

        return (int) $stmt->fetchColumn();
    }

    public function deleteSection(int $id): bool
    {
        if ($this->sectionLessonCount($id) > 0) {
            return false;
        }
        $stmt = $this->pdo->prepare('DELETE FROM lesson_sections WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return true;
    }

    public function seedDefaults(bool $force = false): void
    {
        $settings = new SettingsStore($this->pdo);
        $current = $settings->get('docs_seed_version');
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();

        if (! $force && $current === self::SEED_VERSION && $count > 0) {
            return;
        }

        // First install with empty DB and no force: still seed.
        // Old version or force: upsert official slugs without deleting custom pages.
        $this->applySeedFromFile($force || $current !== self::SEED_VERSION || $count === 0);
        $settings->set('docs_seed_version', self::SEED_VERSION);
    }

    public function applySeedFromFile(bool $run = true): int
    {
        if (! $run) {
            return 0;
        }

        $path = dirname(__DIR__).'/content/docs-seed.php';
        if (! is_file($path)) {
            return 0;
        }

        /** @var list<array{title:string,slug:string,pages:list<array{title:string,slug:string,body:string}>}> $seed */
        $seed = require $path;
        $pages = 0;
        foreach ($seed as $sectionOrder => $section) {
            $sectionId = $this->upsertSectionBySlug(
                (string) $section['title'],
                (string) $section['slug'],
                $sectionOrder + 1
            );
            foreach (($section['pages'] ?? []) as $pageOrder => $page) {
                $this->upsertLessonBySlug(
                    $sectionId,
                    (string) $page['title'],
                    (string) $page['slug'],
                    (string) $page['body'],
                    $pageOrder + 1,
                    true,
                    false
                );
                $pages++;
            }
        }

        return $pages;
    }
}
