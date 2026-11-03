<?php
/** Secure local storage for raster images inserted in rich-text content. */
final class PortixImageStorage
{
    public const MAX_UPLOAD_BYTES = 8388608;
    public const MAX_SOURCE_PIXELS = 16000000;
    public const MAX_OUTPUT_SIDE = 2560;

    public static function storeUploadedFile($file, $destinationDirectory)
    {
        if (!is_array($file) || !isset($file['error'])) {
            throw new InvalidArgumentException('Aucune image reçue.');
        }

        $error = (int) $file['error'];
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::uploadErrorMessage($error));
        }

        $temporaryFile = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($temporaryFile === '' || !is_file($temporaryFile)) {
            throw new RuntimeException('Le fichier temporaire est introuvable.');
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($temporaryFile)) {
            throw new RuntimeException('Le transfert de l’image n’est pas valide.');
        }

        $size = filesize($temporaryFile);
        if ($size === false || $size < 1) {
            throw new RuntimeException('L’image reçue est vide.');
        }
        if ($size > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('L’image dépasse la limite de 8 Mio.');
        }
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('L’extension PHP GD est requise pour traiter les images.');
        }

        $bytes = file_get_contents($temporaryFile);
        if ($bytes === false) {
            throw new RuntimeException('Impossible de lire l’image reçue.');
        }
        $description = @getimagesizefromstring($bytes);
        if (!is_array($description) || empty($description[0]) || empty($description[1])) {
            throw new RuntimeException('Le fichier reçu n’est pas une image valide.');
        }

        $width = (int) $description[0];
        $height = (int) $description[1];
        if ($width * $height > self::MAX_SOURCE_PIXELS) {
            throw new RuntimeException('L’image dépasse la limite de 16 mégapixels.');
        }

        $mime = isset($description['mime']) ? strtolower((string) $description['mime']) : '';
        self::assertSupportedMime($mime);
        if (!function_exists('imagewebp')) {
            throw new RuntimeException('Le support WebP de PHP GD est requis pour traiter les nouvelles images.');
        }
        $extension = 'webp';
        $source = @imagecreatefromstring($bytes);
        unset($bytes);
        if ($source === false) {
            throw new RuntimeException('Impossible de décoder l’image.');
        }

        $image = self::resize($source, $width, $height, $mime);
        if ($image !== $source) {
            imagedestroy($source);
        }

        $destinationDirectory = rtrim((string) $destinationDirectory, '/\\');
        if ($destinationDirectory === '') {
            imagedestroy($image);
            throw new InvalidArgumentException('Dossier de stockage invalide.');
        }
        if (!is_dir($destinationDirectory)
            && !mkdir($destinationDirectory, 0775, true)
            && !is_dir($destinationDirectory)) {
            imagedestroy($image);
            throw new RuntimeException('Impossible de créer le dossier des images.');
        }
        if (!is_writable($destinationDirectory)) {
            imagedestroy($image);
            throw new RuntimeException('Le dossier des images n’est pas accessible en écriture.');
        }

        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $finalPath = $destinationDirectory . DIRECTORY_SEPARATOR . $name;
        $temporaryPath = $destinationDirectory . DIRECTORY_SEPARATOR
            . '.' . $name . '.' . bin2hex(random_bytes(4)) . '.tmp';

        try {
            if (!self::writeImage($image, $temporaryPath)) {
                throw new RuntimeException('Impossible d’encoder l’image.');
            }
            $outputSize = filesize($temporaryPath);
            if ($outputSize === false || $outputSize < 1 || $outputSize > 12582912) {
                throw new RuntimeException('La taille de l’image optimisée est invalide.');
            }
            if (!rename($temporaryPath, $finalPath)) {
                throw new RuntimeException('Impossible de finaliser le stockage de l’image.');
            }
            @chmod($finalPath, 0644);
        } catch (Throwable $error) {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
            throw $error;
        } finally {
            imagedestroy($image);
        }

        if (class_exists(\Portix\Media\MediaBridge::class)
            && realpath($destinationDirectory) === realpath(dirname(__DIR__, 2) . '/var/uploads/images')) {
            \Portix\Media\MediaBridge::registerImage('var/uploads/images/' . $name, (string) ($file['name'] ?? $name));
        }
        return $name;
    }

    public static function optimiseReferencedFilenames($html, $destinationDirectory)
    {
        $html = (string) $html;
        if ($html === '' || !class_exists('DOMDocument')) {
            return $html;
        }
        $destinationDirectory = rtrim((string) $destinationDirectory, '/\\');
        if (!is_dir($destinationDirectory) || !is_writable($destinationDirectory)) {
            return $html;
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!doctype html><html><body><div id="portix-image-root">' . $html . '</div></body></html>';
        if (!$document->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }
        $xpath = new DOMXPath($document);
        $images = $xpath->query('//*[@id="portix-image-root"]//img');
        if ($images === false) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return $html;
        }
        $mapped = [];
        foreach ($images as $image) {
            if (!$image instanceof DOMElement) { continue; }
            $src = trim($image->getAttribute('src'));
            if (preg_match('~^(?:\\.\\./|/)?var/uploads/images/([a-f0-9]{32}\\.webp)$~i', $src, $match) !== 1) {
                continue;
            }
            $oldName = strtolower($match[1]);
            if (class_exists(\Portix\Media\MediaBridge::class) && \Portix\Media\MediaBridge::managed('var/uploads/images/' . $oldName)) { continue; }
            if (isset($mapped[$oldName])) {
                $image->setAttribute('src', 'var/uploads/images/' . $mapped[$oldName]);
                continue;
            }
            $label = trim($image->getAttribute('alt'));
            if ($label === '') {
                $figure = $image->parentNode;
                while ($figure instanceof DOMElement && strtolower($figure->tagName) !== 'figure') {
                    $figure = $figure->parentNode;
                }
                if ($figure instanceof DOMElement) {
                    foreach ($figure->getElementsByTagName('figcaption') as $caption) {
                        $label = trim((string) $caption->textContent);
                        if ($label !== '') { break; }
                    }
                }
            }
            $slug = self::filenameSlug($label);
            if ($slug === '') { continue; }
            $source = $destinationDirectory . DIRECTORY_SEPARATOR . $oldName;
            if (!is_file($source)) { continue; }
            $suffix = substr(pathinfo($oldName, PATHINFO_FILENAME), 0, 8);
            $newName = mb_substr($slug, 0, 72, 'UTF-8') . '-' . $suffix . '.webp';
            $target = $destinationDirectory . DIRECTORY_SEPARATOR . $newName;
            if (!is_file($target) && !copy($source, $target)) { continue; }
            @chmod($target, 0644);
            $mapped[$oldName] = $newName;
            $image->setAttribute('src', 'var/uploads/images/' . $newName);
        }
        $root = $document->getElementById('portix-image-root');
        $result = '';
        if ($root instanceof DOMElement) {
            foreach ($root->childNodes as $child) {
                $result .= (string) $document->saveHTML($child);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $result !== '' ? $result : $html;
    }

    private static function filenameSlug($value)
    {
        $value = trim(strip_tags((string) $value));
        if ($value === '') { return ''; }
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') { $value = $ascii; }
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value);
        return trim((string) $value, '-');
    }

    private static function resize($source, $width, $height, $mime)
    {
        $scale = min(1, self::MAX_OUTPUT_SIDE / max($width, $height));
        if ($scale >= 1) {
            return $source;
        }

        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            throw new RuntimeException('Mémoire insuffisante pour redimensionner l’image.');
        }
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);
        }
        if (!imagecopyresampled(
            $target,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $width,
            $height
        )) {
            imagedestroy($target);
            throw new RuntimeException('Impossible de redimensionner l’image.');
        }
        return $target;
    }

    private static function assertSupportedMime($mime)
    {
        if (!in_array($mime, array('image/jpeg', 'image/png', 'image/webp'), true)) {
            throw new RuntimeException('Format refusé : utilisez une image JPEG, PNG ou WebP.');
        }
    }

    private static function writeImage($image, $path)
    {
        return function_exists('imagewebp') && imagewebp($image, $path, 86);
    }

    private static function uploadErrorMessage($error)
    {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'L’image dépasse la limite d’envoi du serveur.';
            case UPLOAD_ERR_PARTIAL:
                return 'Le transfert de l’image est incomplet.';
            case UPLOAD_ERR_NO_FILE:
                return 'Aucune image reçue.';
            default:
                return 'Le transfert de l’image a échoué.';
        }
    }
}
