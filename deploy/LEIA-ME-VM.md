# StockSense na VM e com o ESP32

URL: https://stocksense.147.15.2.73.sslip.io/

Esta versão prepara a aplicação e o firmware para o deploy. A publicação na VM
e a gravação na placa são etapas necessárias para validar o conjunto físico.

## Para o professor: atualizar a VM sem perder os cadastros

1. Faça um backup do banco atual e publique o código atualizado do repositório.
   Preserve `database/conexao.local.php` da VM. Não copie a configuração do
   computador dos alunos e não publique `arduino/esp32_hcsr04/config.h`.
2. Na pasta do projeto da VM, execute `php database/atualizar.php`.
   Ele cria o que estiver faltando, acrescenta `dispositivos.ultimo_contato_em`
   e mantém empresas, usuários, ocupações, sensores e tokens existentes.
   Pode ser executado novamente. O arquivo único `stocksense.sql` precisa
   acompanhar o código, pois fornece as definições das tabelas ao atualizador.
3. Configure `STOCKSENSE_APP_URL=https://stocksense.147.15.2.73.sslip.io`.
   Essa variável garante a URL HTTPS correta atrás de proxy e os cookies seguros.
   O PHP deve receber essa variável; um arquivo `.env` não é lido automaticamente.
   Em PHP-FPM, configure `env[STOCKSENSE_APP_URL] = https://stocksense.147.15.2.73.sslip.io`
   no pool, ou a forma equivalente do painel/container. Recarregue o serviço.
4. Mantenha a conexão do banco da VM. Pode usar `conexao.local.php` ou as variáveis
   `STOCKSENSE_DB_HOST`, `STOCKSENSE_DB_PORT` (padrão 3306), `STOCKSENSE_DB_USER`,
   `STOCKSENSE_DB_PASS`, `STOCKSENSE_DB_NAME`. O arquivo local tem prioridade.
   O PHP precisa de `mysqli` com mysqlnd, `curl` e `openssl`.
5. Bloqueie o acesso HTTP às pastas `database`, `includes`, `pages`, `arduino`, `tests`,
   `tools`, `deploy`, aos diretórios ocultos e aos arquivos de backup. As regras
   `.htaccess` incluídas atendem Apache 2.4 com AllowOverride AuthConfig FileInfo
   e mod_rewrite para bloquear diretórios ocultos;
   para Nginx veja `deploy/nginx.conf.example` e adapte à configuração existente.
   PHP continua podendo incluir esses arquivos e executá-los pela CLI.
6. Mantenha o MySQL em disco persistente. Se usar container, o diretório de dados
   do MySQL precisa de volume persistente; republicar o PHP não deve recriá-lo.
   A API precisa apenas de HTTPS público na porta 443. Não exponha a porta do MySQL
   para o ESP32. O proxy deve preservar o cabeçalho `X-Device-Token`, aceitar
   GET/POST em `/api/` e não exigir login de proxy, CAPTCHA ou cache nessas rotas.
7. Execute na VM:

   ```sh
   php tools/diagnostico.php https://stocksense.147.15.2.73.sslip.io
   php tests/estoque.php
   php tests/sensores.php
   php tests/estoque_integracao.php
   php tests/medicao_integracao.php https://stocksense.147.15.2.73.sslip.io
   ```

O diagnóstico é somente leitura. O teste de medição usa o mesmo banco do site,
cria duas empresas e contas temporárias, faz login HTTP real, simula a placa,
confere isolamento, token, pedidos, reenvio, erros, calibração, cadastro e
persistência de uma ocupação após novo login. Remove os registros temporários
ao terminar, inclusive em falhas tratáveis. Não depende de compartilhar arquivos
de sessão entre PHP CLI e servidor web. Se o processo for interrompido à força,
confira as empresas com nome `Teste temporário StockSense` antes de removê-las.
Não execute esse teste apontando uma URL que use um banco diferente da CLI.

## Qual SQL enviar e importar

Envie apenas `database/stocksense.sql`: oito tabelas, 120 registros e todas as
alterações necessárias. Ele não contém DROP DATABASE nem TRUNCATE.

- **VM já em uso:** publique o código e execute o atualizador. Não reimporte a carga.
- **Instalação nova com os dados da apresentação:** importe o SQL uma vez, com o
  banco `stocksense` ainda ausente. O atualizador não é necessário depois dessa carga.
- **Demonstração em paralelo ao banco existente:** o professor pode alterar as duas
  instruções `CREATE DATABASE stocksense` e `USE stocksense` para outro nome, importar
  a carga nesse banco novo e configurar o site para ele. Preserve o banco anterior.

Na carga nova, o acesso principal é `admin@reptec.com`, senha `123456`. A senha é
de demonstração. O banco antigo pode ter contas e IDs diferentes; não presuma que
a prateleira 12 ou o dispositivo 1 existam nele.

## Preparar a MAQ-01 da Reptec

1. Entre com uma conta da Reptec e abra **Sensores**. Confira **Galpão Maquete / MAQ-01**.
   Na carga nova, essa prateleira já existe. No banco antigo, se não existir,
   cadastre um galpão com pelo menos 0,0124752 m³ e uma prateleira MAQ-01 com essa
   capacidade. Essa é a capacidade de exemplo da caixa; ajuste às medidas reais.
2. Se não houver sensor, cadastre-o na tela. Se já houver, use o dispositivo
   existente. O site permite apenas um sensor habilitado por prateleira pelo cadastro.
