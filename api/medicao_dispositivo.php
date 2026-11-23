<?php
require_once __DIR__ . '/../includes/medicao.php';
require_once __DIR__ . '/../includes/sensores.php';
$token = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '';
if (!is_string($token) || $token === '' || strlen($token) > 128) {
    medicao_json(401, ['ok' => false, 'erro' => 'Dispositivo inválido ou inativo.']);
}
$metodo = $_SERVER['REQUEST_METHOD'];
if (!in_array($metodo, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    medicao_json(405, ['ok' => false, 'erro' => 'Use GET ou POST.']);
}
require_once __DIR__ . '/../database/conexao.php';
$dispositivo = sensor_autenticado($conexao, $token);
if (!$dispositivo) medicao_json(401, ['ok' => false, 'erro' => 'Dispositivo inválido ou inativo.']);
$dispositivoId = (int) $dispositivo['id'];
$prateleiraId = (int) $dispositivo['prateleira_id'];
if ($metodo === 'GET') {
    $s = $conexao->prepare("SELECT id FROM pedidos_medicao WHERE dispositivo_id=? AND estado='pendente' AND expira_em>NOW() ORDER BY id LIMIT 1");
    $s->bind_param('i', $dispositivoId); $s->execute();
    $pedido = $s->get_result()->fetch_assoc();
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');
    echo $pedido ? $pedido['id'] : '0';
    exit;
}
$id = filter_var($_POST['pedido_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) medicao_json(404, ['ok' => false, 'erro' => 'Pedido não encontrado.']);
$erroSensor = ($_POST['erro'] ?? '') === 'sem_eco';
$distancia = $_POST['distancia_cm'] ?? null;
if (!$erroSensor && (!is_numeric($distancia) || !is_finite((float) $distancia) || $distancia < 0 || $distancia > 1000)) {
    medicao_json(422, ['ok' => false, 'erro' => 'Leitura inválida.']);
}
$conexao->begin_transaction();
// Mesma ordem de bloqueios do cadastro e das configurações: prateleira, sensor, pedido.
$s = $conexao->prepare('SELECT p.id FROM prateleiras p JOIN galpoes g ON g.id=p.galpao_id JOIN empresas e ON e.id=g.empresa_id WHERE p.id=? AND p.ativo=1 AND g.ativo=1 AND e.ativo=1 FOR UPDATE');
$s->bind_param('i', $prateleiraId); $s->execute();
if (!$s->get_result()->fetch_assoc()) medicao_json(401, ['ok' => false, 'erro' => 'Prateleira inativa.']);
$s = $conexao->prepare('SELECT id, distancia_vazia_cm, distancia_cheia_cm FROM dispositivos WHERE id=? AND BINARY token=? AND ativo=1 FOR UPDATE');
$s->bind_param('is', $dispositivoId, $token); $s->execute();
$dispositivo = $s->get_result()->fetch_assoc();
if (!$dispositivo) medicao_json(401, ['ok' => false, 'erro' => 'Token alterado ou sensor inativo.']);
$s = $conexao->prepare('SELECT *, expira_em<=NOW() AS expirado FROM pedidos_medicao WHERE id=? AND dispositivo_id=? FOR UPDATE');
$s->bind_param('ii', $id, $dispositivoId); $s->execute();
$pedido = $s->get_result()->fetch_assoc();
if (!$pedido) medicao_json(404, ['ok' => false, 'erro' => 'Pedido não encontrado para este sensor.']);
// Reenvio após perda de resposta não duplica a leitura.
if ($pedido['estado'] !== 'pendente') {
    $conexao->commit();
    medicao_json(200, ['ok' => true, 'estado' => $pedido['estado']]);
}
if ($pedido['expirado']) medicao_json(409, ['ok' => false, 'erro' => 'Pedido expirado. Solicite uma nova medição.']);
if ($erroSensor) {
    $s = $conexao->prepare("UPDATE pedidos_medicao SET estado='erro', erro='Sensor sem retorno. Confira o posicionamento.', concluido_em=NOW() WHERE id=?");
    $s->bind_param('i', $id);
} else {
    $distancia = (float) $distancia;
    try { $percentual = percentual_sensor($dispositivo, $distancia); }
    catch (InvalidArgumentException $erro) { medicao_json(422, ['ok' => false, 'erro' => $erro->getMessage()]); }
    $s = $conexao->prepare('INSERT INTO leituras (dispositivo_id,prateleira_id,distancia_cm,percentual_ocupado) VALUES (?,?,?,?)');
    $s->bind_param('iidd', $dispositivoId, $prateleiraId, $distancia, $percentual); $s->execute();
    $s = $conexao->prepare('UPDATE prateleiras SET pct_sensor=? WHERE id=?');
    $s->bind_param('di', $percentual, $prateleiraId); $s->execute();
    $s = $conexao->prepare("UPDATE pedidos_medicao SET estado='concluido', distancia_cm=?, percentual_ocupado=?, concluido_em=NOW() WHERE id=?");
    $s->bind_param('ddi', $distancia, $percentual, $id);
}
$s->execute();
$conexao->commit();
medicao_json(201, ['ok' => true]);
