<?php
require_once __DIR__ . '/../includes/auth.php';
exigir_login('../');
exigir_post_seguro();
require_once __DIR__ . '/../database/conexao.php';
$id = (int) ($_POST['dispositivo_id'] ?? 0);
$empresa = empresa_id();
$s = $conexao->prepare('SELECT d.token FROM dispositivos d JOIN prateleiras p ON p.id=d.prateleira_id JOIN galpoes g ON g.id=p.galpao_id WHERE d.id=? AND g.empresa_id=?');
$s->bind_param('ii', $id, $empresa); $s->execute();
$sensor = $s->get_result()->fetch_assoc();
exigir_propriedade((bool) $sensor, '../', 'sensores');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="config.h"');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function literal_cpp(string $valor): string {
    return '"' . str_replace(["\\", '"', "\r", "\n"], ["\\\\", '\\"', '', ''], $valor) . '"';
}
echo "#pragma once\n// Configuração privada. Preencha o Wi-Fi e não publique este arquivo.\n";
echo "const char* WIFI_SSID = \"SEU_WIFI_2_4_GHZ\";\nconst char* WIFI_SENHA = \"SUA_SENHA\";\n";
echo 'const char* API_URL = ' . literal_cpp(url_base_aplicacao() . '/api/medicao_dispositivo.php') . ";\n";
echo 'const char* TOKEN = ' . literal_cpp($sensor['token']) . ";\n";
echo "#define STOCKSENSE_TRIG_PIN 26\n#define STOCKSENSE_ECHO_PIN 25\n#define STOCKSENSE_HTTP_LOCAL 0\n";
