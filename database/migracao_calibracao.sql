ALTER TABLE dispositivos
    ADD COLUMN distancia_vazia_cm DECIMAL(8,2) NOT NULL DEFAULT 22.30,
    ADD COLUMN distancia_cheia_cm DECIMAL(8,2) NOT NULL DEFAULT 3.90;
