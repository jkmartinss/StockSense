<?php
$empresa = empresa_id();
$s = $conexao->prepare('SELECT d.id,d.nome,d.ativo,d.ultimo_contato_em,p.id AS prateleira_id,p.codigo,p.ativo AS prateleira_ativa,
    g.nome AS galpao,g.ativo AS galpao_ativo,
    (d.ultimo_contato_em>=DATE_SUB(NOW(), INTERVAL 30 SECOND)) AS online,
    (SELECT l.criado_em FROM leituras l WHERE l.dispositivo_id=d.id ORDER BY l.id DESC LIMIT 1) AS ultima_leitura
    FROM dispositivos d JOIN prateleiras p ON p.id=d.prateleira_id JOIN galpoes g ON g.id=p.galpao_id
    WHERE g.empresa_id=? ORDER BY g.nome,p.codigo,d.id');
$s->bind_param('i', $empresa); $s->execute();
$sensores = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$s = $conexao->prepare('SELECT p.id,p.codigo,g.nome AS galpao FROM prateleiras p JOIN galpoes g ON g.id=p.galpao_id
    WHERE g.empresa_id=? AND p.ativo=1 AND g.ativo=1
    AND NOT EXISTS (SELECT 1 FROM dispositivos d WHERE d.prateleira_id=p.id AND d.ativo=1) ORDER BY g.nome,p.codigo');
$s->bind_param('i', $empresa); $s->execute();
$disponiveis = $s->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<div class="pagina-topo"><div><h1>Sensores</h1><p>Confira a prateleira de cada ESP32 e prepare sua configuração.</p></div>
    <a class="btn btn-neutro" href="index.php?pagina=sensores">Atualizar situação</a></div>
<section class="card"><div class="card-corpo">
    <p>Escolha o sensor da prateleira que será medida. Renove o token de demonstração, baixe o <strong>config.h</strong>, preencha o Wi-Fi e grave o firmware na placa.</p>
    <p class="texto-fraco">O sensor fica vinculado à prateleira indicada abaixo. Uma placa atende um token por vez. O login determina a empresa cujo estoque você acessa.</p>
    <p>API do ESP32: <code class="sensor-endereco"><?= e(url_base_aplicacao() . '/api/medicao_dispositivo.php') ?></code></p>
</div></section>
<section class="card"><div class="card-topo"><h2>Dispositivos da empresa</h2><span class="contador"><?= count($sensores) ?></span></div>
<div class="tabela-rolagem"><table class="tabela"><thead><tr><th>Sensor</th><th>Prateleira</th><th>Comunicação</th><th>Última leitura</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($sensores as $sensor): ?>
<tr><td><strong><?= e($sensor['nome']) ?></strong><br><span class="texto-fraco">ID <?= (int) $sensor['id'] ?> · <?= $sensor['ativo'] ? 'Habilitado' : 'Desativado' ?></span></td>
<td><a href="index.php?pagina=detalhes_prateleira&id=<?= (int) $sensor['prateleira_id'] ?>"><?= e($sensor['galpao'] . ' / ' . $sensor['codigo']) ?></a>
<?php if (!$sensor['prateleira_ativa'] || !$sensor['galpao_ativo']): ?><br><span class="texto-fraco">Galpão ou prateleira inativo</span><?php endif; ?></td>
<td><span class="etiqueta <?= $sensor['ativo'] && $sensor['online'] ? 'livre' : 'inativo' ?>"><?= !$sensor['ativo'] ? 'Desativado' : ($sensor['online'] ? 'Online' : 'Sem contato recente') ?></span><br>
<span class="texto-fraco"><?= e($sensor['ultimo_contato_em'] ?: 'Nenhum contato da placa') ?></span></td>
<td><?= e($sensor['ultima_leitura'] ?: 'Nenhuma leitura') ?></td>
<td><div class="sensor-acoes">
<form method="POST" action="actions/baixar_config_sensor.php" data-download>
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="dispositivo_id" value="<?= (int) $sensor['id'] ?>">
<button type="submit" class="btn btn-neutro btn-mini">Baixar config.h</button></form>
<form method="POST" action="actions/alterar_sensor.php">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="dispositivo_id" value="<?= (int) $sensor['id'] ?>"><input type="hidden" name="acao" value="renovar_token">
<button type="submit" class="btn btn-neutro btn-mini" data-confirmar="Renovar o token deste sensor? Será preciso baixar o novo config.h e gravar a placa novamente.">Renovar token</button></form>
<form method="POST" action="actions/alterar_sensor.php">
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="dispositivo_id" value="<?= (int) $sensor['id'] ?>"><input type="hidden" name="acao" value="<?= $sensor['ativo'] ? 'desativar' : 'ativar' ?>">
<button type="submit" class="btn btn-neutro btn-mini" data-confirmar="Alterar o estado deste sensor? Pedidos em andamento serão cancelados."><?= $sensor['ativo'] ? 'Desativar' : 'Ativar' ?></button></form>
</div></td></tr>
<?php endforeach; ?>
<?php if (!$sensores): ?><tr><td colspan="5">Cadastre um sensor abaixo e escolha sua prateleira.</td></tr><?php endif; ?>
</tbody></table></div><div class="card-corpo"><p class="texto-fraco">Online significa contato autenticado nos últimos 30 segundos. Leituras de exemplo do SQL não indicam que uma placa esteja conectada. Atualize esta página para conferir.</p></div></section>
<?php if ($disponiveis): ?>
<form method="POST" action="actions/salvar_sensor.php" class="card"><div class="card-topo"><h2>Cadastrar sensor</h2></div>
<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="form"><div class="form-grade">
<div class="campo"><label for="nome_sensor">Nome do sensor</label><input id="nome_sensor" name="nome" maxlength="80" required placeholder="ESP32 da maquete"></div>
<div class="campo"><label for="prateleira_sensor">Prateleira</label><select id="prateleira_sensor" name="prateleira_id" required><option value="">Selecione</option>
<?php foreach ($disponiveis as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['galpao'] . ' / ' . $p['codigo']) ?></option><?php endforeach; ?></select></div>
</div></div><div class="form-acoes"><button type="submit" class="btn btn-primario">Cadastrar sensor</button></div></form>
<?php endif; ?>
