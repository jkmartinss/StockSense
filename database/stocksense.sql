-- =====================================================================
-- STOCKSENSE | TCC | BANCO COMPLETO PARA ENTREGA E APRESENTACAO
-- =====================================================================
-- Arquivo unico: estrutura atual + dados ficticios de demonstracao.
-- Compativel com MySQL 8 / MariaDB 10.4 (XAMPP), usando InnoDB e utf8mb4.
-- Inclui login multiempresa, estoque, sensores, calibracao e medicao manual.
--
-- COMO IMPORTAR
-- 1. Inicie o MySQL e importe SOMENTE este arquivo pelo phpMyAdmin.
-- 2. O banco stocksense deve estar ausente. Nenhum DROP ou TRUNCATE e usado.
--    Se ele ja existir, preserve um backup e use uma instalacao vazia ou
--    altere o nome nas instrucoes CREATE DATABASE e USE abaixo.
-- 3. Nao e necessario importar migracoes nem executar atualizar.php depois.
--
-- ACESSOS (senha de demonstracao de TODAS as contas: 123456)
-- Reptec: admin@reptec.com | operador@reptec.com | tecnico@reptec.com
-- Nortec: admin@nortec.com (demonstra isolamento entre empresas)
-- Demais administradores: veja INSERT INTO usuarios, neste arquivo.
-- As senhas sao hashes bcrypt aceitos por password_verify() do PHP.
-- Empresas adicionais, contatos, CNPJs e movimentacoes sao dados ficticios.
-- Os cargos sao descritivos; o sistema ainda nao restringe acoes por cargo.
--
-- ROTEIRO REPTEC
-- Galpao A: A1 parcial (40%), A2 livre, B1 em atencao (81,25%),
--           A3 parcial (25%) e A4 livre.
-- Galpao B: C1 cheia (100%), C2 parcial (60%), C3 em atencao (90%) e
--           C4 inativa, com historico de retirada.
-- Galpao Maquete: MAQ-01 livre, capacidade 0,0124752 m3 (12,4752 L),
--                pronta para registrar uma nova ocupacao com o ESP32.
-- O historico inclui entradas ativas e produtos ja retirados.
--
-- SENSORES
-- Os 10 dispositivos sao exemplos, nao representam placas conectadas.
-- Para a maquete, configure o TOKEN do config.h com o valor do dispositivo
-- 1, vinculado a MAQ-01 (prateleira 12), e ajuste URL, Wi-Fi e calibracao.
-- Token de demonstracao: stocksense-demo-reptec-maquete-01-tcc-2026
-- Calibracao inicial: vazio 22,30 cm / cheio 3,90 cm.
-- Tokens e senhas de demonstracao devem ser substituidos em uso real.
-- No site, abra Sensores: renove o token e baixe config.h com o vinculo correto.
-- ultimo_contato_em inicia NULL: dados simulados nao indicam placa online.
-- Leituras sao simuladas e datadas relativamente ao momento da importacao.
-- Pedidos historicos: concluidos, com erro e pendentes ja expirados.
-- Nao ha pedido valido em andamento para atrapalhar o botao Medir agora.
--
-- QUANTIDADES
-- empresas: 10 | usuarios: 12 | galpoes: 12 | prateleiras: 20
-- ocupacoes: 24 | dispositivos: 10 | leituras: 20 | pedidos_medicao: 12
-- Ao final, uma consulta exibe as quantidades para conferencia.
-- =====================================================================

SET NAMES utf8mb4;

CREATE DATABASE stocksense CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE stocksense;

-- 1. Estrutura: tabelas pais antes das tabelas dependentes.

