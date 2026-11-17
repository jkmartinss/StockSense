<?php


require_once __DIR__ . "/../includes/funcoes.php";
require_once __DIR__ . "/../includes/auth.php";

exigir_login("../");
exigir_post_seguro();
require_once __DIR__ . "/../database/conexao.php";

if (!isset($_POST["id"])) {
    header("Location: ../index.php?pagina=ocupacoes");
    exit;
}

$id = (int) $_POST["id"];

exigir_propriedade(ocupacao_da_empresa($conexao, $id), "../", "ocupacoes");

// Buscar a ocupação.
$sql = "SELECT prateleira_id, data_saida
        FROM ocupacoes
        WHERE id = ?";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    redirecionar_com_erro("../index.php?pagina=ocupacoes", "Ocupação não encontrada.");
}

$ocupacao = $resultado->fetch_assoc();
$prateleira_id = (int) $ocupacao["prateleira_id"];
$conexao->begin_transaction();
$bloqueio = $conexao->prepare('SELECT id FROM prateleiras WHERE id=? FOR UPDATE');
$bloqueio->bind_param('i', $prateleira_id);
$bloqueio->execute();
$bloqueio->get_result()->fetch_assoc();
$atual = $conexao->prepare('SELECT data_saida FROM ocupacoes WHERE id=?');
$atual->bind_param('i', $id);
$atual->execute();
$ocupacao['data_saida'] = $atual->get_result()->fetch_assoc()['data_saida'] ?? null;

// Se já saiu, não registrar novamente.
if ($ocupacao["data_saida"] !== null) {
    $conexao->rollback();
    redirecionar_com_erro("../index.php?pagina=detalhes_prateleira&id=" .
        $prateleira_id
    , "Esta ocupação já foi retirada.");
}

// Registrar a saída.
$sql = "UPDATE ocupacoes
        SET data_saida = CURDATE()
        WHERE id = ?";

$stmt = $conexao->prepare($sql);
$stmt->bind_param("i", $id);

if (!$stmt->execute()) {
    $conexao->rollback();
    error_log("StockSense registrar_saida: " . $stmt->error);
    redirecionar_com_erro("../index.php?pagina=ocupacoes", "Não foi possível registrar a saída. Tente novamente.");
}

// Regra centralizada.
atualizarPercentualPrateleira($conexao, $prateleira_id);
$conexao->commit();

concluir_acao("../index.php?pagina=detalhes_prateleira&id=" .
    $prateleira_id
, "Saída registrada.");
