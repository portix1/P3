<?php
declare(strict_types=1);

namespace Portix\View;

use RuntimeException;

final class Template
{
    /** @var array<string, mixed> */
    private array $data = [];

    /** @var array<string, string> */
    private array $slots = [];

    public function __construct(private readonly string $file)
    {
        $this->assertTemplateFile($file);
    }

    public function set(string $name, mixed $value): void
    {
        $this->assertName($name);
        $this->data[$name] = $value;
    }

    /** @param array<string, mixed> $row */
    public function addRow(string $collection, array $row): void
    {
        $this->assertName($collection);
        if (!isset($this->data[$collection])) {
            $this->data[$collection] = [];
        }
        if (!is_array($this->data[$collection]) || !array_is_list($this->data[$collection])) {
            throw new RuntimeException('Template collection conflicts with a scalar: ' . $collection);
        }
        $this->data[$collection][] = $row;
    }

    public function setSlot(string $name, string $file): void
    {
        $this->assertName($name);
        $this->assertTemplateFile($file);
        $this->slots[$name] = $file;
    }

    /** @return list<array<string, mixed>> */
    public function rows(string $collection): array
    {
        $rows = $this->data[$collection] ?? [];
        return is_array($rows) && array_is_list($rows) ? $rows : [];
    }

    public function render(): string
    {
        $html = $this->renderFile($this->file, $this->data, 0);
        return MobileThemeAssets::inject($html, $this->file);
    }

    public function validate(): void
    {
        $this->compile($this->file);
    }

