<?php

declare(strict_types=1);

namespace LicenseApi;

use PDO;

final class LessonService
{
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
            $section['lessons'] = $stmt->fetchAll() ?: [];
            $out[] = $section;
        }

        // Orphan lessons
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

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lessons WHERE slug = :s LIMIT 1');
        $stmt->execute(['s' => $slug]);
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

    public function deleteLesson(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM lessons WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function seedDefaults(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM lessons')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $this->upsertSection(null, 'Instalação', 'instalacao', 1);
        $sectionId = (int) $this->pdo->lastInsertId();

        $lessons = [
            [
                'slug' => 'requisitos',
                'title' => 'Requisitos',
                'order' => 1,
                'body' => "# Requisitos\n\n- PHP **8.3+**\n- MySQL (shared) ou Postgres (Docker)\n- Extensões: pdo, mbstring, openssl, curl, json\n- Permissão de escrita em `storage/` e `.env`\n- Cron a cada minuto apontando para `/cron?token=...`\n",
            ],
            [
                'slug' => 'install-shared',
                'title' => 'Instalação em hospedagem compartilhada',
                'order' => 2,
                'body' => "# Shared hosting (MySQL)\n\n1. Faça upload do pacote com `vendor/` e `public/build`\n2. Document root = pasta `public/`\n3. Abra `/install`\n4. Valide a **licença**, configure MySQL e a URL\n5. Configure o cron HTTP gerado no final\n6. Crie o primeiro admin em `/criar-admin`\n",
            ],
            [
                'slug' => 'install-docker',
                'title' => 'Instalação via Docker (VPS)',
                'order' => 3,
                'body' => "# Docker / VPS\n\n```bash\nsudo bash install.sh\n```\n\nAbra `http://IP:PORTA/docker-setup`, informe domínio + chave de licença e depois `/criar-admin`.\n",
            ],
            [
                'slug' => 'licenca',
                'title' => 'Como usar sua licença',
                'order' => 4,
                'body' => "# Licença\n\n- Cada chave vale para **1 instalação de produção**\n- `localhost` / `127.0.0.1` / `*.local` podem ser usados livremente para testes\n- Não compartilhe a chave — reuso em outro domínio será bloqueado\n",
            ],
            [
                'slug' => 'cron',
                'title' => 'Cron e filas',
                'order' => 5,
                'body' => "# Cron\n\nEm shared hosting, agende a cada minuto:\n\n`https://seudominio.com/cron?token=SEU_CRON_SECRET`\n\nIsso processa reconciliação, heartbeat da licença e a fila `database`.\n",
            ],
        ];

        foreach ($lessons as $lesson) {
            $this->upsertLesson(
                null,
                $sectionId,
                $lesson['title'],
                $lesson['slug'],
                $lesson['body'],
                $lesson['order'],
                true,
                false
            );
        }
    }
}
