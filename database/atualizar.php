<?php
// Execute: php database/atualizar.php. Atualiza a estrutura sem recriar o banco.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/conexao.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function tabela_existe(mysqli $db, string $tabela): bool {
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
    $s->bind_param('s', $tabela); $s->execute();
    return (bool) $s->get_result()->fetch_row()[0];
}

function coluna_tipo(mysqli $db, string $tabela, string $coluna): ?string {
    $s = $db->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
    $s->bind_param('ss', $tabela, $coluna); $s->execute();
    return $s->get_result()->fetch_row()[0] ?? null;
}

function criar_tabela_atual(mysqli $db, string $tabela): void {
    // Reutiliza somente o CREATE TABLE solicitado, sem executar a carga de exemplo.
    $sql = file_get_contents(__DIR__ . '/stocksense.sql');
    $padrao = '/^CREATE TABLE ' . preg_quote($tabela, '/') . ' \(\R.*?^\) ENGINE=InnoDB[^;]*;/ms';
    if ($sql === false || !preg_match($padrao, $sql, $trecho)) {
        throw new RuntimeException("Estrutura de $tabela não encontrada em stocksense.sql.");
    }
    $db->query($trecho[0]);
    echo "Tabela criada: $tabela\n";
}

function garantir_indice(mysqli $db, string $tabela, string $nome, string $colunas): void {
    $s = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
    $s->bind_param('ss', $tabela, $nome); $s->execute();
    if (!(int) $s->get_result()->fetch_row()[0]) {
        // Nomes e colunas vêm somente das constantes abaixo.
        $db->query("CREATE INDEX $nome ON $tabela ($colunas)");
        echo "Índice criado: $nome\n";
    }
}

foreach (['empresas', 'usuarios', 'galpoes', 'prateleiras', 'ocupacoes'] as $tabela) {
    if (!tabela_existe($conexao, $tabela)) {
        exit("Estrutura base ausente ($tabela). Para uma instalação nova, importe database/stocksense.sql.\n");
    }
}

if (!coluna_tipo($conexao, 'prateleiras', 'pct_sensor')) {
    $conexao->query('ALTER TABLE prateleiras ADD COLUMN pct_sensor DECIMAL(5,2) DEFAULT NULL AFTER pct_ocupado');
}
foreach (['dispositivos', 'leituras', 'pedidos_medicao'] as $tabela) {
    if (!tabela_existe($conexao, $tabela)) criar_tabela_atual($conexao, $tabela);
}
if (!coluna_tipo($conexao, 'dispositivos', 'ultimo_contato_em')) {
    $conexao->query('ALTER TABLE dispositivos ADD COLUMN ultimo_contato_em DATETIME DEFAULT NULL');
}
foreach (['galpoes' => 'metros_cubicos', 'prateleiras' => 'metros_cubicos', 'ocupacoes' => 'volume_m3'] as $tabela => $coluna) {
    if (coluna_tipo($conexao, $tabela, $coluna) !== 'decimal(15,7)') {
        $conexao->query("ALTER TABLE $tabela MODIFY $coluna DECIMAL(15,7) DEFAULT 0.00");
    }
}
foreach (['distancia_vazia_cm' => '22.30', 'distancia_cheia_cm' => '3.90'] as $coluna => $padrao) {
    if (!coluna_tipo($conexao, 'dispositivos', $coluna)) {
        $conexao->query("ALTER TABLE dispositivos ADD COLUMN $coluna DECIMAL(8,2) NOT NULL DEFAULT $padrao");
    }
}
foreach ([
    ['galpoes', 'idx_galpoes_empresa', 'empresa_id'],
    ['prateleiras', 'idx_prateleiras_galpao', 'galpao_id'],
    ['ocupacoes', 'idx_ocupacoes_prateleira', 'prateleira_id, data_saida'],
    ['dispositivos', 'idx_dispositivos_prateleira', 'prateleira_id'],
    ['leituras', 'idx_leituras_dispositivo_data', 'dispositivo_id, criado_em'],
    ['leituras', 'idx_leituras_prateleira_data', 'prateleira_id, criado_em'],
    ['pedidos_medicao', 'idx_pedido_dispositivo', 'dispositivo_id, estado, expira_em'],
] as [$tabela, $nome, $colunas]) {
    garantir_indice($conexao, $tabela, $nome, $colunas);
}
echo "Banco atualizado. Nenhum dado foi apagado ou substituído por exemplos.\n";