    public function display(): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo $this->render();
    }

    /** @param array<string, mixed> $context */
    private function renderFile(string $file, array $context, int $depth): string
    {
        if ($depth > 30) {
            throw new RuntimeException('Template inclusion depth exceeded.');
        }
        $nodes = $this->compile($file);
        return $this->renderNodes($nodes, $context, dirname($file), $depth);
    }

    /** @return list<array<string, mixed>> */
    private function compile(string $file): array
    {
        $source = file_get_contents($file);
        if ($source === false) {
            throw new RuntimeException('Unable to read template file: ' . $file);
        }
        $tokens = preg_split(
            '/(\{\{\s*[A-Za-z_][A-Za-z0-9_.]*\s*\}\}|\{%\s*.+?\s*%\})/s',
            $source,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );
        if (!is_array($tokens)) {
            throw new RuntimeException('Unable to tokenize template: ' . $file);
        }
        $index = 0;
        [$nodes, $ending] = $this->parseNodes($tokens, $index, []);
        if ($ending !== null) {
            throw new RuntimeException('Unexpected template terminator: ' . $ending);
        }
        return $nodes;
    }

    /**
     * @param list<string> $tokens
     * @param list<string> $stops
     * @return array{0:list<array<string, mixed>>,1:?string}
     */
    private function parseNodes(array $tokens, int &$index, array $stops): array
    {
        $nodes = [];
        while ($index < count($tokens)) {
            $token = $tokens[$index++];
            if (str_starts_with($token, '{{')) {
                $nodes[] = ['type' => 'variable', 'path' => trim(substr($token, 2, -2))];
                continue;
            }
            if (!str_starts_with($token, '{%')) {
                $nodes[] = ['type' => 'text', 'value' => $token];
                continue;
            }

            $directive = trim(substr($token, 2, -2));
            $keyword = strtolower(strtok($directive, " \t\r\n") ?: '');
            if (in_array($keyword, $stops, true)) {
                return [$nodes, $keyword];
            }
            if (str_starts_with($directive, 'if ')) {
                $condition = trim(substr($directive, 3));
                $this->assertCondition($condition);
                [$truthy, $ending] = $this->parseNodes($tokens, $index, ['else', 'endif']);
                $falsy = [];
                if ($ending === 'else') {
                    [$falsy, $ending] = $this->parseNodes($tokens, $index, ['endif']);
                }
                if ($ending !== 'endif') {
                    throw new RuntimeException('Unclosed template condition.');
                }
                $nodes[] = [
                    'type' => 'if',
                    'condition' => $condition,
                    'truthy' => $truthy,
                    'falsy' => $falsy,
                ];
                continue;
            }
            if (preg_match('/^for\s+([A-Za-z_][A-Za-z0-9_]*)\s+in\s+([A-Za-z_][A-Za-z0-9_.]*)$/', $directive, $match) === 1) {
                [$body, $ending] = $this->parseNodes($tokens, $index, ['endfor']);
                if ($ending !== 'endfor') {
                    throw new RuntimeException('Unclosed template loop.');
                }
                $nodes[] = [
                    'type' => 'for',
                    'alias' => $match[1],
                    'collection' => $match[2],
                    'body' => $body,
                ];
                continue;
            }
            if (preg_match('/^slot\s+([A-Za-z_][A-Za-z0-9_]*)$/', $directive, $match) === 1) {
                $nodes[] = ['type' => 'slot', 'name' => $match[1]];
                continue;
            }
            if (preg_match('/^include\s+"([^"]+\.tpl)"$/', $directive, $match) === 1) {
                $nodes[] = ['type' => 'include', 'file' => $match[1]];
                continue;
            }
            throw new RuntimeException('Unsupported template directive: ' . $directive);
        }
        return [$nodes, null];
    }

    /** @param list<array<string, mixed>> $nodes @param array<string, mixed> $context */
    private function renderNodes(array $nodes, array $context, string $directory, int $depth): string
    {
        $output = '';
        foreach ($nodes as $node) {
            switch ($node['type']) {
                case 'text':
                    $output .= $node['value'];
                    break;
                case 'variable':
                    [$found, $value] = $this->lookup($context, (string) $node['path']);
                    if (!$found) {
                        throw new RuntimeException('Undefined template variable: ' . $node['path']);
                    }
                    $output .= $this->escape($value);
                    break;
                case 'if':
                    $branch = $this->conditionMatches((string) $node['condition'], $context)
                        ? $node['truthy']
                        : $node['falsy'];
                    $output .= $this->renderNodes($branch, $context, $directory, $depth);
                    break;
                case 'for':
                    [$found, $rows] = $this->lookup($context, (string) $node['collection']);
                    if (!$found) {
                        $rows = [];
                    }
                    if (!is_array($rows)) {
                        throw new RuntimeException('Template loop value is not iterable: ' . $node['collection']);
                    }
                    foreach ($rows as $row) {
                        if (!is_array($row)) {
                            throw new RuntimeException('Template loop row is not an object: ' . $node['collection']);
                        }
                        $rowContext = $context;
                        $rowContext[(string) $node['alias']] = $row;
                        $output .= $this->renderNodes($node['body'], $rowContext, $directory, $depth);
                    }
                    break;
                case 'slot':
                    $slot = $this->slots[(string) $node['name']] ?? null;
                    if ($slot !== null) {
                        $output .= $this->renderFile($slot, $context, $depth + 1);
                    }
                    break;
                case 'include':
                    $included = $this->resolveIncludedFile($directory, (string) $node['file']);
                    $output .= $this->renderFile($included, $context, $depth + 1);
                    break;
            }
        }
        return $output;
    }

    /** @param array<string, mixed> $context */
    private function conditionMatches(string $condition, array $context): bool
    {
        if (preg_match('/^not\s+([A-Za-z_][A-Za-z0-9_.]*)$/', $condition, $match) === 1) {
            [$found, $value] = $this->lookup($context, $match[1]);
            return !$found || !$this->truthy($value);
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_.]*)\s*(==|!=)\s*"([^"]*)"$/', $condition, $match) === 1) {
            [$found, $value] = $this->lookup($context, $match[1]);
            $equal = $found && (string) $value === $match[3];
            return $match[2] === '==' ? $equal : !$equal;
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/', $condition) === 1) {
            [$found, $value] = $this->lookup($context, $condition);
            return $found && $this->truthy($value);
        }
        throw new RuntimeException('Unsupported template condition: ' . $condition);
    }

    private function assertCondition(string $condition): void
    {
        if (preg_match('/^(?:not\s+)?[A-Za-z_][A-Za-z0-9_.]*$/', $condition) === 1
            || preg_match('/^[A-Za-z_][A-Za-z0-9_.]*\s*(?:==|!=)\s*"[^"]*"$/', $condition) === 1) {
            return;
        }
        throw new RuntimeException('Unsupported template condition: ' . $condition);
    }

    /** @param array<string, mixed> $context @return array{bool,mixed} */
    private function lookup(array $context, string $path): array
    {
        $value = $context;
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return [false, null];
            }
            $value = $value[$segment];
        }
        return [true, $value];
    }

    private function escape(mixed $value): string
    {
        if ($value instanceof Html) {
            return $value->value();
        }
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '';
        }
        if (!is_scalar($value)) {
            throw new RuntimeException('Template variables must be scalar or trusted HTML.');
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function truthy(mixed $value): bool
    {
        return !($value === null || $value === false || $value === 0 || $value === '0' || $value === '');
    }

    private function resolveIncludedFile(string $directory, string $file): string
    {
        $candidate = str_starts_with($file, './') || str_starts_with($file, '../')
            ? $directory . '/' . $file
            : $file;
        $this->assertTemplateFile($candidate);
        return $candidate;
    }

    private function assertTemplateFile(string $file): void
    {
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'tpl' || !is_file($file) || is_link($file)) {
            throw new RuntimeException('Invalid template file: ' . $file);
        }
    }

    private function assertName(string $name): void
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new RuntimeException('Invalid template name: ' . $name);
        }
    }
}
