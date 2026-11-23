<?php

function percentual_sensor(array $dispositivo, float $distancia): float
{
    $vazia = (float) $dispositivo['distancia_vazia_cm'];
    $cheia = (float) $dispositivo['distancia_cheia_cm'];
    if (!is_finite($distancia) || $distancia < 0 || $distancia > 1000
        || $cheia < 0.01 || $vazia > 1000 || $vazia <= $cheia) {
        throw new InvalidArgumentException('Confira a distância e a calibração do sensor.');
    }
    return round(max(0, min(100, ($vazia - $distancia) * 100 / ($vazia - $cheia))), 2);
}

function sensor_autenticado(mysqli $db, string $token): ?array
{
    if ($token === '' || strlen($token) > 128) return null;
    $s = $db->prepare('SELECT d.id, d.prateleira_id, d.distancia_vazia_cm, d.distancia_cheia_cm
        FROM dispositivos d JOIN prateleiras p ON p.id=d.prateleira_id
        JOIN galpoes g ON g.id=p.galpao_id JOIN empresas e ON e.id=g.empresa_id
        WHERE BINARY d.token=? AND d.ativo=1 AND p.ativo=1 AND g.ativo=1 AND e.ativo=1');
    $s->bind_param('s', $token); $s->execute();
    $dispositivo = $s->get_result()->fetch_assoc();
    if (!$dispositivo) return null;
    $id = (int) $dispositivo['id'];
    $s = $db->prepare('UPDATE dispositivos SET ultimo_contato_em=NOW()
        WHERE id=? AND (ultimo_contato_em IS NULL OR ultimo_contato_em<DATE_SUB(NOW(), INTERVAL 10 SECOND))');
    $s->bind_param('i', $id); $s->execute();
    return $dispositivo;
}
