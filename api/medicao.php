<?php
require_once __DIR__ . '/../includes/medicao.php';
require_once __DIR__ . '/../includes/auth.php';
if (!esta_logado()) medicao_json(401, ['ok' => false, 'erro' => 'Entre novamente no sistema.']);
require_once __DIR__ . '/../database/conexao.php';
$empresa = empresa_id();
$csrf = csrf_token();
session_write_close();
$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrf, $_POST['csrf_token'])) {
        medicao_json(403, ['ok' => false, 'erro' => 'Atualize a página e tente novamente.']);
    }
    $id = (int) ($_POST['prateleira_id'] ?? 0);
    $conexao->begin_transaction();
    // Serializa cliques simultaneos para reaproveitar um pedido ainda pendente.
    $s = $conexao->prepare('SELECT p.id FROM prateleiras p JOIN galpoes g ON g.id=p.galpao_id WHERE p.id=? AND g.empresa_id=? AND p.ativo=1 AND g.ativo=1 FOR UPDATE');
    $s->bind_param('ii', $id, $empresa);
    $s->execute();
    if (!$s->get_result()->fetch_assoc()) medicao_json(404, ['ok' => false, 'erro' => 'Prateleira não encontrada ou inativa.']);
    $s = $conexao->prepare('SELECT id FROM dispositivos WHERE prateleira_id=? AND ativo=1 ORDER BY id LIMIT 1');
    $s->bind_param('i', $id);
    $s->execute();
    $dispositivo = $s->get_result()->fetch_assoc();
    if (!$dispositivo) medicao_json(409, ['ok' => false, 'erro' => 'Esta prateleira ainda não tem um sensor ativo.']);
    $dispositivoId = (int) $dispositivo['id'];
    $s = $conexao->prepare("SELECT id FROM pedidos_medicao WHERE dispositivo_id=? AND estado='pendente' AND expira_em>NOW() ORDER BY id DESC LIMIT 1");
    $s->bind_param('i', $dispositivoId);
    $s->execute();
    $pedido = $s->get_result()->fetch_assoc();
    if (!$pedido) {
        $s = $conexao->prepare('INSERT INTO pedidos_medicao (dispositivo_id, expira_em) VALUES (?, DATE_ADD(NOW(), INTERVAL 30 SECOND))');
        $s->bind_param('i', $dispositivoId);
        $s->execute();
        $pedido = ['id' => $conexao->insert_id];
    }
    $conexao->commit();
    medicao_json(200, ['ok' => true, 'pedido_id' => (int) $pedido['id'], 'dispositivo_id' => $dispositivoId]);
}

if ($metodo !== 'GET') medicao_json(405, ['ok' => false, 'erro' => 'Método inválido.']);
$id = (int) ($_GET['pedido_id'] ?? 0);
$s = $conexao->prepare('SELECT m.*, m.expira_em<=NOW() AS expirado, p.id AS prateleira_id, p.metros_cubicos AS capacidade_m3, COALESCE((SELECT SUM(o.volume_m3) FROM ocupacoes o WHERE o.prateleira_id=p.id AND o.data_saida IS NULL),0) AS volume_registrado_m3 FROM pedidos_medicao m JOIN dispositivos d ON d.id=m.dispositivo_id JOIN prateleiras p ON p.id=d.prateleira_id JOIN galpoes g ON g.id=p.galpao_id WHERE m.id=? AND g.empresa_id=?');
$s->bind_param('ii', $id, $empresa);
$s->execute();
$pedido = $s->get_result()->fetch_assoc();
if (!$pedido) medicao_json(404, ['ok' => false, 'erro' => 'Medição não encontrada.']);
if ($pedido['estado'] === 'pendente' && $pedido['expirado']) $pedido['estado'] = 'expirado';
medicao_json(200, ['ok' => true, 'estado' => $pedido['estado'], 'prateleira_id' => (int) $pedido['prateleira_id'], 'capacidade_m3' => (float) $pedido['capacidade_m3'], 'volume_registrado_m3' => (float) $pedido['volume_registrado_m3'], 'distancia_cm' => $pedido['distancia_cm'], 'percentual_ocupado' => $pedido['percentual_ocupado'], 'erro' => $pedido['erro']]);
