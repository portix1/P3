<?php
declare(strict_types=1);

namespace Portix\Controller\Admin;

use Portix\Domain\UserAccount;
use Portix\Observability\EventLogger;
use Portix\Repository\UserRepository;
use Portix\Support\Translator;
use RuntimeException;

final class UserAdminController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EventLogger $logger,
        private readonly Translator $translator,
        private readonly string $requestId
    ) {
    }

    /** @param list<string> $arguments */
    public function handle(\Portix\View\Template $template, array $arguments, UserAccount $actor): void
    {
        if (!$actor->isAdmin()) {
            http_response_code(403);
            $template->setSlot('main', './var/admin/templates/access-denied.tpl');
            return;
        }

        $action = strtolower((string) ($arguments[2] ?? 'list'));
        if ($action === '' || $action === 'list') {
            $this->listing($template, $actor);
            return;
        }
        if ($action === 'new') {
            $this->create($template, $actor);
            return;
        }
        if ($action === 'edit') {
            $this->edit($template, $arguments, $actor);
            return;
        }
        if ($action === 'permissions') {
            $pseudo = $this->pseudoFromRoute($arguments);
            $account = $this->users->find($pseudo);
            if ($account === null) { $this->notFound($template); return; }
            try {
                \PortixSecurity::requirePostWithCsrf();
                $permissions = \Portix\Domain\ArticlePermissions::DEFAULTS;
                $valid = array_map('intval', array_column($this->users->articleCategories(), 'id'));
                foreach ($permissions as $key => $default) {
                    if ($key === 'categories') {
                        $ids = isset($_POST['permissions_reset']) ? [] : ($_POST['permission_categories'] ?? []);
                        if (!is_array($ids)) { throw new RuntimeException('users_permissions_invalid'); }
                        $permissions[$key] = [];
                        foreach ($ids as $id) {
                            if (!is_scalar($id) || !ctype_digit((string)$id) || !in_array((int)$id, $valid, true)) { throw new RuntimeException('users_permissions_invalid'); }
                            $permissions[$key][] = (int)$id;
                        }
                        $permissions[$key] = array_values(array_unique($permissions[$key]));
                    } else { $permissions[$key] = isset($_POST['permission_' . $key]); }
                }
                if (!isset($_POST['permissions_reset'])) {
                    $publication = (string) ($_POST['permission_publication'] ?? '');
                    $permissions = \Portix\Domain\ArticlePermissions::withPublicationChoice($permissions, $publication);
                }
                if (isset($_POST['permissions_reset'])) { $permissions = []; }
                $this->users->saveArticlePermissions($pseudo, $permissions);
                if ($pseudo === $actor->pseudo()) { $_SESSION['auth_version'] = $this->users->find($pseudo)->authVersion(); }
                $this->logger->audit($actor->pseudo(), 'user.article_permissions', $pseudo, $permissions, $this->requestId);
                $this->redirect('Admin-users-edit-' . rawurlencode($pseudo) . '.html?status=updated');
            } catch (RuntimeException $error) {
                http_response_code(400);
                $this->accountForm($template, $this->draftFromAccount($account), $account, $this->translator->exception($error));
            }
            return;
        }
        if ($action === 'password') {
            $this->changePassword($template, $arguments, $actor);
            return;
        }
        if ($action === 'delete') {
            $this->delete($template, $arguments, $actor);
            return;
        }

        $this->notFound($template);
    }

    private function listing(\Portix\View\Template $template, UserAccount $actor, string $error = ''): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $role = strtolower(trim((string) ($_GET['role'] ?? 'all')));
        if (!in_array($role, ['all', 'admin', 'redactor'], true)) {
            $role = 'all';
        }
        if ($this->length($query) > 100) {
            $query = $this->slice($query, 100);
        }
        $rows = $this->users->searchForAdministration($query, $role);
        $message = $error !== '' ? $error : $this->statusMessage((string) ($_GET['status'] ?? ''));

        $template->setSlot('main', './var/admin/templates/users-list.tpl');
        $template->set('users_message', $message);
        $template->set('users_query', $query);
        $template->set('users_total', (string) $this->users->count());
        $template->set('users_admin_count', (string) $this->users->countAdmins());
        $template->set('users_redactor_count', (string) $this->users->countRedactors());
        $template->set('users_role_filter', $role);
        $template->set('users_filter_all_active', $role === 'all' ? '1' : '0');
        $template->set('users_filter_admin_active', $role === 'admin' ? '1' : '0');
        $template->set('users_filter_redactor_active', $role === 'redactor' ? '1' : '0');
        $template->set('users_filter_all_current', $role === 'all' ? 'is-current' : '');
        $template->set('users_filter_admin_current', $role === 'admin' ? 'is-current' : '');
        $template->set('users_filter_redactor_current', $role === 'redactor' ? 'is-current' : '');
        $template->set('users_list_title', $this->translator->text(match ($role) {
            'admin' => 'users_list_administrators',
            'redactor' => 'users_list_redactors',
            default => 'users_list_all',
        }));
        $template->set(
            'users_clear_search_url',
            $role === 'all' ? 'Admin-users.html' : 'Admin-users.html?role=' . $role
        );
        $template->set('users_has_items', $rows === [] ? '0' : '1');
        $template->set('users_has_query', $query === '' ? '0' : '1');

        foreach ($rows as $row) {
            $pseudo = (string) $row['pseudo'];
            $firstName = trim((string) ($row['first_name'] ?? ''));
            $lastName = trim((string) ($row['last_name'] ?? ''));
            $displayName = trim($firstName . ' ' . $lastName);
            $email = trim((string) ($row['email'] ?? ''));
            $template->addRow('userrow', [
                'pseudo' => $pseudo,
                'pseudo_url' => rawurlencode($pseudo),
                'display_name' => $displayName !== '' ? $displayName : $this->translator->text('users_name_missing'),
                'email' => $email !== '' ? $email : $this->translator->text('users_no_email'),
                'initials' => $this->initials($firstName, $lastName, $pseudo),
                'is_admin' => !empty($row['is_admin']) ? '1' : '0',
                'is_redactor' => !empty($row['is_redactor']) ? '1' : '0',
                'is_self' => $pseudo === $actor->pseudo() ? '1' : '0',
                'can_delete' => $pseudo === $actor->pseudo() ? '0' : '1',
            ]);
        }
    }

    private function create(\Portix\View\Template $template, UserAccount $actor): void
    {
        $draft = [
            'pseudo' => '', 'first_name' => '', 'last_name' => '', 'email' => '',
            'is_admin' => false, 'is_redactor' => true,
        ];
        $message = '';

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $draft = $this->accountInput(true);
                $password = $this->validatedPassword();
                if ($this->users->findCaseInsensitive((string) $draft['pseudo']) !== null) {
                    throw new RuntimeException('users_pseudo_exists');
                }
                $this->users->create(
                    (string) $draft['pseudo'],
                    password_hash($password, PASSWORD_DEFAULT),
                    (string) $draft['email'],
                    (string) $draft['last_name'],
                    (string) $draft['first_name'],
                    (bool) $draft['is_admin'],
                    (bool) $draft['is_redactor']
                );
                $this->logger->audit(
                    $actor->pseudo(),
                    'user.create',
                    (string) $draft['pseudo'],
                    ['admin' => (bool) $draft['is_admin'], 'redactor' => (bool) $draft['is_redactor']],
                    $this->requestId
                );
                $this->redirect('Admin-users.html?status=created');
            } catch (RuntimeException $error) {
                $message = $this->translator->exception($error);
                $draft = $this->draftFromPost($draft, true);
            }
        }

        $this->accountForm($template, $draft, null, $message);
    }

    /** @param list<string> $arguments */
    private function edit(\Portix\View\Template $template, array $arguments, UserAccount $actor): void
    {
        try {
            $pseudo = $this->pseudoFromRoute($arguments);
        } catch (RuntimeException) {
            $this->notFound($template);
            return;
        }
        $account = $this->users->find($pseudo);
        if ($account === null) {
            $this->notFound($template);
            return;
        }

        $draft = $this->draftFromAccount($account);
        $message = $this->statusMessage((string) ($_GET['status'] ?? ''));
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            try {
                \PortixSecurity::requirePostWithCsrf();
                $draft = $this->accountInput(false);
                $draft['pseudo'] = $pseudo;
                if ($pseudo === $actor->pseudo() && !(bool) $draft['is_admin']) {
                    throw new RuntimeException('users_self_admin');
                }
                $this->users->updateAccount(
                    $pseudo,
                    (string) $draft['last_name'],
                    (string) $draft['first_name'],
                    (string) $draft['email'],
                    (bool) $draft['is_admin'],
                    (bool) $draft['is_redactor']
                );
                $this->logger->audit(
                    $actor->pseudo(),
                    'user.update',
                    $pseudo,
                    ['admin' => (bool) $draft['is_admin'], 'redactor' => (bool) $draft['is_redactor']],
                    $this->requestId
                );
                $this->redirect('Admin-users-edit-' . rawurlencode($pseudo) . '.html?status=updated');
            } catch (RuntimeException $error) {
                $message = $this->translator->exception($error);
                $draft = $this->draftFromPost($draft, false);
            }
        }

        $this->accountForm($template, $draft, $account, $message);
    }

    /** @param list<string> $arguments */
    private function changePassword(\Portix\View\Template $template, array $arguments, UserAccount $actor): void
    {
        try {
            $pseudo = $this->pseudoFromRoute($arguments);
        } catch (RuntimeException) {
            $this->notFound($template);
            return;
        }
        $account = $this->users->find($pseudo);
        if ($account === null) {
            $this->notFound($template);
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            $this->accountForm(
                $template,
                $this->draftFromAccount($account),
                $account,
                $this->translator->text('security_post_required')
            );
            return;
        }

        try {
            \PortixSecurity::requirePostWithCsrf();
            $password = $this->validatedPassword();
            $this->users->updatePassword($pseudo, password_hash($password, PASSWORD_DEFAULT));
            $updated = $this->users->find($pseudo);
            if ($pseudo === $actor->pseudo() && $updated !== null) {
                session_regenerate_id(true);
                $_SESSION['auth_version'] = $updated->authVersion();
            }
            $this->logger->audit($actor->pseudo(), 'user.password_reset', $pseudo, [], $this->requestId);
            $this->redirect('Admin-users-edit-' . rawurlencode($pseudo) . '.html?status=password');
        } catch (RuntimeException $error) {
            $this->accountForm(
                $template,
                $this->draftFromAccount($account),
                $account,
                $this->translator->exception($error)
            );
        }
    }

    /** @param list<string> $arguments */
    private function delete(\Portix\View\Template $template, array $arguments, UserAccount $actor): void
    {
        try {
            \PortixSecurity::requirePostWithCsrf();
            $pseudo = $this->pseudoFromRoute($arguments);
            if ($pseudo === $actor->pseudo()) {
                throw new RuntimeException('users_self_delete');
            }
            $account = $this->users->find($pseudo);
            if ($account === null) {
                throw new RuntimeException('users_not_found');
            }
            if ($account->isAdmin() && $this->users->countAdmins() <= 1) {
                throw new RuntimeException('users_last_admin');
            }
            $this->users->delete($pseudo);
            $this->logger->audit($actor->pseudo(), 'user.delete', $pseudo, [], $this->requestId);
            $this->redirect('Admin-users.html?status=deleted');
        } catch (RuntimeException $error) {
            http_response_code(400);
            $this->listing($template, $actor, $this->translator->exception($error));
        }
    }

    /** @param array<string, mixed> $draft */
    private function accountForm(
        \Portix\View\Template $template,
        array $draft,
        ?UserAccount $account,
        string $message
    ): void {
        $isNew = $account === null;
        $pseudo = $isNew ? (string) $draft['pseudo'] : $account->pseudo();
        $encodedPseudo = rawurlencode($pseudo);
        $template->setSlot('main', './var/admin/templates/users-form.tpl');
        $template->set('users_message', $message);
        $template->set('users_is_new', $isNew ? '1' : '0');
        $template->set('users_is_edit', $isNew ? '0' : '1');
        $template->set('users_can_delete', !$isNew && $pseudo !== (string) ($_SESSION['user_nick'] ?? '') ? '1' : '0');
        $template->set('users_is_self', !$isNew && $pseudo === (string) ($_SESSION['user_nick'] ?? '') ? '1' : '0');
        $template->set('users_form_title', $this->translator->text($isNew ? 'users_form_new' : 'users_form_edit'));
        $template->set('users_form_intro', $this->translator->text($isNew ? 'users_form_new_help' : 'users_form_edit_help'));
        $template->set('users_form_action', $isNew ? 'Admin-users-new.html' : 'Admin-users-edit-' . $encodedPseudo . '.html');
        $template->set('users_password_action', 'Admin-users-password-' . $encodedPseudo . '.html');
        $template->set('users_delete_action', 'Admin-users-delete-' . $encodedPseudo . '.html');
        $template->set('users_pseudo_value', (string) $draft['pseudo']);
        $template->set('users_first_name_value', (string) $draft['first_name']);
        $template->set('users_last_name_value', (string) $draft['last_name']);
        $template->set('users_email_value', (string) $draft['email']);
        $template->set('users_admin_checked', !empty($draft['is_admin']) ? '1' : '0');
        $template->set('users_redactor_checked', !empty($draft['is_redactor']) ? '1' : '0');
        $permissions = $account?->articlePermissions() ?? \Portix\Domain\ArticlePermissions::DEFAULTS;
        $template->set('users_permissions_action', 'Admin-users-permissions-' . $encodedPseudo . '.html');
        $template->set('users_publication_direct', !empty($permissions['publish']) && !empty($permissions['edit_published']) ? '1' : '0');
        foreach ($permissions as $key => $value) {
            if ($key !== 'categories') { $template->set('permission_' . $key, $value ? '1' : '0'); }
        }
        foreach ($this->users->articleCategories() as $category) {
            $template->addRow('permissioncategory', ['id' => (string)$category['id'], 'name' => (string)$category['name'], 'checked' => in_array((int)$category['id'], $permissions['categories'], true) ? '1' : '0']);
        }

    }

    /** @return array{pseudo:string,first_name:string,last_name:string,email:string,is_admin:bool,is_redactor:bool} */
    private function accountInput(bool $includePseudo): array
    {
        $pseudo = $includePseudo ? trim((string) ($_POST['pseudo'] ?? '')) : '';
        $firstName = trim(strip_tags((string) ($_POST['first_name'] ?? '')));
        $lastName = trim(strip_tags((string) ($_POST['last_name'] ?? '')));
        $email = trim((string) ($_POST['email'] ?? ''));
        $admin = isset($_POST['is_admin']);
        $redactor = isset($_POST['is_redactor']);

        if (($includePseudo && preg_match('/^[A-Za-z0-9_.-]{2,64}$/', $pseudo) !== 1)
            || $this->length($firstName) > 100
            || $this->length($lastName) > 100
            || $this->length($email) > 254
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('users_invalid_identity');
        }
        if (!$admin && !$redactor) {
            throw new RuntimeException('users_role_required');
        }

        return [
            'pseudo' => $pseudo,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'is_admin' => $admin,
            'is_redactor' => $redactor,
        ];
    }

    private function validatedPassword(): string
    {
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirm'] ?? '');
        if (strlen($password) < 12 || strlen($password) > 4096 || !hash_equals($password, $confirmation)) {
            throw new RuntimeException('users_password_requirements');
        }
        return $password;
    }

    /** @param array<string, mixed> $fallback @return array<string, mixed> */
    private function draftFromPost(array $fallback, bool $includePseudo): array
    {
        return [
            'pseudo' => $includePseudo ? trim((string) ($_POST['pseudo'] ?? $fallback['pseudo'])) : (string) $fallback['pseudo'],
            'first_name' => trim(strip_tags((string) ($_POST['first_name'] ?? $fallback['first_name']))),
            'last_name' => trim(strip_tags((string) ($_POST['last_name'] ?? $fallback['last_name']))),
            'email' => trim((string) ($_POST['email'] ?? $fallback['email'])),
            'is_admin' => isset($_POST['is_admin']),
            'is_redactor' => isset($_POST['is_redactor']),
        ];
    }

    /** @return array{pseudo:string,first_name:string,last_name:string,email:string,is_admin:bool,is_redactor:bool} */
    private function draftFromAccount(UserAccount $account): array
    {
        return [
            'pseudo' => $account->pseudo(),
            'first_name' => $account->firstName(),
            'last_name' => $account->lastName(),
            'email' => $account->email(),
            'is_admin' => $account->isAdmin(),
            'is_redactor' => $account->isRedactor(),
        ];
    }

    /** @param list<string> $arguments */
    private function pseudoFromRoute(array $arguments): string
    {
        $pseudo = trim(implode('-', array_slice($arguments, 3)));
        if (preg_match('/^[A-Za-z0-9_.-]{2,64}$/', $pseudo) !== 1) {
            throw new RuntimeException('users_not_found');
        }
        return $pseudo;
    }

    private function statusMessage(string $status): string
    {
        $key = match ($status) {
            'created' => 'users_created',
            'updated' => 'users_updated',
            'password' => 'users_password_changed',
            'deleted' => 'users_deleted',
            default => '',
        };
        return $key === '' ? '' : $this->translator->text($key);
    }

    private function initials(string $firstName, string $lastName, string $pseudo): string
    {
        $source = [];
        if ($firstName !== '') {
            $source[] = $firstName;
        }
        if ($lastName !== '') {
            $source[] = $lastName;
        }
        if ($source === []) {
            $source[] = $pseudo;
        }
        $initials = '';
        foreach (array_slice($source, 0, 2) as $part) {
            $initials .= $this->slice($part, 1);
        }
        return function_exists('mb_strtoupper') ? mb_strtoupper($initials, 'UTF-8') : strtoupper($initials);
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private function slice(string $value, int $length): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    }

    private function notFound(\Portix\View\Template $template): void
    {
        http_response_code(404);
        $template->setSlot('main', './var/admin/templates/not-found.tpl');
    }

    private function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }
}
