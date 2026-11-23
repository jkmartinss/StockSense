<?php

/**
 * Conexao principal do StockSense.
 *
 * - XAMPP/local: usa localhost, root, sem senha por padrao.
 * - Producao: crie database/conexao.local.php com as credenciais do host.
 *
 * O arquivo local fica fora do Git para evitar publicar senhas.
 */

require_once __DIR__ . '/../includes/configuracao.php';
$arquivoLocal = __DIR__ . "/conexao.local.php";

if (file_exists($arquivoLocal)) {
    require $arquivoLocal;
} else {
    $hostHttp = strtolower((string) ($_SERVER["HTTP_HOST"] ?? $_SERVER["SERVER_NAME"] ?? ""));
    $hostBase = preg_replace('/:\d+$/', '', $hostHttp);

    $ehLocal = in_array($hostBase, ["localhost", "127.0.0.1", "::1"], true);

    if ($ehLocal) {
        $host = "localhost";
        $usuario = "root";
        $senha = "";
        $banco = "stocksense";
    } else {
        $host    = getenv("STOCKSENSE_DB_HOST") ?: "";
        $usuario = getenv("STOCKSENSE_DB_USER") ?: "";
        $senha   = getenv("STOCKSENSE_DB_PASS") ?: "";
        $banco   = getenv("STOCKSENSE_DB_NAME") ?: "";

        if ($host === "" || $usuario === "" || $banco === "") {
            http_response_code(500);
            exit("Configuracao do banco nao encontrada. Crie database/conexao.local.php.");
        }
    }
}

$porta = (int) ($porta ?? (getenv('STOCKSENSE_DB_PORT') ?: 3306));
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conexao = new mysqli($host, $usuario, $senha, $banco, $porta);
} catch (mysqli_sql_exception $erro) {
    error_log('StockSense: erro na conexão com o banco: ' . $erro->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Não foi possível conectar ao banco. Confira a configuração desta instalação.\n");
        exit(1);
    }
    if (function_exists('medicao_json')) medicao_json(503, ['ok' => false, 'erro' => 'Banco temporariamente indisponível. Tente novamente.']);
    http_response_code(503);
    exit('Não foi possível conectar ao banco de dados.');
}

$conexao->set_charset("utf8mb4");
