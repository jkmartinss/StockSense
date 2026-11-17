<?php
require_once __DIR__ . '/../includes/medicao.php';
require_once __DIR__ . '/../database/conexao.php';
$token = (string) ($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? '');
$s = $conexao->prepare('SELECT d.id, d.prateleira_id, d.distancia_vazia_cm, d.distancia_cheia_cm FROM dispositivos d JOIN prateleiras p ON p.id=d.prateleira_id JOIN galpoes g ON g.id=p.galpao_id WHERE d.token=? AND d.ativo=1 AND p.ativo=1 AND g.ativo=1');
$s->bind_param('s', $token);
$s->execute();
$dispositivo = $s->get_result()->fetch_assoc();
if (!$dispositivo) medicao_json(401, ['ok' => false, 'erro' => 'Dispositivo inválido ou inativo.']);
$dispositivoId = (int) $dispositivo['id'];
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = $conexao->prepare("SELECT id FROM pedidos_medicao WHERE dispositivo_id=? AND estado='pendente' AND expira_em>NOW() ORDER BY id LIMIT 1");
    $s->bind_param('i', $dispositivoId);
    $s->execute();
    $pedido = $s->get_result()->fetch_assoc();
    header('Content-Type: text/plain');
    header('Cache-Control: no-store');
    echo $pedido ? $pedido['id'] : '0';
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') medicao_json(405, ['ok' => false]);
$id = (int) ($_POST['pedido_id'] ?? 0);
$erroSensor = ($_POST['erro'] ?? '') === 'sem_eco';
$distancia = $_POST['distancia_cm'] ?? null;
$percentual = null;
if (!$erroSensor && (!is_numeric($distancia) || !is_finite((float) $distancia) || $distancia < 0 || $distancia > 1000)) {
    medicao_json(422, ['ok' => false, 'erro' => 'Leitura inválida.']);
}
$conexao->begin_transaction();
$s = $conexao->prepare('SELECT *, expira_em<=NOW() AS expirado FROM pedidos_medicao WHERE id=? AND dispositivo_id=? FOR UPDATE');
$s->bind_param('ii', $id, $dispositivoId);
$s->execute();
$pedido = $s->get_result()->fetch_assoc();
if (!$pedido) medicao_json(404, ['ok' => false]);
// Reenvios depois de uma resposta perdida nao duplicam a leitura.
if ($pedido['estado'] !== 'pendente') {
    $conexao->commit();
    medicao_json(200, ['ok' => true]);
}
if ($pedido['expirado']) medicao_json(409, ['ok' => false, 'erro' => 'Pedido expirado.']);
if ($erroSensor) {
    $s = $conexao->prepare("UPDATE pedidos_medicao SET estado='erro', erro='Sensor sem retorno. Confira o posicionamento.', concluido_em=NOW() WHERE id=?");
    $s->bind_param('i', $id);
} else {
    $distancia = (float) $distancia;
    $percentual = round(max(0, min(100, ((float) $dispositivo['distancia_vazia_cm'] - $distancia) * 100 / ((float) $dispositivo['distancia_vazia_cm'] - (float) $dispositivo['distancia_cheia_cm']))), 2);
    $prateleiraId = (int) $dispositivo['prateleira_id'];
    $s = $conexao->prepare('INSERT INTO leituras (dispositivo_id,prateleira_id,distancia_cm,percentual_ocupado) VALUES (?,?,?,?)');
    $s->bind_param('iidd', $dispositivoId, $prateleiraId, $distancia, $percentual);
    $s->execute();
    $s = $conexao->prepare('UPDATE prateleiras SET pct_sensor=? WHERE id=?');
    $s->bind_param('di', $percentual, $prateleiraId);
    $s->execute();
    $s = $conexao->prepare("UPDATE pedidos_medicao SET estado='concluido', distancia_cm=?, percentual_ocupado=?, concluido_em=NOW() WHERE id=?");
    $s->bind_param('ddi', $distancia, $percentual, $id);
}
$s->execute();
$conexao->commit();
medicao_json(201, ['ok' => true]);
