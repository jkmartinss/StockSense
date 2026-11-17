CREATE TABLE IF NOT EXISTS pedidos_medicao (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispositivo_id INT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
    distancia_cm DECIMAL(10,2) DEFAULT NULL,
    percentual_ocupado DECIMAL(5,2) DEFAULT NULL,
    erro VARCHAR(150) DEFAULT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NOT NULL,
    concluido_em DATETIME DEFAULT NULL,
    FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id) ON DELETE CASCADE,
    INDEX idx_pedido_dispositivo (dispositivo_id, estado, expira_em)
);
