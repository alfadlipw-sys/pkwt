<?php
// includes/logo.php
// Panggil dengan: require_once __DIR__ . '/../includes/logo.php'; logoIndopelita();
// Parameter: $size = 'sm' | 'md' | 'lg' | 'sidebar'

function logoIndopelita(string $size = 'md', string $extraStyle = ''): void {
    $sizes = [
        'sm'      => 'height:32px;',
        'md'      => 'height:48px;',
        'lg'      => 'height:72px;',
        'sidebar' => 'height:38px;filter:brightness(0) invert(1);',  // putih untuk sidebar gelap
        'form'    => 'height:56px;',
        'print'   => 'height:44px;',
    ];
    $style = ($sizes[$size] ?? $sizes['md']) . $extraStyle;
    $src   = APP_URL . '/assets/img/logo.png';
    echo "<img src=\"{$src}\" alt=\"Indopelita\" style=\"{$style}display:block;\">";
}
?>
