<?php
// Execute: php database/atualizar.php. Pode rodar novamente sem recriar o banco.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/conexao.php';
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
function aplicar(mysqli $db, string $arquivo): void {
    $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(__DIR__ . '/' . $arquivo));
    foreach (explode(';', $sql) as $comando) {
        $comando = trim($comando);
        if ($comando === '' || preg_match('/^USE\s+/i', $comando)) continue;
        $db->query($comando);
    }
    echo "Aplicada: $arquivo\n";
}
if (!tabela_existe($conexao, 'prateleiras')) {
    exit("Banco vazio. Importe primeiro database/stocksense.sql.\n");
}
if (!coluna_tipo($conexao, 'prateleiras', 'pct_sensor') && !tabela_existe($conexao, 'dispositivos') && !tabela_existe($conexao, 'leituras')) {
    aplicar($conexao, 'migracao_arduino.sql');
} elseif (!coluna_tipo($conexao, 'prateleiras', 'pct_sensor') || !tabela_existe($conexao, 'dispositivos') || !tabela_existe($conexao, 'leituras')) {
    exit("Estrutura de sensor incompleta. Confira o banco antes de continuar.\n");
}
if (coluna_tipo($conexao, 'prateleiras', 'metros_cubicos') !== 'decimal(15,7)' || coluna_tipo($conexao, 'galpoes', 'metros_cubicos') !== 'decimal(15,7)' || coluna_tipo($conexao, 'ocupacoes', 'volume_m3') !== 'decimal(15,7)') {
    aplicar($conexao, 'migracao_precisao_volumes.sql');
}
if (!tabela_existe($conexao, 'pedidos_medicao')) aplicar($conexao, 'migracao_medicao_manual.sql');
if (!coluna_tipo($conexao, 'dispositivos', 'distancia_vazia_cm') && !coluna_tipo($conexao, 'dispositivos', 'distancia_cheia_cm')) {
    aplicar($conexao, 'migracao_calibracao.sql');
} elseif (!coluna_tipo($conexao, 'dispositivos', 'distancia_vazia_cm') || !coluna_tipo($conexao, 'dispositivos', 'distancia_cheia_cm')) {
    exit("Calibração incompleta. Confira o banco antes de continuar.\n");
}
echo "Banco atualizado. Nenhum dado foi apagado.\n";
