<?php
declare(strict_types=1);

namespace Portix\Repository;

use Portix\Infrastructure\Database;

final class RepositoryRegistry
{
    private UserRepository $users;
    private ModuleRepository $modules;
    private MenuRepository $menu;
    private NewsRepository $news;
    private DownloadRepository $downloads;
    private InsightRepository $insights;
    private ContactRepository $contact;
    private ContentSafetyRepository $contentSafety;

    public function __construct(Database $database)
    {
        $pdo = $database->connection();
        $this->users = new UserRepository($pdo);
        $this->modules = new ModuleRepository($pdo);
        $this->menu = new MenuRepository($pdo, $database);
        $this->news = new NewsRepository($pdo);
        $this->downloads = new DownloadRepository($pdo);
        $this->insights = new InsightRepository($pdo);
        $this->contact = new ContactRepository($pdo);
        $this->contentSafety = new ContentSafetyRepository($pdo);
    }

    public function users(): UserRepository { return $this->users; }
    public function modules(): ModuleRepository { return $this->modules; }
    public function menu(): MenuRepository { return $this->menu; }
    public function news(): NewsRepository { return $this->news; }
    public function downloads(): DownloadRepository { return $this->downloads; }
    public function insights(): InsightRepository { return $this->insights; }
    public function contact(): ContactRepository { return $this->contact; }
    public function contentSafety(): ContentSafetyRepository { return $this->contentSafety; }
}
