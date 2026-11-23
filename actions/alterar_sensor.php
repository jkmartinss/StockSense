<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('../');
exigir_post_seguro();
require_once __DIR__ . '/../database/conexao.php';
$empresa = empresa_id();
$id = (int) ($_POST['dispositivo_id'] ?? 0);
$acao = (string) ($_POST['acao'] ?? '');
$url = '../index.php?pagina=sensores';
if (!in_array($acao, ['ativar', 'desativar', 'renovar_token'], true)) redirecionar_com_erro($url, 'Ação inválida.');
$s = $conexao->prepare('SELECT d.prateleira_id FROM dispositivos d JOIN prateleiras p ON p.id=d.prateleira_id JOIN galpoes g ON g.id=p.galpao_id WHERE d.id=? AND g.empresa_id=?');
$s->bind_param('ii', $id, $empresa); $s->execute();
$sensor = $s->get_result()->fetch_assoc();
exigir_propriedade((bool) $sensor, '../', 'sensores');
$prateleira = (int) $sensor['prateleira_id'];
$conexao->begin_transaction();
$s = $conexao->prepare('SELECT p.id,p.ativo,g.ativo AS galpao_ativo FROM prateleiras p JOIN galpoes g ON g.id=p.galpao_id WHERE p.id=? AND g.empresa_id=? FOR UPDATE');
$s->bind_param('ii', $prateleira, $empresa); $s->execute();
$p = $s->get_result()->fetch_assoc();
if (!$p || ($acao === 'ativar' && (!$p['ativo'] || !$p['galpao_ativo']))) {
    $conexao->rollback();
    redirecionar_com_erro($url, 'Ative o galpão e a prateleira antes de ativar o sensor.');
}
if ($acao === 'ativar') {
    $s = $conexao->prepare('SELECT id FROM dispositivos WHERE prateleira_id=? AND ativo=1 AND id<>?');
    $s->bind_param('ii', $prateleira, $id); $s->execute();
    if ($s->get_result()->num_rows) {
        $conexao->rollback();
        redirecionar_com_erro($url, 'Já existe outro sensor ativo nessa prateleira. Desative-o primeiro.');
    }
}
if ($acao === 'renovar_token') {
    $token = bin2hex(random_bytes(32));
    $s = $conexao->prepare('UPDATE dispositivos SET token=?,ultimo_contato_em=NULL WHERE id=?');
    $s->bind_param('si', $token, $id);
} else {
    $ativo = $acao === 'ativar' ? 1 : 0;
    $s = $conexao->prepare('UPDATE dispositivos SET ativo=?,ultimo_contato_em=NULL WHERE id=?');
    $s->bind_param('ii', $ativo, $id);
}
$s->execute();
// Impede que um pedido anterior seja atendido após troca de token/estado.
$s = $conexao->prepare("UPDATE pedidos_medicao SET estado='erro',erro='Configuração do sensor alterada. Solicite uma nova medição.',concluido_em=NOW() WHERE dispositivo_id=? AND estado='pendente'");
$s->bind_param('i', $id); $s->execute();
$conexao->commit();
concluir_acao($url, $acao === 'renovar_token' ? 'Token renovado. Baixe o novo config.h e grave novamente o ESP32.' : 'Estado do sensor atualizado.');