CREATE TABLE empresas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cnpj VARCHAR(18),
    email VARCHAR(150),
    telefone VARCHAR(20),
    endereco VARCHAR(255),
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    cargo VARCHAR(100),
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE galpoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    nome VARCHAR(80) NOT NULL,
    descricao VARCHAR(255),
    metros_cubicos DECIMAL(15,7) DEFAULT 0.00,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_galpoes_empresa (empresa_id),
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE prateleiras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    galpao_id INT UNSIGNED NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    descricao VARCHAR(255),
    metros_cubicos DECIMAL(15,7) DEFAULT 0.00,
    setor VARCHAR(100),
    pct_ocupado DECIMAL(5,2) DEFAULT 0.00,
    pct_sensor DECIMAL(5,2) DEFAULT NULL,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (galpao_id, codigo),
    INDEX idx_prateleiras_galpao (galpao_id),
    FOREIGN KEY (galpao_id) REFERENCES galpoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE ocupacoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prateleira_id INT UNSIGNED NOT NULL,
    produto VARCHAR(150) NOT NULL,
    setor VARCHAR(100) NOT NULL,
    volume_m3 DECIMAL(15,7) DEFAULT 0.00,
    data_entrada DATE NOT NULL,
    data_saida DATE DEFAULT NULL,
    observacao VARCHAR(255),
    usuario_id INT UNSIGNED,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ocupacoes_prateleira (prateleira_id, data_saida),
    FOREIGN KEY (prateleira_id) REFERENCES prateleiras(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE dispositivos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prateleira_id INT UNSIGNED NOT NULL,
    nome VARCHAR(80) NOT NULL,
    tipo_sensor VARCHAR(50) NOT NULL DEFAULT 'HC-SR04',
    token VARCHAR(128) NOT NULL UNIQUE,
    distancia_vazia_cm DECIMAL(8,2) NOT NULL DEFAULT 22.30,
    distancia_cheia_cm DECIMAL(8,2) NOT NULL DEFAULT 3.90,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_dispositivos_prateleira (prateleira_id),
    ultimo_contato_em DATETIME DEFAULT NULL,
    FOREIGN KEY (prateleira_id) REFERENCES prateleiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE leituras (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispositivo_id INT UNSIGNED NOT NULL,
    prateleira_id INT UNSIGNED NOT NULL,
    distancia_cm DECIMAL(10,2) NOT NULL,
    percentual_ocupado DECIMAL(5,2) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_leituras_dispositivo_data (dispositivo_id, criado_em),
    INDEX idx_leituras_prateleira_data (prateleira_id, criado_em),
    FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id) ON DELETE CASCADE,
    FOREIGN KEY (prateleira_id) REFERENCES prateleiras(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE pedidos_medicao (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispositivo_id INT UNSIGNED NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
    distancia_cm DECIMAL(10,2) DEFAULT NULL,
    percentual_ocupado DECIMAL(5,2) DEFAULT NULL,
    erro VARCHAR(150) DEFAULT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expira_em DATETIME NOT NULL,
    concluido_em DATETIME DEFAULT NULL,
    INDEX idx_pedido_dispositivo (dispositivo_id, estado, expira_em),
    FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Carga de demonstracao em transacao, com chaves estrangeiras ativas.
START TRANSACTION;

-- Dez empresas permitem avaliar o isolamento de estoque por login.
INSERT INTO empresas (id, nome, cnpj, email, telefone, endereco) VALUES
    (1, 'Reptec Ferramentas', '90000000000184', 'contato@reptec.com', '(14) 90000-0001', 'Rua de Demonstração, 100 - Ourinhos - SP'),
    (2, 'Nortec Distribuidora', '90000000000265', 'contato@nortec.com', '(14) 90000-0002', 'Rua de Demonstração, 200 - Mogi das Cruzes - SP'),
    (3, 'ValeFix Parafusos', '90000000000346', 'contato@valefix.example', '(14) 90000-0003', 'Rua de Demonstração, 300 - Assis - SP'),
    (4, 'Metalvia Componentes', '90000000000427', 'contato@metalvia.example', '(14) 90000-0004', 'Rua de Demonstração, 400 - Bauru - SP'),
    (5, 'AgroLume Suprimentos', '90000000000508', 'contato@agrolume.example', '(14) 90000-0005', 'Rua de Demonstração, 500 - Marília - SP'),
    (6, 'EletroPonto Materiais', '90000000000699', 'contato@eletroponto.example', '(14) 90000-0006', 'Rua de Demonstração, 600 - Botucatu - SP'),
    (7, 'Oficina Horizonte', '90000000000770', 'contato@horizonte.example', '(14) 90000-0007', 'Rua de Demonstração, 700 - Londrina - PR'),
    (8, 'EmbalaSul Logística', '90000000000850', 'contato@embalasul.example', '(14) 90000-0008', 'Rua de Demonstração, 800 - Cambará - PR'),
    (9, 'HidroVale Conexões', '90000000000931', 'contato@hidrovale.example', '(14) 90000-0009', 'Rua de Demonstração, 900 - Jacarezinho - PR'),
    (10, 'SeguraMais Equipamentos', '90000000001075', 'contato@seguramais.example', '(14) 90000-0010', 'Rua de Demonstração, 1000 - Santa Cruz do Rio Pardo - SP');

-- Os logins originais da Reptec e da Nortec foram mantidos.
INSERT INTO usuarios (id, empresa_id, nome, email, senha, cargo) VALUES
    (1, 1, 'Administrador Reptec Ferramentas', 'admin@reptec.com', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (2, 2, 'Administrador Nortec Distribuidora', 'admin@nortec.com', '$2y$10$PRwjIKLKLL0I.BcEDQ8lYOy0cvIaeAW0RZZ.zUvduQycgTuIKeWAi', 'Administrador'),
    (3, 3, 'Administrador ValeFix Parafusos', 'admin@valefix.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (4, 4, 'Administrador Metalvia Componentes', 'admin@metalvia.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (5, 5, 'Administrador AgroLume Suprimentos', 'admin@agrolume.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (6, 6, 'Administrador EletroPonto Materiais', 'admin@eletroponto.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (7, 7, 'Administrador Oficina Horizonte', 'admin@horizonte.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (8, 8, 'Administrador EmbalaSul Logística', 'admin@embalasul.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (9, 9, 'Administrador HidroVale Conexões', 'admin@hidrovale.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (10, 10, 'Administrador SeguraMais Equipamentos', 'admin@seguramais.example', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Administrador'),
    (11, 1, 'Operador Reptec', 'operador@reptec.com', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Operador'),
    (12, 1, 'Técnico Reptec', 'tecnico@reptec.com', '$2y$10$sPfAgNPVs/GEWYIkxLNmYeXn2jd3B44DZiKBTZNXJ5lvkPHRpW0YS', 'Técnico de sensores');

-- Galpoes originais preservados e uma unidade dedicada a maquete.
INSERT INTO galpoes (id, empresa_id, nome, descricao, metros_cubicos) VALUES
    (1, 1, 'Galpão A', 'Galpão principal de ferramentas', 500),
    (2, 1, 'Galpão B', 'Galpão secundário', 300),
    (3, 2, 'Centro de Distribuição', 'Unidade de Mogi das Cruzes', 800),
    (4, 1, 'Galpão Maquete', 'Demonstração do ESP32 e sensor HC-SR04 em escala reduzida', 0.05),
    (5, 3, 'Galpão ValeFix Parafusos', 'Unidade de demonstração - Assis - SP', 100),
    (6, 4, 'Galpão Metalvia Componentes', 'Unidade de demonstração - Bauru - SP', 120),
    (7, 5, 'Galpão AgroLume Suprimentos', 'Unidade de demonstração - Marília - SP', 140),
    (8, 6, 'Galpão EletroPonto Materiais', 'Unidade de demonstração - Botucatu - SP', 160),
    (9, 7, 'Galpão Oficina Horizonte', 'Unidade de demonstração - Londrina - PR', 180),
    (10, 8, 'Galpão EmbalaSul Logística', 'Unidade de demonstração - Cambará - PR', 200),
    (11, 9, 'Galpão HidroVale Conexões', 'Unidade de demonstração - Jacarezinho - PR', 220),
    (12, 10, 'Galpão SeguraMais Equipamentos', 'Unidade de demonstração - Santa Cruz do Rio Pardo - SP', 240);

-- A Reptec tem 10 prateleiras, incluindo a maquete e uma inativa.
INSERT INTO prateleiras (id, galpao_id, codigo, descricao, metros_cubicos, setor, ativo) VALUES
    (1, 1, 'A1', 'Prateleira superior esquerda', 5, 'Usinagem', 1),
    (2, 1, 'A2', 'Prateleira superior direita', 5, 'Usinagem', 1),
    (3, 1, 'B1', 'Prateleira inferior esquerda', 8, 'Manutenção', 1),
    (4, 2, 'C1', 'Prateleira do galpão B', 6, 'Almoxarifado', 1),
    (5, 3, 'D1', 'Corredor 1 - nível 1', 12, 'Expedição', 1),
    (6, 3, 'D2', 'Corredor 1 - nível 2', 12, 'Expedição', 1),
    (7, 1, 'A3', 'Ferramentas manuais e acessórios', 5, 'Ferramentas manuais', 1),
    (8, 1, 'A4', 'Reserva para novas entradas', 4, 'Recebimento', 1),
    (9, 2, 'C2', 'Ferramentas elétricas', 10, 'Ferramentas elétricas', 1),
    (10, 2, 'C3', 'Equipamentos de proteção individual', 10, 'Segurança', 1),
    (11, 2, 'C4', 'Prateleira desativada após retirada do lote', 4, 'Reserva técnica', 0),
    (12, 4, 'MAQ-01', 'Caixa da maquete - capacidade em escala real do protótipo', 0.0124752, 'Demonstração ESP32', 1),
    (13, 5, 'VF-01', 'Parafusos e fixadores', 8, 'Fixação', 1),
    (14, 6, 'MV-01', 'Componentes metálicos', 10, 'Metalurgia', 1),
    (15, 7, 'AG-01', 'Peças para manutenção agrícola', 12, 'Agrícola', 1),
    (16, 8, 'EP-01', 'Materiais elétricos', 6, 'Elétrica', 1),
    (17, 9, 'OH-01', 'Ferramentas de oficina', 5, 'Oficina', 1),
    (18, 10, 'ES-01', 'Embalagens para transporte', 15, 'Embalagem', 1),
    (19, 11, 'HV-01', 'Conexões hidráulicas', 8, 'Hidráulica', 1),
    (20, 12, 'SM-01', 'Equipamentos de segurança', 10, 'Segurança', 1);

SET @data_base = CURRENT_DATE();
SET @instante_base = CURRENT_TIMESTAMP();

-- Ocupacoes: produtos ativos e retiradas completas (nao consomem espaco).
INSERT INTO ocupacoes (id, prateleira_id, produto, setor, volume_m3, data_entrada, data_saida, observacao, usuario_id) VALUES
    (1, 1, 'Ferramentas de usinagem', 'Usinagem', 2, DATE_SUB(@data_base, INTERVAL 30 DAY), NULL, 'Lote inicial da Reptec', 1),
    (2, 3, 'Peças de reposição', 'Manutenção', 6.5, DATE_SUB(@data_base, INTERVAL 28 DAY), NULL, 'Prateleira em atenção: 81,25% ocupada', 1),
    (3, 5, 'Caixas para expedição', 'Expedição', 9, DATE_SUB(@data_base, INTERVAL 26 DAY), NULL, 'Pedido 4471 - estoque da Nortec', 2),
    (4, 4, 'Kits de manutenção industrial', 'Almoxarifado', 6, DATE_SUB(@data_base, INTERVAL 18 DAY), NULL, 'Capacidade completa: demonstração de prateleira cheia', 11),
    (5, 7, 'Jogos de chaves combinadas', 'Ferramentas manuais', 0.5, DATE_SUB(@data_base, INTERVAL 15 DAY), NULL, 'Primeiro lote da mesma prateleira', 11),
    (6, 7, 'Alicates e chaves de fenda', 'Ferramentas manuais', 0.75, DATE_SUB(@data_base, INTERVAL 12 DAY), NULL, 'Segundo lote: soma dos volumes na prateleira', 11),
    (7, 9, 'Furadeiras e parafusadeiras', 'Ferramentas elétricas', 6, DATE_SUB(@data_base, INTERVAL 10 DAY), NULL, 'Armazenamento parcial: 60%', 1),
    (8, 10, 'Capacetes, luvas e óculos de proteção', 'Segurança', 9, DATE_SUB(@data_base, INTERVAL 9 DAY), NULL, 'Estoque em atenção: 90%', 11),
    (9, 1, 'Brocas de aço rápido - lote anterior', 'Usinagem', 0.5, DATE_SUB(@data_base, INTERVAL 45 DAY), DATE_SUB(@data_base, INTERVAL 35 DAY), 'Lote retirado; não participa da ocupação atual', 1),
    (10, 2, 'Fresas de topo - pedido concluído', 'Usinagem', 1.25, DATE_SUB(@data_base, INTERVAL 40 DAY), DATE_SUB(@data_base, INTERVAL 20 DAY), 'Saída concluída; prateleira disponível', 11),
    (11, 11, 'Ferramentas transferidas para manutenção', 'Reserva técnica', 1, DATE_SUB(@data_base, INTERVAL 35 DAY), DATE_SUB(@data_base, INTERVAL 16 DAY), 'Histórico preservado de prateleira inativa', 1),
    (12, 12, 'Blocos de teste da maquete', 'Demonstração ESP32', 0.0031188, DATE_SUB(@data_base, INTERVAL 5 DAY), DATE_SUB(@data_base, INTERVAL 4 DAY), 'Demonstração anterior encerrada; caixa livre para a apresentação', 12),
    (13, 6, 'Embalagens para pedidos fracionados', 'Expedição', 2.4, DATE_SUB(@data_base, INTERVAL 8 DAY), NULL, 'Prateleira Nortec com 20% de ocupação', 2),
    (14, 5, 'Caixas do pedido 4400', 'Expedição', 1, DATE_SUB(@data_base, INTERVAL 40 DAY), DATE_SUB(@data_base, INTERVAL 30 DAY), 'Pedido já expedido pela Nortec', 2),
    (15, 13, 'Parafusos sextavados e arruelas', 'Fixação', 4, DATE_SUB(@data_base, INTERVAL 14 DAY), NULL, 'Lote de fixadores', 3),
    (16, 14, 'Suportes e chapas metálicas', 'Metalurgia', 7, DATE_SUB(@data_base, INTERVAL 13 DAY), NULL, 'Componentes separados por lote', 4),
    (17, 15, 'Filtros e correias agrícolas', 'Agrícola', 9.6, DATE_SUB(@data_base, INTERVAL 12 DAY), NULL, 'Atenção: 80% da capacidade', 5),
    (18, 16, 'Cabos e disjuntores', 'Elétrica', 1.5, DATE_SUB(@data_base, INTERVAL 11 DAY), NULL, 'Materiais para instalações', 6),
    (19, 17, 'Soquetes e torquímetros', 'Oficina', 5, DATE_SUB(@data_base, INTERVAL 10 DAY), NULL, 'Prateleira cheia', 7),
    (20, 18, 'Caixas dobráveis e plástico bolha', 'Embalagem', 6, DATE_SUB(@data_base, INTERVAL 9 DAY), NULL, 'Embalagens para transporte', 8),
    (21, 19, 'Joelhos, registros e adaptadores', 'Hidráulica', 2, DATE_SUB(@data_base, INTERVAL 8 DAY), NULL, 'Conexões por diâmetro', 9),
    (22, 20, 'Protetores auriculares e cintos de segurança', 'Segurança', 8.5, DATE_SUB(@data_base, INTERVAL 7 DAY), NULL, 'Atenção: 85% da capacidade', 10),
    (23, 13, 'Arruelas - lote anterior', 'Fixação', 1, DATE_SUB(@data_base, INTERVAL 35 DAY), DATE_SUB(@data_base, INTERVAL 25 DAY), 'Retirada concluída', 3),
    (24, 18, 'Caixas do embarque anterior', 'Embalagem', 2, DATE_SUB(@data_base, INTERVAL 30 DAY), DATE_SUB(@data_base, INTERVAL 20 DAY), 'Embarque finalizado', 8);

-- Dispositivos de exemplo; apenas o token escolhido deve ir para a placa.
INSERT INTO dispositivos (id, prateleira_id, nome, tipo_sensor, token, distancia_vazia_cm, distancia_cheia_cm, ativo) VALUES
    (1, 12, 'ESP32 Maquete Reptec', 'HC-SR04', 'stocksense-demo-reptec-maquete-01-tcc-2026', 22.3, 3.9, 1),
    (2, 1, 'Sensor demonstrativo Reptec A1', 'HC-SR04', 'stocksense-demo-reptec-sensor-02-tcc-2026', 22.3, 3.9, 1),
    (3, 2, 'Sensor demonstrativo Reptec A2', 'HC-SR04', 'stocksense-demo-reptec-sensor-03-tcc-2026', 22.3, 3.9, 1),
    (4, 3, 'Sensor demonstrativo Reptec B1', 'HC-SR04', 'stocksense-demo-reptec-sensor-04-tcc-2026', 22.3, 3.9, 1),
    (5, 4, 'Sensor demonstrativo Reptec C1', 'HC-SR04', 'stocksense-demo-reptec-sensor-05-tcc-2026', 22.3, 3.9, 1),
    (6, 7, 'Sensor demonstrativo Reptec A3', 'HC-SR04', 'stocksense-demo-reptec-sensor-06-tcc-2026', 22.3, 3.9, 1),
    (7, 8, 'Sensor demonstrativo Reptec A4', 'HC-SR04', 'stocksense-demo-reptec-sensor-07-tcc-2026', 22.3, 3.9, 1),
    (8, 9, 'Sensor demonstrativo Reptec C2', 'HC-SR04', 'stocksense-demo-reptec-sensor-08-tcc-2026', 22.3, 3.9, 1),
    (9, 10, 'Sensor demonstrativo Reptec C3', 'HC-SR04', 'stocksense-demo-reptec-sensor-09-tcc-2026', 22.3, 3.9, 1),
    (10, 5, 'Sensor demonstrativo Nortec D1', 'HC-SR04', 'stocksense-demo-nortec-sensor-10-tcc-2026', 22.3, 3.9, 1);

-- Duas leituras simuladas por dispositivo, em ordem cronologica.
INSERT INTO leituras (id, dispositivo_id, prateleira_id, distancia_cm, percentual_ocupado, criado_em) VALUES
    (1, 1, 12, 17.7, 25, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (2, 2, 1, 16.78, 30, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (3, 3, 2, 18.62, 20, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (4, 4, 3, 11.26, 60, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (5, 5, 4, 5.74, 90, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (6, 6, 7, 19.54, 15, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (7, 7, 8, 20.46, 10, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (8, 8, 9, 13.1, 50, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (9, 9, 10, 7.58, 80, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (10, 10, 5, 9.42, 70, DATE_SUB(@instante_base, INTERVAL 120 MINUTE)),
    (11, 1, 12, 22.3, 0, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (12, 2, 1, 14.9, 40.22, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (13, 3, 2, 22.3, 0, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (14, 4, 3, 7.35, 81.25, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (15, 5, 4, 3.9, 100, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (16, 6, 7, 17.7, 25, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (17, 7, 8, 22.3, 0, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (18, 8, 9, 11.26, 60, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (19, 9, 10, 5.74, 90, DATE_SUB(@instante_base, INTERVAL 60 MINUTE)),
    (20, 10, 5, 8.5, 75, DATE_SUB(@instante_base, INTERVAL 60 MINUTE));

-- Dez pedidos concluidos correspondem as leituras mais recentes.
INSERT INTO pedidos_medicao (id, dispositivo_id, estado, distancia_cm, percentual_ocupado, erro, criado_em, expira_em, concluido_em) VALUES
    (1, 1, 'concluido', 22.3, 0, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (2, 2, 'concluido', 14.9, 40.22, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (3, 3, 'concluido', 22.3, 0, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (4, 4, 'concluido', 7.35, 81.25, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (5, 5, 'concluido', 3.9, 100, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (6, 6, 'concluido', 17.7, 25, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (7, 7, 'concluido', 22.3, 0, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (8, 8, 'concluido', 11.26, 60, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (9, 9, 'concluido', 5.74, 90, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (10, 10, 'concluido', 8.5, 75, NULL, DATE_SUB(@instante_base, INTERVAL 3610 SECOND), DATE_SUB(@instante_base, INTERVAL 3580 SECOND), DATE_SUB(@instante_base, INTERVAL 3600 SECOND)),
    (11, 1, 'erro', NULL, NULL, 'Sensor sem retorno. Confira o posicionamento.', DATE_SUB(@instante_base, INTERVAL 3010 SECOND), DATE_SUB(@instante_base, INTERVAL 2980 SECOND), DATE_SUB(@instante_base, INTERVAL 3000 SECOND)),
    (12, 1, 'pendente', NULL, NULL, NULL, DATE_SUB(@instante_base, INTERVAL 60 SECOND), DATE_SUB(@instante_base, INTERVAL 30 SECOND), NULL);

-- 3. Percentuais derivados dos dados; saidas nao consomem capacidade.
UPDATE prateleiras p
SET pct_ocupado = COALESCE((
    SELECT ROUND(SUM(o.volume_m3) / NULLIF(p.metros_cubicos, 0) * 100, 2)
    FROM ocupacoes o
    WHERE o.prateleira_id = p.id AND o.data_saida IS NULL
), 0);

-- O cache do sensor recebe a mesma leitura usada nas telas do sistema.
UPDATE prateleiras p
SET pct_sensor = (
    SELECT l.percentual_ocupado FROM leituras l
    WHERE l.prateleira_id = p.id ORDER BY l.id DESC LIMIT 1
);

COMMIT;

-- 4. Conferencia: todas as oito tabelas devem ter pelo menos 10 registros.
SELECT 'empresas' AS tabela, COUNT(*) AS registros FROM empresas
UNION ALL SELECT 'usuarios', COUNT(*) FROM usuarios
UNION ALL SELECT 'galpoes', COUNT(*) FROM galpoes
UNION ALL SELECT 'prateleiras', COUNT(*) FROM prateleiras
UNION ALL SELECT 'ocupacoes', COUNT(*) FROM ocupacoes
UNION ALL SELECT 'dispositivos', COUNT(*) FROM dispositivos
UNION ALL SELECT 'leituras', COUNT(*) FROM leituras
UNION ALL SELECT 'pedidos_medicao', COUNT(*) FROM pedidos_medicao;
