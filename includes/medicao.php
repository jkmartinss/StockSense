<?php

function medicao_json($status, array $dados)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_exception_handler(function ($erro) {
    error_log('StockSense medicao: ' . $erro->getMessage());
    medicao_json(500, ['ok' => false, 'erro' => 'Não foi possível comunicar com o sensor. Tente novamente.']);
});
