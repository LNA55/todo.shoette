<?php
// Page "sante" : liste dédiée (titre affiché « santé »). Template partagé dans /tasks/page.php.
$LIST_KEY   = 'sante';
$PAGE_TITLE = 'santé';

// Footer : liens vers toutes les pages sous /sante/ (sous-dossiers contenant un index.php).
// Auto-détecté → une nouvelle page /sante/xxx apparaîtra ici toute seule.
$FOOTER_TITLE = 'Pages santé :';
$FOOTER_LINKS = [];
foreach (glob(__DIR__ . '/*/index.php') ?: [] as $sub) {
    $slug = basename(dirname($sub));
    $FOOTER_LINKS[] = ['href' => '/sante/' . $slug, 'label' => $slug];
}

require __DIR__ . '/../tasks/page.php';
