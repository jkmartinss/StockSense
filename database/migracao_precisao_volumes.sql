-- Preserva os valores existentes e permite volumes pequenos de maquetes.
ALTER TABLE galpoes MODIFY metros_cubicos DECIMAL(15,7) DEFAULT 0.00;
ALTER TABLE prateleiras MODIFY metros_cubicos DECIMAL(15,7) DEFAULT 0.00;
ALTER TABLE ocupacoes MODIFY volume_m3 DECIMAL(15,7) DEFAULT 0.00;
