<?php
declare(strict_types=1);

/** Public route names differ from the internal module prefix, preserving test data. */
return static function (\PDO $pdo, string $slug): ?array {
    if ($slug === 'download') {
        return ['dl2'];
    }
    if ($slug === 'download-catalog') {
        return ['dl2', 'catalog'];
    }
    if (preg_match('/^download-file-([1-9][0-9]*)$/D', $slug, $match) !== 1) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT 1 FROM dm2_files WHERE id = ? AND status = 'published'");
    $stmt->execute([(int) $match[1]]);
    return $stmt->fetchColumn() !== false ? ['dl2', 'file', $match[1]] : null;
};
