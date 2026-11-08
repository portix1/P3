<?php
declare(strict_types=1);
if ((require __DIR__.'/boot.php') === false) { return; }
use PortixModule\Home\Renderer;

$homeStaff=$user->isAdmin() || $user->isRedactor();
$insightMode=\PortixSettings::read('insights',['mode'=>'minimal'])['mode'];
$homeMeasurable=$homeSettings['tracking'] && strlen($homeSettings['secret'])>=32 && $homeRepository->enabled('insights') && $insightMode!=='off'
    && !$homeStaff && ($_SERVER['HTTP_DNT']??'')!=='1'
    && !preg_match('/bot|crawl|spider|slurp|headless|monitor|lighthouse|pagespeed|bingpreview|facebookexternalhit/i',(string)($_SERVER['HTTP_USER_AGENT']??''));
if (($argument[1]??'')==='events') {
    header('Cache-Control: no-store'); header('X-Robots-Tag: noindex');
    try {
        if ($request->method()!=='POST') { http_response_code(405); header('Allow: POST'); exit; }
        if (!$homeMeasurable) { http_response_code(204); exit; }
        if (($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin')==='cross-site') { throw new InvalidArgumentException('origin'); }
        $origin=$_SERVER['HTTP_ORIGIN']??'';
        $originHost=(string)parse_url($origin,PHP_URL_HOST);
        $originPort=parse_url($origin,PHP_URL_PORT);
        if ($originPort!==null && $originPort!==false) { $originHost.=':'.$originPort; }
        if ($origin!=='' && strtolower($originHost)!==strtolower($request->publicHost())) { throw new InvalidArgumentException('origin'); }
        if (!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json')) { throw new InvalidArgumentException('type'); }
        $raw=file_get_contents('php://input',false,null,0,32769);
        if (!is_string($raw) || strlen($raw)>32768) { throw new InvalidArgumentException('size'); }
        $input=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if (!is_array($input)) { throw new InvalidArgumentException('body'); }
        $limiter=new \Portix\Security\RateLimiter($database->connection());
        $rateKey='home:'.hash_hmac('sha256',(string)($_SERVER['REMOTE_ADDR']??'unknown'),$homeSettings['secret']);
        if ($limiter->consumeMany([$rateKey],600,180)>0) { http_response_code(429); exit; }
        (new \PortixModule\Home\Metrics($database->connection(),$homeSettings['secret']))->accept($input,time());
        http_response_code(204);
    } catch (InvalidArgumentException|JsonException $error) { http_response_code(422);
    } catch (Throwable $error) { error_log('Home metrics: '.$error->getMessage()); http_response_code(503); }
    exit;
}
if (count($argument)>1 || !in_array($request->method(),['GET','HEAD'],true)) {
    http_response_code(404); $template->setSlot('main','./var/admin/templates/not-found.tpl'); return;
}
$pageMetadata->define($translator->text('home_title'),'', 'home.html');
$template->setSlot('main',__DIR__.'/templates/index.tpl');
$homeCss=(string)file_get_contents(__DIR__.'/assets/public.css');
$template->set('home_style_html',\Portix\View\Html::trusted('<style>'.$homeCss.'</style>'));
$template->set('home_title',$translator->text('home_title'));
$template->set('home_empty',$translator->text('home_empty'));
$homeSections=$homeRepository->selection($homeSettings,time());
[$homeHtml,$homeSlots]=Renderer::render($homeSections,$homeRoot,[
    'home_all'=>$translator->text('home_all'), 'home_series_start'=>$translator->text('home_series_start'), 'home_disclosure'=>$translator->text('home_disclosure'),
],$homeRepository->categories());
$template->set('home_html',\Portix\View\Html::trusted($homeHtml));
$template->set('home_has_content',$homeHtml!==''?'1':'0');
$template->set('home_tracking_enabled',$homeMeasurable?'1':'0');
$template->set('home_ticket',$homeMeasurable?(new \PortixModule\Home\Metrics($database->connection(),$homeSettings['secret']))->ticket($homeSlots,time()):'');
