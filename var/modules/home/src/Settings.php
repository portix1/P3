<?php
declare(strict_types=1);
namespace PortixModule\Home;

final class Settings
{
    public static function read(string $root='.'): array
    {
        $data=\PortixSettings::read('home',['days'=>28,'exploration'=>50,'refresh_minutes'=>60,'tracking'=>false,'secret'=>''],$root);
        return self::normalize($data)+['secret'=>(string)($data['secret'] ?? '')];
    }
    public static function normalize(array $data): array
    {
        return ['days'=>max(7,min(90,(int)($data['days']??28))),
            'exploration'=>max(20,min(100,(int)($data['exploration']??50))),
            'refresh_minutes'=>max(5,min(1440,(int)($data['refresh_minutes']??60))),
            'tracking'=>!empty($data['tracking'])];
    }
}
