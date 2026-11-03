<?php
declare(strict_types=1);

use Portix\Media\MediaManager;
use Portix\Observability\EventLogger;

if (!$user->isAdmin() && !$user->isRedactor()) { throw new RuntimeException('Accès refusé.'); }
require_once __DIR__ . '/src/MediaManager.php';
$mediaManager = new MediaManager($database->connection(), dirname(__DIR__, 3));
if (($argument[2] ?? '') === 'api') {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !PortixSecurity::csrfIsValid()) { throw new RuntimeException('Jeton ou méthode invalide. Rechargez la page.', 403); }
        $operation = (string) ($_POST['operation'] ?? 'list');
        $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (in_array($operation, ['replace', 'delete', 'restore', 'analyse', 'inventory', 'recover', 'webp_candidates', 'webp_migrate'], true)) {
            if (!$user->isAdmin()) { throw new RuntimeException('Action réservée à l’administrateur.', 403); }
        }
        if (in_array($operation, ['replace', 'delete', 'restore', 'usages'], true) && $id === false) {
            throw new RuntimeException('Identifiant invalide.');
        }
        require_once __DIR__ . '/src/WebpMigration.php';
        $webp = new \Portix\Media\WebpMigration($database->connection(), dirname(__DIR__, 3));
        $result = match ($operation) {
            'webp_candidates' => $webp->candidates(),
            'webp_migrate' => $webp->migrate((int) $id),
            'list' => $mediaManager->listing((string) ($_POST['family'] ?? ''), (string) ($_POST['search'] ?? '')),
            'upload' => $mediaManager->upload($_FILES['file'] ?? [], $user->pseudo()),
            'usages' => $mediaManager->usages((int) $id),
            'inventory' => $mediaManager->inventory(),
            'analyse' => $mediaManager->analyse(),
            'replace' => $mediaManager->replace((int) $id, $_FILES['file'] ?? [], ($_POST['confirm_dimensions'] ?? '') === '1'),
            'restore' => $mediaManager->restore((int) $id),
            'delete' => $mediaManager->delete((int) $id, ($_POST['confirm_history'] ?? '') === '1'),
            'recover' => $mediaManager->recover(),
            default => throw new RuntimeException('Opération inconnue.'),
        };
        if (!in_array($operation, ['list', 'usages', 'analyse', 'webp_candidates'], true)) {
            (new EventLogger($database->connection()))->audit($user->pseudo(), 'media.' . $operation, 'media:' . (string) $id, [], $requestId);
        }
        echo json_encode(['ok' => true, 'result' => $result], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    } catch (Throwable $error) {
        http_response_code($error->getCode() === 403 ? 403 : 422);
        echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}
$template->set('media_admin', $user->isAdmin() ? '1' : '0');
$template->setSlot('main', __DIR__ . '/templates/index.tpl');
