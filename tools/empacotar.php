<?php
// Cria um ZIP do projeto sem credenciais, dependências de teste ou metadados Git.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!class_exists('ZipArchive')) exit("Ative a extensão zip do PHP.\n");
$raiz = dirname(__DIR__);
$destino = $argv[1] ?? $raiz . '/.test-tools/StockSense-deploy.zip';
if (!is_dir(dirname($destino))) mkdir(dirname($destino), 0775, true);
$zip = new ZipArchive();
if ($zip->open($destino, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) exit("Não foi possível criar o ZIP.\n");
$arquivos = [];
foreach (['actions','api','arduino','assets','database','includes','pages','tests','tools','deploy'] as $pasta) {
    if (!is_dir($raiz . '/' . $pasta)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($raiz . '/' . $pasta, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $arquivo) if ($arquivo->isFile()) $arquivos[] = $arquivo->getPathname();
}
foreach (glob($raiz . '/*.php') as $arquivo) $arquivos[] = $arquivo;
foreach (['README.md','.htaccess','.gitignore'] as $arquivo) if (is_file($raiz . '/' . $arquivo)) $arquivos[] = $raiz . '/' . $arquivo;
foreach ($arquivos as $arquivo) {
    $relativo = str_replace('\\', '/', substr($arquivo, strlen($raiz) + 1));
    if (in_array(basename($arquivo), ['config.h','conexao.local.php','.env','.env.local'], true)
        || preg_match('/\.(?:zip|log|bak|backup|pem|key)$/i', $arquivo)) continue;
    $zip->addFile($arquivo, 'StockSense/' . $relativo);
}
$zip->close();
echo "ZIP criado: $destino\nCredenciais locais e configurações privadas foram excluídas.\n";
