<?php
// Compatibilidade com firmware antigo de envio contínuo. O firmware atual usa pedidos.
require_once __DIR__ . '/../includes/medicao.php';
require_once __DIR__ . '/../includes/sensores.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    medicao_json(405, ['ok' => false, 'erro' => 'Use o método POST.']);
}
$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = [];
$token = $_SERVER['HTTP_X_DEVICE_TOKEN'] ?? $input['token'] ?? $_POST['token'] ?? '';
$distancia = $input['distancia_cm'] ?? $_POST['distancia_cm'] ?? null;
if (!is_string($token) || $token === '' || strlen($token) > 128) medicao_json(401, ['ok' => false, 'erro' => 'Dispositivo inválido ou inativo.']);
if (!is_numeric($distancia) || !is_finite((float) $distancia) || $distancia < 0 || $distancia > 1000) medicao_json(422, ['ok' => false, 'erro' => 'Distância inválida.']);
require_once __DIR__ . '/../database/conexao.php';
$dispositivo = sensor_autenticado($conexao, $token);
if (!$dispositivo) medicao_json(401, ['ok' => false, 'erro' => 'Dispositivo inválido ou inativo.']);
$distancia = (float) $distancia;
try { $percentual = percentual_sensor($dispositivo, $distancia); }
catch (InvalidArgumentException $erro) { medicao_json(422, ['ok' => false, 'erro' => $erro->getMessage()]); }
$dispositivoId = (int) $dispositivo['id'];
$prateleiraId = (int) $dispositivo['prateleira_id'];
$conexao->begin_transaction();
$s = $conexao->prepare('SELECT id FROM prateleiras WHERE id=? FOR UPDATE');
$s->bind_param('i', $prateleiraId); $s->execute();
$s = $conexao->prepare('SELECT id FROM dispositivos WHERE id=? AND BINARY token=? AND ativo=1 FOR UPDATE');
$s->bind_param('is', $dispositivoId, $token); $s->execute();
if (!$s->get_result()->fetch_assoc()) medicao_json(401, ['ok' => false, 'erro' => 'Token alterado ou sensor inativo.']);
$s = $conexao->prepare('INSERT INTO leituras (dispositivo_id,prateleira_id,distancia_cm,percentual_ocupado) VALUES (?,?,?,?)');
$s->bind_param('iidd', $dispositivoId, $prateleiraId, $distancia, $percentual); $s->execute();
$s = $conexao->prepare('UPDATE prateleiras SET pct_sensor=? WHERE id=?');
$s->bind_param('di', $percentual, $prateleiraId); $s->execute();
$conexao->commit();
medicao_json(201, ['ok' => true, 'mensagem' => 'Leitura registrada.', 'prateleira_id' => $prateleiraId, 'distancia_cm' => $distancia, 'percentual_ocupado' => $percentual]);
