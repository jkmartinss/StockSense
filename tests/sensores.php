<?php
require_once __DIR__ . '/../includes/sensores.php';
require_once __DIR__ . '/../includes/configuracao.php';
$verificacoes = 0;
function conferir_sensor(bool $condicao, string $mensagem): void {
    global $verificacoes;
    if (!$condicao) throw new RuntimeException($mensagem);
    $verificacoes++;
}
$sensor = ['distancia_vazia_cm' => 22.30, 'distancia_cheia_cm' => 3.90];
foreach ([[22.3,0],[3.9,100],[13.1,50],[30,0],[0,100],[14.9,40.22]] as [$cm,$pct]) {
    conferir_sensor(percentual_sensor($sensor, $cm) === (float) $pct, "Percentual incorreto: $cm cm");
}
foreach ([-1,1001,INF,NAN] as $cm) {
    try { percentual_sensor($sensor, $cm); throw new RuntimeException('Distância inválida aceita.'); }
    catch (InvalidArgumentException $erro) { $verificacoes++; }
}
foreach ([[10,10],[10,20],[1001,10],[20,0]] as [$vazia,$cheia]) {
    try { percentual_sensor(['distancia_vazia_cm' => $vazia,'distancia_cheia_cm' => $cheia], 15); throw new RuntimeException('Calibração inválida aceita.'); }
    catch (InvalidArgumentException $erro) { $verificacoes++; }
}
$anterior = getenv('STOCKSENSE_APP_URL');
try {
    putenv('STOCKSENSE_APP_URL=https://stocksense.147.15.2.73.sslip.io/');
    conferir_sensor(url_base_aplicacao() === 'https://stocksense.147.15.2.73.sslip.io', 'URL do deploy incorreta.');
    putenv('STOCKSENSE_APP_URL=https://exemplo.test/StockSense');
    conferir_sensor(url_base_aplicacao() === 'https://exemplo.test/StockSense', 'Perdeu subpasta do deploy.');
    foreach (['ftp://exemplo.test','https://usuario@exemplo.test','https://exemplo.test/?token=segredo'] as $url) {
        putenv('STOCKSENSE_APP_URL=' . $url);
        try { url_base_aplicacao(); throw new LogicException('URL inválida aceita.'); }
        catch (RuntimeException $erro) { $verificacoes++; }
    }
} finally { putenv($anterior === false ? 'STOCKSENSE_APP_URL' : 'STOCKSENSE_APP_URL=' . $anterior); }
$header = file_get_contents(__DIR__ . '/../arduino/esp32_hcsr04/ca_cert.h');
preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $header, $certificados);
$esperadas = ['96bcec06264976f37460779acf28c5a7cfe8a3c0aae11a8ffcee05c0bddf08c6','69729b8e15a86efc177a57afb7171dfc64add28c2fca8cf1507e34453ccb1470'];
conferir_sensor(count($certificados[0]) === 2, 'Devem existir duas raízes públicas no firmware.');
foreach ($certificados[0] as $i => $pem) conferir_sensor(openssl_x509_fingerprint($pem, 'sha256') === $esperadas[$i], 'CA pública modificada.');
echo "OK: $verificacoes verificações de percentual, calibração, URL e raízes TLS.\n";
