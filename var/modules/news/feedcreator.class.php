<?php
/*
 * Portix2 feed compatibility layer for PHP 8.
 *
 * It replaces the bundled FeedCreator 1.7.2 implementation while retaining
 * the small public API used by Portix2. All historical format names now emit
 * a standards-based RSS 2.0 document encoded as UTF-8.
 */

define('FEEDCREATOR_VERSION', 'Portix2 FeedCreator PHP 8');

class HtmlDescribable
{
    public $description = '';
    public $descriptionHtmlSyndicated = false;
    public $descriptionTruncSize = 0;

    public function getDescription()
    {
        $description = (string) $this->description;
        $length = function_exists('mb_strlen') ? mb_strlen($description, 'UTF-8') : strlen($description);
        if ((int) $this->descriptionTruncSize > 0 && $length > (int) $this->descriptionTruncSize) {
            $description = function_exists('mb_substr')
                ? mb_substr($description, 0, (int) $this->descriptionTruncSize - 2, 'UTF-8') . '…'
                : substr($description, 0, (int) $this->descriptionTruncSize - 3) . '...';
        }
        return $description;
    }
}

class FeedItem extends HtmlDescribable
{
    public $title = '';
    public $link = '';
    public $image = null;
    public $category = '';
    public $guid = '';
    public $source = '';
    public $date = '';
    public $additionalElements = array();
}

class FeedImage extends HtmlDescribable
{
    public $title = '';
    public $url = '';
    public $link = '';
    public $width = null;
    public $height = null;
}

class FeedHtmlField
{
    public $rawFieldContent = '';
    public $truncSize = 0;
    public $syndicateHtml = false;

    public function __construct($content = '')
    {
        $this->rawFieldContent = (string) $content;
    }

    public function FeedHtmlField($content = '')
    {
        $this->__construct($content);
    }

    public function output()
    {
        $content = $this->rawFieldContent;
        $length = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        if ((int) $this->truncSize > 0 && $length > (int) $this->truncSize) {
            $content = function_exists('mb_substr')
                ? mb_substr($content, 0, (int) $this->truncSize - 2, 'UTF-8') . '…'
                : substr($content, 0, (int) $this->truncSize - 3) . '...';
        }
        return $this->syndicateHtml
            ? '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $content) . ']]>'
            : htmlspecialchars($content, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

class FeedDate
{
    public $unix = 0;

    public function __construct($date = '')
    {
        if (is_int($date)) {
            $this->unix = $date;
        } else {
            $parsed = $date === '' ? time() : strtotime((string) $date);
            $this->unix = $parsed === false ? time() : $parsed;
        }
    }

    public function FeedDate($date = '')
    {
        $this->__construct($date);
    }

    public function rfc822()
    {
        return date(DATE_RSS, $this->unix);
    }

    public function iso8601()
    {
        return date(DATE_ATOM, $this->unix);
    }

    public function unix()
    {
        return $this->unix;
    }
}

class FeedCreator extends HtmlDescribable
{
    public $title = '';
    public $link = '';
    public $syndicationURL = '';
    public $image = null;
    public $language = 'fr';
    public $copyright = '';
    public $pubDate = '';
    public $lastBuildDate = '';
    public $editor = '';
    public $editorEmail = '';
    public $webmaster = '';
    public $category = '';
    public $docs = '';
    public $ttl = 60;
    public $rating = '';
    public $skipHours = array();
    public $skipDays = array();
    public $xslStyleSheet = '';
    public $cssStyleSheet = '';
    public $items = array();
    public $contentType = 'application/rss+xml';
    public $encoding = 'UTF-8';
    public $additionalElements = array();

    public function addItem($item)
    {
        if ($item instanceof FeedItem) {
            $this->items[] = $item;
        }
    }

    public static function iTrunc($string, $length)
    {
        $string = (string) $string;
        $length = (int) $length;
        $stringLength = function_exists('mb_strlen') ? mb_strlen($string, 'UTF-8') : strlen($string);
        if ($length > 1 && $stringLength > $length) {
            return function_exists('mb_substr')
                ? mb_substr($string, 0, $length - 1, 'UTF-8') . '…'
                : substr($string, 0, $length - 3) . '...';
        }
        return $string;
    }

    public function createFeed($format = 'RSS2.0')
    {
        $xml = array();
        $xml[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml[] = '<rss version="2.0"><channel>';
        $xml[] = '<title>' . self::xml($this->title) . '</title>';
        $xml[] = '<link>' . self::xml($this->link) . '</link>';
        $xml[] = '<description>' . self::xml(strip_tags((string) $this->description)) . '</description>';
        $xml[] = '<language>' . self::xml($this->language) . '</language>';
        $xml[] = '<generator>' . self::xml(FEEDCREATOR_VERSION) . '</generator>';

        if ($this->syndicationURL !== '') {
            $xml[] = '<atom:link xmlns:atom="http://www.w3.org/2005/Atom" href="'
                . self::xml($this->syndicationURL) . '" rel="self" type="application/rss+xml" />';
        }

        foreach ($this->items as $item) {
            $date = new FeedDate($item->date);
            $guid = $item->guid !== '' ? $item->guid : $item->link;
            $xml[] = '<item>';
            $xml[] = '<title>' . self::xml($item->title) . '</title>';
            $xml[] = '<link>' . self::xml($item->link) . '</link>';
            $xml[] = '<guid isPermaLink="true">' . self::xml($guid) . '</guid>';
            $xml[] = '<description>' . self::xml(strip_tags($item->getDescription())) . '</description>';
            $xml[] = '<pubDate>' . self::xml($date->rfc822()) . '</pubDate>';
            $xml[] = '</item>';
        }

        $xml[] = '</channel></rss>';
        return implode("\n", $xml) . "\n";
    }

    public function saveFeed($filename = '', $displayContents = true)
    {
        $feed = $this->createFeed();
        if ($filename !== '' && file_put_contents($filename, $feed, LOCK_EX) === false) {
            throw new RuntimeException('Unable to save feed: ' . $filename);
        }
        if ($displayContents) {
            if (!headers_sent()) {
                header('Content-Type: ' . $this->contentType . '; charset=UTF-8');
            }
            echo $feed;
        }
        return $feed;
    }

    public function useCached($filename = '', $timeout = 3600)
    {
        return false;
    }

    protected static function xml($value)
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

class UniversalFeedCreator extends FeedCreator
{
    public function saveFeed($format = 'RSS2.0', $filename = '', $displayContents = true)
    {
        return parent::saveFeed($filename, $displayContents);
    }

    public function useCached($format = 'RSS2.0', $filename = '', $timeout = 3600)
    {
        return false;
    }
}

class RSSCreator091 extends FeedCreator {}
class RSSCreator10 extends FeedCreator {}
class RSSCreator20 extends FeedCreator {}
class PIECreator01 extends FeedCreator {}
class AtomCreator03 extends FeedCreator {}
class MBOXCreator extends FeedCreator {}
class OPMLCreator extends FeedCreator {}
class HTMLCreator extends FeedCreator {}
class JSCreator extends FeedCreator {}
