<?php
// Somente leitura. Executar NA VM: php tools/diagnostico.php [URL pública]
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/../includes/configuracao.php';
$falhas = 0;
function verificar_diagnostico(bool $ok, string $texto): void {
    global $falhas;
    echo ($ok ? '[OK] ' : '[FALHA] ') . $texto . PHP_EOL;
    if (!$ok) $falhas++;
}
echo 'StockSense: diagnóstico de implantação (somente leitura)' . PHP_EOL;
echo 'PHP ' . PHP_VERSION . PHP_EOL;
foreach (['mysqli', 'curl', 'openssl'] as $extensao) verificar_diagnostico(extension_loaded($extensao), "Extensão $extensao");
verificar_diagnostico(extension_loaded('mysqlnd'), 'Driver mysqlnd para prepared statements');
$pastaSessoes = ini_get('session.save_path');
if ($pastaSessoes !== '' && strpos($pastaSessoes, ';') === false && ini_get('session.save_handler') === 'files') {
    verificar_diagnostico(is_writable($pastaSessoes), 'Diretório de sessões gravável pelo PHP CLI (confira também o usuário do servidor web)');
}
if (!extension_loaded('mysqli')) exit(1);
require __DIR__ . '/../database/conexao.php';
echo 'Banco: ' . $conexao->query('SELECT DATABASE()')->fetch_row()[0] . ' / ' . $conexao->server_info . PHP_EOL;
foreach (['empresas','usuarios','galpoes','prateleiras','ocupacoes','dispositivos','leituras','pedidos_medicao'] as $tabela) {
    try { $n = $conexao->query("SELECT COUNT(*) FROM $tabela")->fetch_row()[0]; verificar_diagnostico(true, "$tabela: $n registros"); }
    catch (Throwable $erro) { verificar_diagnostico(false, "$tabela ausente. Execute php database/atualizar.php."); }
}
try {
    $s = $conexao->query('SELECT e.nome AS empresa,g.nome AS galpao,p.codigo,d.id,d.nome,d.ativo,d.ultimo_contato_em,
        d.distancia_vazia_cm,d.distancia_cheia_cm FROM dispositivos d JOIN prateleiras p ON p.id=d.prateleira_id
        JOIN galpoes g ON g.id=p.galpao_id JOIN empresas e ON e.id=g.empresa_id ORDER BY e.nome,g.nome,p.codigo');
    echo PHP_EOL . 'Vínculos dos sensores (tokens omitidos):' . PHP_EOL;
    foreach ($s->fetch_all(MYSQLI_ASSOC) as $d) echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $duplicados = $conexao->query('SELECT prateleira_id FROM dispositivos WHERE ativo=1 GROUP BY prateleira_id HAVING COUNT(*)>1')->num_rows;
    verificar_diagnostico($duplicados === 0, 'No máximo um sensor ativo por prateleira');
} catch (Throwable $erro) { verificar_diagnostico(false, 'Estrutura de sensores desatualizada. Execute o atualizador.'); }
$url = rtrim($argv[1] ?? (getenv('STOCKSENSE_APP_URL') ?: ''), '/');
if ($url === '') {
    verificar_diagnostico(false, 'Defina STOCKSENSE_APP_URL ou informe a URL pública como argumento.');
} elseif (extension_loaded('curl')) {
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['https','http'], true)) exit("URL inválida.\n");
    foreach (['/login.php' => 200, '/api/medicao.php' => 401, '/api/medicao_dispositivo.php' => 401, '/database/stocksense.sql' => [403,404]] as $rota => $esperado) {
        $c = curl_init($url . $rota);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        curl_exec($c); $codigo = curl_getinfo($c, CURLINFO_RESPONSE_CODE); $erro = curl_error($c); curl_close($c);
        verificar_diagnostico(in_array($codigo, (array) $esperado, true), $rota . ': HTTP ' . $codigo . ($erro ? ' / ' . $erro : ''));
    }
}
echo PHP_EOL . ($falhas ? "$falhas pendência(s)." : 'Diagnóstico concluído.') . PHP_EOL;
exit($falhas ? 1 : 0);
