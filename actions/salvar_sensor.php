<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('../');
exigir_post_seguro();
require_once __DIR__ . '/../database/conexao.php';
$empresa = empresa_id();
$id = (int) ($_POST['prateleira_id'] ?? 0);
$nome = trim((string) ($_POST['nome'] ?? ''));
$url = '../index.php?pagina=sensores';
if (!preg_match('/^.{1,80}$/usD', $nome)) redirecionar_com_erro($url, 'Informe um nome de até 80 caracteres.');
$conexao->begin_transaction();
$s = $conexao->prepare('SELECT p.id FROM prateleiras p JOIN galpoes g ON g.id=p.galpao_id WHERE p.id=? AND g.empresa_id=? AND p.ativo=1 AND g.ativo=1 FOR UPDATE');
$s->bind_param('ii', $id, $empresa); $s->execute();
if (!$s->get_result()->fetch_assoc()) {
    $conexao->rollback();
    redirecionar_com_erro($url, 'Escolha uma prateleira ativa da sua empresa.');
}
$s = $conexao->prepare('SELECT id FROM dispositivos WHERE prateleira_id=? AND ativo=1');
$s->bind_param('i', $id); $s->execute();
if ($s->get_result()->num_rows) {
    $conexao->rollback();
    redirecionar_com_erro($url, 'Essa prateleira já tem sensor ativo. Use o existente ou desative-o antes.');
}
$token = bin2hex(random_bytes(32));
$s = $conexao->prepare('INSERT INTO dispositivos (prateleira_id,nome,token) VALUES (?,?,?)');
$s->bind_param('iss', $id, $nome, $token); $s->execute();
$conexao->commit();
concluir_acao($url, 'Sensor cadastrado. Baixe a configuração, preencha o Wi-Fi e grave o firmware no ESP32.');
