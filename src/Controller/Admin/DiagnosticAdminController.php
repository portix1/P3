<?php
declare(strict_types=1);

namespace Portix\Controller\Admin;

use Portix\Diagnostics\DiagnosticRunner;
use Portix\Domain\UserAccount;
use Portix\Observability\EventLogger;
use Portix\Support\Translator;
use Portix\View\Template;
use RuntimeException;

final class DiagnosticAdminController
{
    public function __construct(
        private readonly DiagnosticRunner $runner,
        private readonly EventLogger $logger,
        private readonly Translator $translator,
        private readonly string $requestId,
        private readonly string $root
    ) {
    }

    public function handle(Template $template, UserAccount $actor): void
    {
        if (!$actor->isAdmin()) {
            http_response_code(403);
            $template->setSlot('main', './var/admin/templates/access-denied.tpl');
            return;
        }

        $environment = $this->runner->environment();
        $result = null;
        $mode = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $mode = strtolower(trim((string) ($_POST['diagnostic_mode'] ?? '')));
                if (!in_array($mode, ['quick', 'full'], true)) {
                    throw new RuntimeException('diagnostics_invalid_mode');
                }
                $result = $this->runner->run($mode);
                $this->persistSummary($result, $mode);
                $this->logger->audit(
                    $actor->pseudo(),
                    'diagnostics.run',
                    $mode,
                    ['total' => $result['total'], 'pass' => $result['pass'], 'fail' => $result['fail']],
                    $this->requestId
                );
            } catch (RuntimeException $error) {
                $template->set('diagnostics_message', $this->translator->exception($error));
            }
        }

        $template->setSlot('main', './var/admin/templates/diagnostics.tpl');
        $template->set('diagnostics_mode', $mode);
        $template->set('diagnostics_has_result', $result === null ? '0' : '1');
        $template->set('diagnostics_total', (string) ($result['total'] ?? 0));
        $template->set('diagnostics_pass', (string) ($result['pass'] ?? 0));
        $template->set('diagnostics_fail', (string) ($result['fail'] ?? 0));
        $template->set('diagnostics_report', (string) ($result['report'] ?? ''));
        $template->set('diagnostics_last_summary', $this->lastSummary());

        foreach ($environment['rows'] as $row) {
            $template->addRow('diagnosticenvironment', $row);
        }
        foreach (($result['rows'] ?? []) as $row) {
            $template->addRow('diagnosticrow', $row);
        }
    }

    /** @param array{total:int,pass:int,fail:int,warn:int} $result */
    private function persistSummary(array $result, string $mode): void
    {
        $file = $this->root . '/var/sys/diagnostics.json';
        $payload = [
            'run_at' => gmdate('c'),
            'version' => trim((string) @file_get_contents($this->root . '/VERSION')),
            'php' => PHP_VERSION,
            'mode' => $mode,
            'total' => $result['total'],
            'pass' => $result['pass'],
            'fail' => $result['fail'],
        ];
        @file_put_contents(
            $file,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            LOCK_EX
        );
    }

    private function lastSummary(): string
    {
        $file = $this->root . '/var/sys/diagnostics.json';
        if (!is_file($file)) {
            return $this->translator->text('diagnostics_never_run');
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            return $this->translator->text('diagnostics_never_run');
        }
        return sprintf(
            '%s — %s — %d/%d OK',
            (string) ($data['run_at'] ?? ''),
            strtoupper((string) ($data['mode'] ?? '')),
            (int) ($data['pass'] ?? 0),
            (int) ($data['total'] ?? 0)
        );
    }
}
