<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('../');
exigir_post_seguro();
require_once __DIR__ . '/../database/conexao.php';
$id = (int) ($_POST['dispositivo_id'] ?? 0);
$vazia = filter_var($_POST['distancia_vazia_cm'] ?? null, FILTER_VALIDATE_FLOAT);
$cheia = filter_var($_POST['distancia_cheia_cm'] ?? null, FILTER_VALIDATE_FLOAT);
$stmt = $conexao->prepare('SELECT d.prateleira_id FROM dispositivos d JOIN prateleiras p ON p.id=d.prateleira_id JOIN galpoes g ON g.id=p.galpao_id WHERE d.id=? AND g.empresa_id=? AND d.ativo=1');
$empresa = empresa_id();
$stmt->bind_param('ii', $id, $empresa);
$stmt->execute();
$sensor = $stmt->get_result()->fetch_assoc();
exigir_propriedade((bool) $sensor, '../', 'prateleiras');
$url = '../index.php?pagina=detalhes_prateleira&id=' . (int) $sensor['prateleira_id'];
if ($vazia === false || $cheia === false || !is_finite($vazia) || !is_finite($cheia) || $cheia < 0.01 || $vazia > 1000 || $vazia <= $cheia) {
    redirecionar_com_erro($url, 'Confira as distâncias: vazio deve ser maior que cheio, ambas entre 0,01 e 1000 cm.');
}
$stmt = $conexao->prepare('UPDATE dispositivos SET distancia_vazia_cm=?, distancia_cheia_cm=? WHERE id=?');
$stmt->bind_param('ddi', $vazia, $cheia, $id);
$stmt->execute();
concluir_acao($url, 'Calibração salva. As próximas leituras usarão esses valores.');
