<?php

function url_base_aplicacao(): string
{
    $configurada = rtrim((string) getenv('STOCKSENSE_APP_URL'), '/');
    if ($configurada !== '') {
        $partes = parse_url($configurada);
        if (!$partes || !in_array($partes['scheme'] ?? '', ['http', 'https'], true)
            || empty($partes['host']) || isset($partes['user']) || isset($partes['pass'])
            || isset($partes['query']) || isset($partes['fragment'])) {
            throw new RuntimeException('STOCKSENSE_APP_URL deve conter a URL pública do sistema.');
        }
        return $configurada;
    }
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-zA-Z0-9.\[\]:-]+$/D', $host)) throw new RuntimeException('Host inválido.');
    $diretorio = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $diretorio = preg_replace('~/(actions|api)$~', '', $diretorio);
    return ($https ? 'https://' : 'http://') . $host . rtrim($diretorio, '/.');
}

date_default_timezone_set(getenv('STOCKSENSE_TIMEZONE') ?: 'America/Sao_Paulo');
