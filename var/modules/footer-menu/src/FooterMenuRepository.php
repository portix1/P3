<?php
declare(strict_types=1);

namespace PortixModule\FooterMenu;

use PDO;
use RuntimeException;

final class FooterMenuRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function ensureDefaults(): void
    {
        $hasSetting = $this->pdo->query(
            "SELECT 1 FROM footer_menu_settings WHERE setting_key = 'column_count' LIMIT 1"
        )->fetchColumn() !== false;
        if (!$hasSetting) {
            $statement = $this->pdo->prepare(
                'INSERT INTO footer_menu_settings (setting_key, setting_value) VALUES (:key, :value)'
            );
            $statement->execute(['key' => 'column_count', 'value' => '2']);
        }

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM footer_menu_columns')->fetchColumn();
        if ($count === 0) {
            $statement = $this->pdo->prepare(
                'INSERT INTO footer_menu_columns (title, position) VALUES (:title, :position)'
            );
            foreach ([
                1 => 'Navigation',
                2 => 'Contenu',
                3 => 'Informations',
            ] as $position => $title) {
                $statement->execute(['title' => $title, 'position' => $position]);
            }
        }
    }

    public function columnCount(): int
    {
        $statement = $this->pdo->prepare(
            "SELECT setting_value FROM footer_menu_settings WHERE setting_key = 'column_count'"
        );
        $statement->execute();
        $value = (int) $statement->fetchColumn();
        return in_array($value, [2, 3], true) ? $value : 3;
    }

    public function setColumnCount(int $count): void
    {
        if (!in_array($count, [2, 3], true)) {
            throw new RuntimeException('footer_invalid_column_count');
        }
        $statement = $this->pdo->prepare(
            "INSERT INTO footer_menu_settings (setting_key, setting_value) VALUES ('column_count', :value) "
            . 'ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
        );
        $statement->execute(['value' => (string) $count]);
    }

    /** @return list<array<string, mixed>> */
    public function columns(): array
    {
        return $this->pdo->query(
            'SELECT id, title, position FROM footer_menu_columns ORDER BY position, id LIMIT 3'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function publicColumns(): array
    {
        $count = $this->columnCount();
        $statement = $this->pdo->prepare(
            'SELECT id, title, position FROM footer_menu_columns ORDER BY position, id LIMIT :limit'
        );
        $statement->bindValue(':limit', $count, PDO::PARAM_INT);
        $statement->execute();
        return $statement->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function links(): array
    {
        return $this->pdo->query(
            'SELECT l.*, c.title AS column_title, c.position AS column_position '
            . 'FROM footer_menu_links l JOIN footer_menu_columns c ON c.id = l.column_id '
            . 'ORDER BY c.position, l.position, l.id'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function publicLinksForColumn(int $columnId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, label, url, position, target_blank FROM footer_menu_links '
            . 'WHERE column_id = :column_id AND enabled = 1 ORDER BY position, id'
        );
        $statement->execute(['column_id' => $columnId]);
        return $statement->fetchAll();
    }

    public function updateColumn(int $id, string $title): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE footer_menu_columns SET title = :title WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'title' => $title]);
    }

    /** @param array<string, mixed> $values */
    public function updateLink(int $id, array $values): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE footer_menu_links SET column_id = :column_id, label = :label, url = :url, '
            . 'position = :position, target_blank = :target_blank, enabled = :enabled WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'column_id' => $values['column_id'],
            'label' => $values['label'],
            'url' => $values['url'],
            'position' => $values['position'],
            'target_blank' => $values['target_blank'],
            'enabled' => $values['enabled'],
        ]);
    }

    /** @param array<string, mixed> $values */
    public function createLink(array $values): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO footer_menu_links '
            . '(column_id, label, url, position, target_blank, enabled) '
            . 'VALUES (:column_id, :label, :url, :position, :target_blank, :enabled)'
        );
        $statement->execute([
            'column_id' => $values['column_id'],
            'label' => $values['label'],
            'url' => $values['url'],
            'position' => $values['position'],
            'target_blank' => $values['target_blank'],
            'enabled' => $values['enabled'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteLink(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM footer_menu_links WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    /** @return array<int, true> */
    public function columnIdMap(): array
    {
        $map = [];
        foreach ($this->columns() as $column) {
            $map[(int) $column['id']] = true;
        }
        return $map;
    }

    /** @return array<int, true> */
    public function linkIdMap(): array
    {
        $map = [];
        foreach ($this->links() as $link) {
            $map[(int) $link['id']] = true;
        }
        return $map;
    }
}
