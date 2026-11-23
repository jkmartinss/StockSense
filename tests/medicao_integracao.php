<?php
// Executar NA VM, com o mesmo banco e a URL pública do site. Não usa sessões forjadas.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
if (!extension_loaded('curl')) exit("O teste requer a extensão curl do PHP.\n");
require __DIR__ . '/../database/conexao.php';
$base = rtrim($argv[1] ?? (getenv('STOCKSENSE_TEST_BASE_URL') ?: ''), '/') . '/';
if (!filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['http','https'], true)) {
    exit("Uso: php tests/medicao_integracao.php https://stocksense.147.15.2.73.sslip.io\nExecute no servidor usando o mesmo banco do site.\n");
}
$empresas = [];
$usuarios = [];
$clientes = [];
$checks = 0;
function conferir($condicao, $mensagem): void {
    global $checks;
    if (!$condicao) throw new RuntimeException($mensagem);
    $checks++;
}
function chamar(string $path, ?array $dados = null, ?string $token = null, ?int $pessoa = 0): array {
    global $base, $clientes;
    $headers = ['Accept: application/json'];
    if ($token !== null) $headers[] = 'X-Device-Token: ' . $token;
    $c = $pessoa === null ? curl_init() : ($clientes[$pessoa] ??= curl_init());
    $opcoes = [CURLOPT_URL => $base . $path, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2];
    if ($pessoa !== null) $opcoes[CURLOPT_COOKIEFILE] = ''; // cookies só em memória
    if ($dados !== null) { $opcoes[CURLOPT_POST] = true; $opcoes[CURLOPT_POSTFIELDS] = http_build_query($dados); }
    else $opcoes[CURLOPT_HTTPGET] = true;
    if ($ca = getenv('STOCKSENSE_TEST_CA_FILE')) $opcoes[CURLOPT_CAINFO] = $ca;
    curl_setopt_array($c, $opcoes);
    $body = curl_exec($c); $erro = curl_error($c); $status = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    if ($pessoa === null) curl_close($c);
    if ($body === false) throw new RuntimeException('Falha HTTP/TLS: ' . $erro);
    return [$status, json_decode($body, true), $body];
}
function entrar(int $pessoa): string {
    global $usuarios;
    [$status] = chamar('login.php', ['email' => $usuarios[$pessoa]['email'], 'senha' => $usuarios[$pessoa]['senha']], null, $pessoa);
    conferir($status === 302, 'Login real falhou. A URL pública e a CLI precisam usar o mesmo banco.');
    [$status, , $body] = chamar('index.php?pagina=cadastrar_galpao', null, null, $pessoa);
    conferir($status === 200 && preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $m), 'Obter CSRF da sessão HTTP real. Execute antes o atualizador na VM.');
    return $m[1];
}
try {
    $sufixo = bin2hex(random_bytes(8));
    for ($i=0; $i<2; $i++) {
        $nome = 'Teste temporário StockSense ' . $sufixo . '-' . $i;
        $s = $conexao->prepare('INSERT INTO empresas (nome) VALUES (?)');
        $s->bind_param('s', $nome); $s->execute(); $empresas[$i] = $conexao->insert_id;
        $email = 'teste-' . $sufixo . '-' . $i . '@stocksense.example';
        $senha = bin2hex(random_bytes(24)); $hash = password_hash($senha, PASSWORD_DEFAULT);
        $s = $conexao->prepare("INSERT INTO usuarios (empresa_id,nome,email,senha,cargo) VALUES (?,'Teste integração',?,?,'Administrador')");
        $s->bind_param('iss', $empresas[$i], $email, $hash); $s->execute();
        $usuarios[$i] = ['email' => $email, 'senha' => $senha];
    }
    $conexao->query("INSERT INTO galpoes (empresa_id,nome,metros_cubicos) VALUES ({$empresas[0]},'Teste',1)");
    $galpao = $conexao->insert_id;
    $conexao->query("INSERT INTO prateleiras (galpao_id,codigo,metros_cubicos) VALUES ($galpao,'TESTE',0.0124752)");
    $prateleira = $conexao->insert_id;
    $csrf = entrar(0); $csrfOutro = entrar(1);
    $dados = ['prateleira_id' => $prateleira, 'csrf_token' => $csrf];
    conferir(chamar('api/medicao.php', null, null, null)[0] === 401, 'Bloquear API sem login.');
    conferir(chamar('api/medicao.php', array_merge($dados, ['csrf_token' => 'invalido']))[0] === 403, 'Bloquear CSRF inválido.');
    conferir(chamar('api/medicao.php', array_merge($dados, ['csrf_token' => $csrfOutro]), null, 1)[0] === 404, 'Isolar solicitação por empresa.');
    conferir(chamar('api/medicao.php', $dados)[0] === 409, 'Exigir sensor ativo na prateleira.');
    $cadastro = $dados + ['nome' => 'ESP32 de teste'];
    conferir(chamar('actions/salvar_sensor.php', $cadastro)[1]['sucesso'] ?? false, 'Cadastrar sensor pela interface.');
    conferir(chamar('actions/salvar_sensor.php', $cadastro)[0] === 422, 'Impedir dois sensores ativos na mesma prateleira.');
    $sensor = $conexao->query("SELECT id,token FROM dispositivos WHERE prateleira_id=$prateleira")->fetch_assoc();
    $dispositivo = (int) $sensor['id']; $token = $sensor['token'];
    conferir(chamar('api/medicao_dispositivo.php', null, 'invalido', null)[0] === 401, 'Validar token da placa.');
    conferir(chamar('api/medicao_dispositivo.php', null, strtoupper($token), null)[0] === 401, 'Token diferencia maiúsculas e minúsculas.');
    conferir(trim(chamar('api/medicao_dispositivo.php', null, $token, null)[2]) === '0', 'Sem pedidos: aguardar sem medir.');
    conferir($conexao->query("SELECT ultimo_contato_em FROM dispositivos WHERE id=$dispositivo")->fetch_row()[0] !== null, 'Registrar contato da placa.');
    [$status, $pedido] = chamar('api/medicao.php', $dados);
    conferir($status === 200, 'Criar pedido.'); $id = $pedido['pedido_id'];
    conferir(chamar('api/medicao.php', $dados)[1]['pedido_id'] === $id, 'Reaproveitar clique duplicado.');
    conferir((int) chamar('api/medicao_dispositivo.php', null, $token, null)[2] === $id, 'Entregar pedido ao ESP32.');
    $leitura = ['pedido_id' => $id, 'distancia_cm' => 13.1];
    conferir(chamar('api/medicao_dispositivo.php', $leitura + ['percentual_ocupado' => 99], $token, null)[0] === 201, 'Receber distância e calcular percentual no servidor.');
    conferir(chamar('api/medicao_dispositivo.php', $leitura, $token, null)[0] === 200, 'Reenvio idempotente.');
    conferir((int) $conexao->query("SELECT COUNT(*) FROM leituras WHERE dispositivo_id=$dispositivo")->fetch_row()[0] === 1, 'Não duplicar leitura.');
    $resultado = chamar('api/medicao.php?pedido_id=' . $id)[1];
    conferir($resultado['estado'] === 'concluido' && (float) $resultado['percentual_ocupado'] === 50.0, 'Mostrar resultado calibrado.');
    conferir(chamar('api/medicao.php?pedido_id=' . $id, null, null, 1)[0] === 404, 'Isolar resultado de outra empresa.');
    conferir(chamar('api/medicao_dispositivo.php', ['pedido_id' => 0,'distancia_cm' => 20], $token, null)[0] === 404, 'Rejeitar pedido inexistente.');
    $id = chamar('api/medicao.php', $dados)[1]['pedido_id'];
    conferir(chamar('api/medicao_dispositivo.php', ['pedido_id' => $id,'distancia_cm' => 1001], $token, null)[0] === 422, 'Rejeitar distância fora do intervalo.');
    conferir(chamar('api/medicao_dispositivo.php', ['pedido_id' => $id,'erro' => 'sem_eco'], $token, null)[0] === 201, 'Receber falha do sensor.');
    conferir(chamar('api/medicao.php?pedido_id=' . $id)[1]['estado'] === 'erro', 'Exibir falha do sensor.');
    $id = chamar('api/medicao.php', $dados)[1]['pedido_id'];
    $conexao->query("UPDATE pedidos_medicao SET expira_em=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=$id");
    conferir(chamar('api/medicao.php?pedido_id=' . $id)[1]['estado'] === 'expirado', 'Exibir expiração.');
    conferir(chamar('api/medicao_dispositivo.php', ['pedido_id' => $id,'distancia_cm' => 20], $token, null)[0] === 409, 'Rejeitar leitura atrasada.');
    $calibracao = ['csrf_token' => $csrf,'dispositivo_id' => $dispositivo,'distancia_vazia_cm' => 30,'distancia_cheia_cm' => 10];
    conferir(chamar('actions/calibrar_sensor.php', $calibracao)[1]['sucesso'] ?? false, 'Salvar calibração pela interface.');
    conferir(chamar('actions/calibrar_sensor.php', array_merge($calibracao, ['distancia_cheia_cm' => 30]))[0] === 422, 'Rejeitar calibração sem intervalo de medição.');
    $id = chamar('api/medicao.php', $dados)[1]['pedido_id'];
    conferir(chamar('api/medicao_dispositivo.php', ['pedido_id' => $id,'distancia_cm' => 20], $token, null)[0] === 201, 'Medir com nova calibração.');
    conferir((float) chamar('api/medicao.php?pedido_id=' . $id)[1]['percentual_ocupado'] === 50.0, 'Usar calibração salva no banco.');
    $produto = ['csrf_token' => $csrf,'prateleira_id' => $prateleira,'produto' => 'Bloco de teste','setor' => 'Teste','volume_m3' => '0.0062376','data_entrada' => date('Y-m-d'),'observacao' => 'Teste temporário'];
    conferir(chamar('actions/cadastrar_ocupacao.php', $produto)[1]['sucesso'] ?? false, 'Confirmar ocupação com volume medido.');
    $csrf = entrar(0); $dados['csrf_token'] = $csrf;
    conferir((int) $conexao->query("SELECT COUNT(*) FROM ocupacoes WHERE prateleira_id=$prateleira AND data_saida IS NULL")->fetch_row()[0] === 1, 'Persistir ocupação depois de entrar novamente.');
    $alteracao = ['csrf_token' => $csrf,'dispositivo_id' => $dispositivo,'acao' => 'renovar_token'];
    conferir(chamar('actions/alterar_sensor.php', array_merge($alteracao, ['csrf_token' => $csrfOutro]), null, 1)[0] === 403, 'Bloquear alteração de sensor alheio.');
    conferir(chamar('actions/alterar_sensor.php', $alteracao)[1]['sucesso'] ?? false, 'Renovar token.');
    conferir(chamar('api/medicao_dispositivo.php', null, $token, null)[0] === 401, 'Revogar token antigo.');
    $token = $conexao->query("SELECT token FROM dispositivos WHERE id=$dispositivo")->fetch_row()[0];
    [$status, , $config] = chamar('actions/baixar_config_sensor.php', ['csrf_token' => $csrf,'dispositivo_id' => $dispositivo]);
    conferir($status === 200 && strpos($config, $token) !== false && strpos($config, $base . 'api/medicao_dispositivo.php') !== false, 'Baixar config.h com URL e token corretos.');
    conferir(chamar('actions/baixar_config_sensor.php', ['csrf_token' => $csrfOutro,'dispositivo_id' => $dispositivo], null, 1)[0] === 403, 'Não revelar token a outra empresa.');
    conferir(chamar('actions/alterar_sensor.php', array_merge($alteracao, ['acao' => 'desativar']))[1]['sucesso'] ?? false, 'Desativar sensor.');
    conferir(chamar('api/medicao_dispositivo.php', null, $token, null)[0] === 401, 'Bloquear sensor desativado.');
    conferir(chamar('api/medicao.php', $dados)[0] === 409, 'Não solicitar medição a sensor desativado.');
    conferir(chamar('actions/alterar_sensor.php', array_merge($alteracao, ['acao' => 'ativar']))[1]['sucesso'] ?? false, 'Reativar sensor.');
    $id = chamar('api/medicao.php', $dados)[1]['pedido_id'];
    conferir(chamar('actions/alterar_sensor.php', $alteracao)[1]['sucesso'] ?? false, 'Renovar token com pedido pendente.');
    conferir(chamar('api/medicao.php?pedido_id=' . $id)[1]['estado'] === 'erro', 'Cancelar pedido anterior após renovar token.');
    echo "OK: $checks verificações HTTP com login real, isolamento, sensores, pedidos, leituras, calibração e persistência.\n";
} finally {
    foreach ($empresas as $empresa) {
        $empresa = (int) $empresa;
        $conexao->query("DELETE FROM usuarios WHERE empresa_id=$empresa");
        $conexao->query("DELETE FROM empresas WHERE id=$empresa");
    }
    foreach ($clientes as $cliente) curl_close($cliente);
}
