<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/src/SearchRepository.php';
use PortixModule\Search\SearchRepository;
function typoCheck(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } echo 'PASS ' . $message . PHP_EOL; }
$source = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$source->exec('CREATE TABLE article_categories (id INTEGER PRIMARY KEY,name TEXT)');
$source->exec('CREATE TABLE articles (id INTEGER PRIMARY KEY,category_id INTEGER,title TEXT,slug TEXT,summary TEXT,body TEXT,status TEXT,updated_at TEXT)');
$source->exec('CREATE TABLE news (id INTEGER PRIMARY KEY,title TEXT,slug TEXT,intro TEXT,body TEXT,status TEXT,updated_at TEXT)');
$source->exec("INSERT INTO article_categories VALUES (1,'Guitares')");
$q=$source->prepare('INSERT INTO articles VALUES (?,?,?,?,?,?,?,?)');
$q->execute([1,1,'Fender Stratocaster','fender','Les mécaniques','<p>Une guitare Fender Stratocaster et ses mécaniques.</p>','published','2026-10-16']);
$q->execute([2,1,'Martin','martin','Une acoustique','<p>Une Martin acoustique.</p>','published','2026-10-16']);
$q->execute([3,1,'Qwertyuiop','secret','','<p>Confidentiel.</p>','draft','2026-10-16']);
$source->exec("INSERT INTO news VALUES (1,'Fender annonce','annonce','','<p>Fender présente une guitare.</p>','published','2026-10-16')");
foreach ([false,true] as $fallback) {
    $index=sys_get_temp_dir().'/p3-typos-'.bin2hex(random_bytes(8)).'.sqlite';
    $repository=new SearchRepository($source,$index);
    if($fallback){(new ReflectionProperty(SearchRepository::class,'fts5Available'))->setValue($repository,false);}
    else {typoCheck($repository->fts5Available(),'FTS5 available');}
    foreach(['fendar','fendre','fendr','fendeer','fender strtaocaster','mecaniques'] as $query){
        $results=$repository->search($query,'article');
        typoCheck(count($results)===1 && $results[0]['source_id']==='1',($fallback?'fallback ':'FTS5 ').$query.' finds published Fender article');
    }
    $results=$repository->search('fendar','news');typoCheck(count($results)===1 && $results[0]['kind']==='news','news filter kept');
    $repository->search('Fender');typoCheck($repository->suggestedQuery()==='','exact search takes priority and clears suggestion');
    typoCheck($repository->search('zxqvbnm')===[],'unrelated query stays empty');
    typoCheck($repository->search('qwertyuiop')===[],'drafts excluded');
    typoCheck($repository->search('fenadr')===[],'two substitutions in short word not overcorrected');
    $repository->search('fendar');typoCheck(strtolower($repository->suggestedQuery())==='fender','correction is explicitly reported');
    $results=$repository->search('fendar','all',1);typoCheck(count($results)===1,'result limit preserved');
    unset($repository);
    foreach(glob($index.'*')?:[] as $file){unlink($file);}
}
echo 'TYPO TESTS COMPLETE PHP '.PHP_VERSION.PHP_EOL;
