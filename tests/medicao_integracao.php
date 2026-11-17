<?php
// Executar somente pela CLI, com Apache e MySQL locais ativos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/../database/conexao.php';
$base = 'http://localhost/StockSense/';
$empresa = null;
$cookie = '';
function conferir($condicao, $mensagem) {
    if (!$condicao) throw new RuntimeException($mensagem);
}
function chamar($path, $dados = null, $token = null, $autenticado = true) {
    global $base, $cookie;
    $headers = ['Accept: application/json'];
    if ($autenticado) $headers[] = 'Cookie: ' . $cookie;
    if ($token !== null) $headers[] = 'X-Device-Token: ' . $token;
    if ($dados !== null) $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    $ctx = stream_context_create(['http' => ['method' => $dados === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers), 'content' => $dados === null ? '' : http_build_query($dados), 'ignore_errors' => true, 'timeout' => 5]]);
    $body = file_get_contents($base . $path, false, $ctx);
    preg_match('/\s(\d{3})\s/', $http_response_header[0], $match);
    return [(int) $match[1], json_decode($body, true), $body];
}
try {
    $conexao->query("INSERT INTO empresas (nome) VALUES ('Teste temporário de medição')");
    $empresa = $conexao->insert_id;
    $conexao->query("INSERT INTO galpoes (empresa_id,nome,metros_cubicos) VALUES ($empresa,'Teste',1)");
    $galpao = $conexao->insert_id;
    $conexao->query("INSERT INTO prateleiras (galpao_id,codigo,metros_cubicos) VALUES ($galpao,'TESTE',0.0124752)");
    $prateleira = $conexao->insert_id;
    $token = bin2hex(random_bytes(32));
    $conexao->query("INSERT INTO dispositivos (prateleira_id,nome,token) VALUES ($prateleira,'Teste','$token')");
    $dispositivo = $conexao->insert_id;
    session_start();
    $_SESSION = ['usuario_id' => 1, 'empresa_id' => $empresa, 'csrf_token' => bin2hex(random_bytes(16))];
    $csrf = $_SESSION['csrf_token'];
    $cookie = session_name() . '=' . session_id();
    session_write_close();
    conferir(chamar('api/medicao.php', null, null, false)[0] === 401, 'Bloquear sem login');
    $dados = ['prateleira_id' => $prateleira, 'csrf_token' => 'invalido'];
    conferir(chamar('api/medicao.php', $dados)[0] === 403, 'Validar CSRF');
    $dados['csrf_token'] = $csrf;
    // Uma segunda sessao nao pode consultar ou medir prateleira alheia.
    session_start(); $_SESSION['empresa_id'] = -1; session_write_close();
    conferir(chamar('api/medicao.php', $dados)[0] === 404, 'Isolar empresa na solicitação');
    session_start(); $_SESSION['empresa_id'] = $empresa; session_write_close();
    [$status, $pedido] = chamar('api/medicao.php', $dados);
    conferir($status === 200, 'Solicitar medição');
    $id = $pedido['pedido_id'];
    conferir(chamar('api/medicao.php', $dados)[1]['pedido_id'] === $id, 'Reaproveitar clique duplicado');
    conferir(chamar('api/medicao_dispositivo.php', null, 'invalido')[0] === 401, 'Validar token');
    conferir((int) chamar('api/medicao_dispositivo.php', null, $token)[2] === $id, 'Entregar pedido ao ESP32');
    $leitura = ['pedido_id' => $id, 'distancia_cm' => 13.1, 'percentual_ocupado' => 50];
    conferir(chamar('api/medicao_dispositivo.php', array_merge($leitura, ['distancia_cm' => 1001]), $token)[0] === 422, 'Rejeitar distância fora do intervalo');
    conferir(chamar('api/medicao_dispositivo.php', array_merge($leitura, ['pedido_id' => 0]), $token)[0] === 404, 'Rejeitar pedido alheio/inexistente');
    conferir(chamar('api/medicao_dispositivo.php', $leitura, $token)[0] === 201, 'Salvar leitura');
    conferir(chamar('api/medicao_dispositivo.php', $leitura, $token)[0] === 200, 'Reenvio idempotente');
    conferir((int) $conexao->query("SELECT COUNT(*) FROM leituras WHERE dispositivo_id=$dispositivo")->fetch_row()[0] === 1, 'Sem duplicatas');
    conferir(chamar('api/medicao.php?pedido_id=' . $id)[1]['estado'] === 'concluido', 'Exibir resultado');
    conferir((float) $conexao->query("SELECT pct_sensor FROM prateleiras WHERE id=$prateleira")->fetch_row()[0] === 50.0, 'Atualizar percentual');
    $conexao->query("UPDATE dispositivos SET distancia_vazia_cm=30, distancia_cheia_cm=10 WHERE id=$dispositivo");
    $idCalibrado = chamar('api/medicao.php', $dados)[1]['pedido_id'];
    conferir(chamar('api/medicao_dispositivo.php', ['pedido_id' => $idCalibrado, 'distancia_cm' => 20], $token)[0] === 201, 'Aceitar medição após calibrar');
    conferir((float) chamar('api/medicao.php?pedido_id=' . $idCalibrado)[1]['percentual_ocupado'] === 50.0, 'Usar calibração do banco');
    session_start(); $_SESSION['empresa_id'] = -1; session_write_close();
    conferir(chamar('api/medicao.php?pedido_id=' . $id)[0] === 404, 'Isolar consulta do resultado');
    session_start(); $_SESSION['empresa_id'] = $empresa; session_write_close();
    $id = chamar('api/medicao.php', $dados)[1]['pedido_id'];
    conferir(chamar('api/medicao_dispositivo.php', ['pedido_id' => $id, 'erro' => 'sem_eco'], $token)[0] === 201, 'Informar sem eco');
    conferir(chamar('api/medicao.php?pedido_id=' . $id)[1]['estado'] === 'erro', 'Mostrar falha do sensor');
    $id = chamar('api/medicao.php', $dados)[1]['pedido_id'];
    $conexao->query("UPDATE pedidos_medicao SET expira_em=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=$id");
    conferir(chamar('api/medicao.php?pedido_id=' . $id)[1]['estado'] === 'expirado', 'Informar placa offline');
    conferir(chamar('api/medicao_dispositivo.php', array_merge($leitura, ['pedido_id' => $id]), $token)[0] === 409, 'Rejeitar leitura atrasada');
    conferir(trim(chamar('api/medicao_dispositivo.php', null, $token)[2]) === '0', 'Sem pedidos: aguardar sem medir');
} finally {
    if ($empresa) $conexao->query("DELETE FROM empresas WHERE id=$empresa");
    if ($cookie) { session_start(); $_SESSION = []; session_destroy(); }
}
echo "OK: autenticação, CSRF, isolamento, pedido, leitura, reenvio, sem eco e expiração.\n";
