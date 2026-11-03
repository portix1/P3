<?php
foreach ($_LANG as $key => $value) {
    $template->set(
        $key,
        html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8')
    );
}
?>
