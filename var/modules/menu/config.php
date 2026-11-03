<?php
// Administration du menu public.
$template->setSlot('main', 'var/modules/menu/templates/admin.tpl');
$menuMessage = '';

$normaliseMenuUrl = function ($value) {
    $value = trim((string) $value);
    if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        throw new InvalidArgumentException($translator->text('menu_invalid_url'));
    }

    $scheme = parse_url($value, PHP_URL_SCHEME);
    if (is_string($scheme) && $scheme !== '') {
        if (!in_array(strtolower($scheme), array('http', 'https'), true)
            || !is_string(parse_url($value, PHP_URL_HOST))
            || parse_url($value, PHP_URL_HOST) === '') {
            throw new InvalidArgumentException($translator->text('menu_allowed_urls'));
        }
    } elseif (substr($value, 0, 2) === '//'
        || $value[0] === '/'
        || !preg_match('!^[A-Za-z0-9][A-Za-z0-9._~%/?#=&+\-]*$!', $value)) {
        throw new InvalidArgumentException($translator->text('menu_relative_url_help'));
    }

    return $value;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['menu_action'])
    && (string) $_POST['menu_action'] === 'save') {
    try {
        PortixSecurity::requirePostWithCsrf();

        $names = isset($_POST['name']) && is_array($_POST['name']) ? $_POST['name'] : array();
        $urls = isset($_POST['url']) && is_array($_POST['url']) ? $_POST['url'] : array();
        $descriptions = isset($_POST['description']) && is_array($_POST['description'])
            ? $_POST['description']
            : array();
        $deletedNames = isset($_POST['delete']) && is_array($_POST['delete'])
            ? array_map('strval', $_POST['delete'])
            : array();

        $existingNames = array();
        foreach ($repositories->menu()->allOrdered() as $existing) {
            $existingNames[(string) $existing['name']] = true;
        }

        $validatedUrls = array();
        foreach ($names as $index => $name) {
            if (!in_array((string) $name, $deletedNames, true)) {
                $submittedUrl = trim((string) ($urls[$index] ?? ''));
                $validatedUrls[$index] = $submittedUrl === ''
                    ? $repositories->menu()->localUrlFromName((string) $name, (string) $name)
                    : $normaliseMenuUrl($submittedUrl);
            }
        }

        $newName = trim((string) ($_POST['newname'] ?? ''));
        $newUrl = '';
        if ($newName !== '') {
            if (strlen($newName) > 100 || isset($existingNames[$newName])) {
                throw new InvalidArgumentException($translator->text('menu_invalid_name'));
            }
            $submittedUrl = trim((string) ($_POST['newurl'] ?? ''));
            $newUrl = $submittedUrl === ''
                ? $repositories->menu()->localUrlFromName($newName)
                : $normaliseMenuUrl($submittedUrl);
        }

        foreach ($names as $index => $name) {
            $name = (string) $name;
            if (!isset($existingNames[$name])) {
                continue;
            }
            if (in_array($name, $deletedNames, true)) {
                $repositories->menu()->delete($name);
                continue;
            }

            $repositories->menu()->update(
                $name,
                trim((string) (isset($descriptions[$index]) ? $descriptions[$index] : '')),
                $validatedUrls[$index]
            );
        }

        if ($newName !== '') {
            $repositories->menu()->create(
                $newName,
                trim((string) ($_POST['newdescription'] ?? '')),
                $newUrl
            );
        }

        $order = isset($_POST['order']) && is_array($_POST['order'])
            ? array_map('strval', $_POST['order'])
            : array();
        $repositories->menu()->reorder($order);
        $menuMessage = $translator->text('menu_saved');
    } catch (Throwable $error) {
        $menuMessage = $translator->exception($error);
    }
}

$template->set('menu_message', $menuMessage);
$position = 0;
foreach ($repositories->menu()->allOrdered() as $record) {
    $position++;
    $template->addRow('simplemenu', array(
        'name' => (string) $record['name'],
        'url' => (string) $record['url'],
        'description' => (string) $record['description'],
        'position' => (string) $position,
    ));
}
?>
