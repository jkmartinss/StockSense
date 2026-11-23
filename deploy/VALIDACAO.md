# Verificações da versão — 23/11/2026

| Verificação | Resultado |
|---|---|
| Importação do SQL em MariaDB 10.4.32 isolado | Aprovada: 27 verificações de estrutura, contagens, vínculos, volumes, percentuais, leituras e datas |
| Carga de apresentação | Oito tabelas e 120 registros, pelo menos dez por tabela |
| Atualizador na estrutura atual | Aprovado em duas execuções consecutivas |
| Atualizador em estrutura antiga sem sensores/pedidos e com menor precisão | Aprovado; tabelas criadas e dados anteriores mantidos |
| Integração PHP/MySQL/HTTP em instalação nova | Aprovada: 47 verificações com login HTTP real |
| Integração após atualizar estrutura antiga | Aprovada: as mesmas 47 verificações |
| Cálculos, limites e indicadores de estoque por empresa | Aprovados nos testes unitários e de integração de leitura |
| Calibração, URL pública e integridade das raízes CA | Aprovadas: 22 verificações em `tests/sensores.php` |
| Sintaxe PHP e JavaScript | Aprovada nos arquivos verificados |
| Pacote de entrega | Um SQL, sem `config.h`, `conexao.local.php`, `.env` ou metadados Git |

Os testes de integração verificaram criação e ativação do sensor, isolamento entre
empresas, CSRF, autenticação por token com distinção de maiúsculas, atualização de
contato, pedido duplicado, reenvio idempotente, expiração, ausência de eco, calibração,
revogação de token, download do `config.h`, registro de ocupação e persistência após
novo login. Foram executados com PHP 8.1.25 e MariaDB 10.4.32 em cópias isoladas.

## Etapas que ainda precisam ser executadas

- Publicação desta versão e importação/atualização do banco na VM do professor.
- Compilação e gravação do firmware na Arduino IDE, usando a placa física correta.
  O compilador ESP32 não pôde ser executado no ambiente desta revisão.
- Ensaio com ESP32, HC-SR04 e o site atualizado na internet. O teste HTTP simula
  o protocolo do dispositivo; não comprova montagem, Wi-Fi ou precisão física.
- Testes de interface no navegador. Os testes Playwright foram preparados, mas
  o Edge não iniciou corretamente neste ambiente; isso não conta como aprovação
  dos testes visuais. A sintaxe JavaScript e os fluxos HTTP foram verificados.

Não houve publicação de código ou alteração do banco de dados da VM nesta revisão.
