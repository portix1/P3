<?php
declare(strict_types=1);
namespace PortixModule\Home;

final class Image
{
    /** Local uploaded raster images only: no remote fetching, SVG or traversal. */
    public static function path(string $value): string
    {
        $value=trim($value);
        return preg_match('~^var/uploads/images/[a-zA-Z0-9_/-]+\.(?:webp|png|jpe?g)$~D',$value)===1
            && !str_contains($value,'..') ? $value : '';
    }

    public static function thumbnail(string $root, string $path): string
    {
        $path=self::path($path);
        if ($path==='') { return ''; }
        $base=realpath($root.'/var/uploads/images'); $file=realpath($root.'/'.$path);
        if ($base===false || $file===false || !str_starts_with($file,$base.DIRECTORY_SEPARATOR) || !is_file($file)) { return ''; }
        $size=filesize($file); $info=@getimagesize($file);
        if (!$info || !$size || $size>8388608 || $info[0]*$info[1]>16000000
            || !in_array($info['mime'],['image/webp','image/png','image/jpeg'],true)) { return ''; }
        if ($info[0]<=256 && $info[1]<=256) { return $path; }
        if (!function_exists('imagewebp')) { return $path; }
        $hash=hash_file('sha256',$file); if (!is_string($hash)) { return ''; }
        $directory=$root.'/var/uploads/images/home-thumbs';
        if (is_link($directory)) { return ''; }
        if (!is_dir($directory) && !@mkdir($directory,0755,true) && !is_dir($directory)) { return $path; }
        $target=$directory.'/mini256-'.$hash.'.webp';
        if (!is_file($target)) {
            $bytes=file_get_contents($file); $source=$bytes!==false ? @imagecreatefromstring($bytes) : false;
            if ($source===false) { return ''; }
            $scale=min(256/$info[0],256/$info[1],1);
            $w=max(1,(int)round($info[0]*$scale)); $h=max(1,(int)round($info[1]*$scale));
            $image=imagecreatetruecolor($w,$h); imagealphablending($image,false); imagesavealpha($image,true);
            imagefill($image,0,0,imagecolorallocatealpha($image,0,0,0,127));
            imagecopyresampled($image,$source,0,0,0,0,$w,$h,$info[0],$info[1]);
            $temporary=$target.'.'.bin2hex(random_bytes(6)).'.tmp';
            $ok=imagewebp($image,$temporary,82); imagedestroy($image); imagedestroy($source);
            if (!$ok || !@rename($temporary,$target)) { @unlink($temporary); return $path; }
            @chmod($target,0644);
        }
        return 'var/uploads/images/home-thumbs/mini256-'.$hash.'.webp';
    }
}