3. Use **Renovar token** antes de usar um token de exemplo ou que já tenha sido
   compartilhado. Baixe **config.h** para o dispositivo escolhido. O download
   contém apenas a URL pública e o token da sua empresa; nenhuma senha do banco.
4. No computador que grava a placa, coloque o arquivo na pasta
   `arduino/esp32_hcsr04/`, ao lado do `.ino` e do `ca_cert.h`. Preencha o nome e
   a senha do Wi-Fi. Não compartilhe esse arquivo nem o inclua no Git/ZIP.
5. A rede precisa ter Wi-Fi de 2,4 GHz, acesso à internet, DNS, HTTPS (443) e
   sincronização NTP (UDP 123). Redes com portal de autenticação não funcionam
   somente com nome e senha no firmware. Um hotspot de celular compatível pode
   ser usado para testar o ESP32 fora da rede da VM.
6. Abra o `.ino` na Arduino IDE com o pacote **esp32 by Espressif Systems**.
   Use a placa correspondente ao modelo físico. O FCC ID informado identifica
   o módulo certificado; confirme o modelo da placa/porta na IDE para gravar.
   O firmware usa `WiFiClientSecure`, CA pública e NTP. Não usa `setInsecure()`.
   `ca_cert.h` inclui as raízes ISRG X1/X2 usadas pela cadeia HTTPS observada.
   Se o provedor mudar a autoridade certificadora, atualize as CAs confiáveis.
7. Grave a placa e abra o Monitor Serial em **115200 baud**. Confira energia,
   conexão Wi-Fi e ausência de erros HTTP/TLS. Tokens e senha não são impressos.
8. Em **Sensores**, use **Atualizar situação**. A placa deve aparecer **Online**
   após uma consulta autenticada, mesmo sem medir. Leituras simuladas não a tornam online.
9. Abra os detalhes de MAQ-01 e salve a calibração real: distância com a caixa
   vazia maior que a distância com a caixa cheia. O servidor calcula o percentual
   a partir da distância; não é preciso regravar para mudar a calibração.

### Ligação já usada pelo projeto

| Sinal | Ligação |
|---|---|
| TRIG | GPIO 26, ou o pino configurado no `config.h` |
| ECHO | GPIO 25 **através de adaptação para 3,3 V** |
| GND | Terra comum entre ESP32 e HC-SR04 |
| VCC | Alimentação conforme a versão do HC-SR04, geralmente 5 V |

Não ligue Echo de 5 V diretamente à entrada do ESP32. Um divisor resistivo
adequado, por exemplo 1 kΩ entre Echo e GPIO e 2 kΩ entre GPIO e GND, reduz
aproximadamente 5 V para 3,3 V. Confira a montagem e a tensão com o responsável
pelo hardware. O GND do sensor deve ir ao GND, e não ao 3V3.

## Ensaio da apresentação

1. Confirme que o sensor de MAQ-01 aparece Online. Entre em **Registrar ocupação**
   e selecione exatamente essa prateleira.
2. Coloque o objeto e clique em **Medir agora**. O ESP32 consulta pedidos a cada
   dois segundos; cada pedido tem validade de 30 segundos. A medição usa a mediana
   de cinco amostras, exigindo pelo menos três retornos válidos.
3. Confira a distância no Serial e a estimativa no site. A conta apresentada é
   volume total medido menos volume já cadastrado.
4. Informe produto, setor e data e confirme a ocupação. Medir sozinho registra
   uma leitura; confirmar o formulário registra o produto no estoque.
5. Saia e entre novamente: a ocupação deve continuar no banco. Mostre a leitura
   nos detalhes da prateleira e registre a saída para demonstrar o histórico.

Para testar só a API, o teste de integração simula o ESP32 e remove seus dados.
Ele não comprova a ligação elétrica, o Wi-Fi ou a medição da placa física.

## Falhas mais comuns

| Resultado | Conferir |
|---|---|
| HTTP 401 no Serial | Token igual ao download; sensor, prateleira, galpão e empresa ativos |
| HTTP 301/302 | URL HTTPS final, sem redirecionar para login ou outra rota |
| HTTP 404 | Arquivos atualizados, raiz do deploy e URL pública no `config.h` |
| HTTP 500 | Log PHP, conexão correta e execução do atualizador |
| Erro TLS | Relógio NTP, nome do host, certificado válido e cadeia completa no proxy |
| Sem contato recente | Placa desligada, Wi-Fi, internet ou configuração diferente do sensor exibido |
| Medição expirada | Placa ainda não consultou o pedido; confira Serial e rede |
| Sensor sem eco | Alimentação, GND comum, TRIG/ECHO, divisor e posicionamento |
| Volume novo zero | Caixa vazia ou volume medido não excede o já registrado |

## Entrega sem credenciais

Execute `php tools/empacotar.php` para gerar
`.test-tools/StockSense-deploy.zip`. O empacotador exclui `config.h`,
`conexao.local.php`, `.env`, `.git`, dependências de teste, logs e backups.
O professor pode continuar usando o GitHub; o ZIP é uma alternativa de transferência.
Troque credenciais que tenham sido expostas em ZIPs antigos nos serviços respectivos.

Cadastrar, editar, excluir e medir pelo site grava no MySQL e não exige novo deploy.
Código PHP/JS/CSS exige republicação. Alterar a estrutura exige o atualizador.
Alterar URL/token/Wi-Fi do ESP32 exige gravar a configuração atualizada na placa.
