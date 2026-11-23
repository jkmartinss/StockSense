# StockSense

Sistema Inteligente de Gestão e Monitoramento de Estoque.
Projeto de Trabalho de Conclusão de Curso.

## Deploy na VM e apresentação com sensor

As instruções completas estão em [deploy/LEIA-ME-VM.md](deploy/LEIA-ME-VM.md).
O código usa HTTPS com validação de certificado no ESP32. No site, o menu
**Sensores** permite cadastrar o dispositivo, conferir sua prateleira,
verificar contato recente, renovar o token e baixar o `config.h`.
Na apresentação, use **Reptec → Galpão Maquete → MAQ-01**.

Para atualizar uma VM que já contém dados, publique o código e execute
`php database/atualizar.php`. Não reimporte a carga inicial sobre o banco.
Configure `STOCKSENSE_APP_URL=https://stocksense.147.15.2.73.sslip.io`
no ambiente do PHP. O banco deve permanecer em disco persistente.

## Tecnologias

- PHP 8 (procedural, com `mysqli` e prepared statements)
- MySQL / MariaDB
- HTML5, CSS3 (design system próprio, sem framework)
- JavaScript puro
- Font Awesome 6 (ícones, via CDN)

## Estrutura

```
StockSense/
├── index.php              Roteador: define página, título e CSS de cada rota
├── database/
│   ├── conexao.php        Conexão mysqli (utf8mb4)
│   ├── stocksense.sql     Único SQL: banco completo + dados da apresentação
│   └── atualizar.php      Atualiza bancos existentes sem apagar os dados
├── login.php              Tela de login
├── cadastro.php           Cadastro de nova empresa + usuário admin
├── logout.php             Encerra a sessão
├── includes/
│   ├── auth.php           Sessão, login e verificações de propriedade
│   ├── funcoes.php        Helpers de formatação e a regra de ocupação
│   ├── sidebar.php        Menu lateral
│   ├── header.php         Barra superior
│   └── footer.php         Rodapé
├── pages/                 Telas (só exibição; recebem dados e renderizam)
├── actions/               Gravações no banco (INSERT/UPDATE/DELETE + redirect)
└── assets/
    ├── css/
    │   ├── base.css       Design system: tokens, layout e componentes
│   ├── acesso.css     Telas de login/cadastro e caixas de aviso
    │   ├── dashboard.css  Exclusivo do dashboard (KPIs e mapa)
    │   └── detalhes.css   Exclusivo das telas de detalhe
    └── js/app.js          Relógio, busca, menu mobile, confirmações
```

## Como executar

1. Inicie o Apache e o MySQL (XAMPP, WAMP ou similar).
2. Copie a pasta `StockSense` para o diretório público (`htdocs`).
3. Importe somente `database/stocksense.sql` no phpMyAdmin. O script cria o
   banco `stocksense`, as oito tabelas e todos os dados da apresentação.
   O banco deve estar ausente: o arquivo não apaga nem substitui um banco existente.
4. Acesse `http://localhost/StockSense/` e entre com um dos acessos de
   demonstração listados abaixo.

> Para atualizar a estrutura de um banco existente, execute
> `php database/atualizar.php`. O atualizador aplica as mudanças de sensores,
> precisão, medição manual, calibração e índices sem inserir dados de exemplo.

No XAMPP, as configurações padrão já funcionam. Em outro servidor, use `database/conexao.local.php` a partir do arquivo de exemplo.

## Organização do CSS

Todo estilo compartilhado (botões, tabelas, cards, formulários, etiquetas de
status e barras de progresso) fica em **`base.css`**, com as cores e medidas
declaradas como variáveis CSS no `:root`. Os arquivos por página contêm apenas
o que é exclusivo daquela tela.

Consequência prática: mudar a cor principal do sistema é editar `--azul` em um
lugar só, e a mesma classe nunca é redefinida em dois arquivos diferentes.

### Níveis de ocupação

As faixas são definidas em um único ponto — `status_ocupacao()` em
`includes/funcoes.php` — e reutilizadas no dashboard, nas listagens e nos
detalhes:

| Percentual | Nível     | Cor      |
|-----------:|-----------|----------|
| 0%         | `livre`   | Verde    |
| 1–79%      | `parcial` | Azul     |
| 80–99%     | `atencao` | Amarelo  |
| 100%       | `cheia`   | Vermelho |

## Funcionalidades

- Dashboard com indicadores, taxa de ocupação e mapa visual das prateleiras
- Cadastro, edição, exclusão e detalhamento de galpões
- Cadastro, edição, exclusão e detalhamento de prateleiras
- Registro de ocupações, com validação da capacidade da prateleira
- Registro de saída de produtos, com histórico de entradas e retiradas
- Recálculo automático do percentual de ocupação a cada movimentação
- Busca instantânea nas listagens
- Layout responsivo (menu lateral retrátil no celular)

### Melhorias de operação

- Busca por múltiplos termos, ignorando acentos, combinada com filtros de galpão, situação e status nas listagens. O botão **Limpar filtros** restaura a lista.
- Cabeçalhos clicáveis ordenam texto, números e datas; um segundo clique inverte a ordem. Ações ficam fora da ordenação.
- Salvamentos, exclusões e saídas mostram notificações e atualizam o conteúdo sem recarregar a página inteira. Erros mantêm os campos preenchidos; envios simultâneos são bloqueados.
- O dashboard reúne filtros por galpão, indicadores, mapa do estoque e o botão **Registrar ocupação**. Saídas são registradas em **Ocupações**; cadastros e consultas ficam nas respectivas páginas do menu lateral.
- Validação durante a digitação, incluindo campos obrigatórios, valores positivos e limites de capacidade disponíveis nos formulários. O servidor continua validando as regras de negócio.
- O botão **Tema** no cabeçalho alterna os modos claro/escuro. A preferência fica salva no navegador; no primeiro acesso, acompanha o sistema operacional.
- Tabelas largas têm rolagem horizontal própria, acessível por teclado, sem alargar a página no celular.
- O painel concentra a ocupação em um único indicador; o mapa informa situação, volume ocupado e capacidade por prateleira. Galpões sem prateleiras recebem uma indicação própria.
- A ocupação é calculada pelo **volume de produtos ainda armazenados dividido pela capacidade das prateleiras ativas**. O painel inclui apenas galpões ativos; listas e detalhes preservam o acesso aos registros inativos e ao histórico. A capacidade declarada do galpão é identificada separadamente.
- Filtros e seletores dos formulários usam listas com o tema do sistema nos navegadores com suporte a `appearance: base-select`; os demais mantêm o seletor nativo funcional.
- As confirmações de saída e exclusão usam uma janela do próprio site, com foco inicial em **Cancelar** e fechamento por Escape. Formulários mostram campos obrigatórios e espaço disponível ao escolher galpão ou prateleira.

As ações de alteração usam POST e token de sessão. Sem JavaScript, os formulários continuam enviando normalmente e exibem mensagens após o redirecionamento; busca dinâmica, ordenação e janela de confirmação dependem de JavaScript.

### Verificação das melhorias

`tests/ui.cjs` executa testes de interface com Playwright e Edge em modo headless, usando respostas HTTP simuladas. Requer o pacote `playwright` disponível no ambiente Node. Execute `node tests/ui.cjs`; para outro navegador instalado, configure `BROWSER_CHANNEL` conforme o canal do Playwright.

Os testes cobrem filtros combinados, acentos, ordenação, validação, respostas de sucesso/erro, confirmação, persistência de tema e larguras de 360, 768 e 1280 pixels. Eles **não validam a integração PHP/MySQL**. Em um ambiente PHP 8 com o banco configurado, confira também cadastro/edição dos três tipos de registro, limites de capacidade, saída, exclusão e isolamento entre empresas. Faça isso com dados de teste.

`php tests/estoque.php` verifica cálculo ponderado, prateleiras inativas, estoque vazio, limites e pluralização. `php tests/estoque_integracao.php` compara os indicadores com consultas independentes, somente de leitura, no banco configurado. Este último só roda pela CLI e usa `database/conexao.local.php` ou as variáveis `STOCKSENSE_DB_HOST`, `STOCKSENSE_DB_USER`, `STOCKSENSE_DB_PASS` e `STOCKSENSE_DB_NAME`; não cria nem altera registros.

`php tests/sensores.php` verifica os cálculos de calibração, URLs e integridade
das raízes TLS do firmware. `tests/sensores_ui.cjs` verifica a tela de sensores
e o download de configuração com Playwright. Para renderizar as páginas PHP
nos testes de interface, use uma cópia e um banco de testes; as variáveis
`STOCKSENSE_TEST_ROOT`, `STOCKSENSE_TEST_SESSION_PATH` e `PHP_BINARY` permitem
indicar essa cópia, o diretório de sessões e o executável PHP.

## Autenticação e isolamento por empresa

O sistema é **multiempresa**: cada empresa tem seus próprios galpões,
prateleiras e ocupações, e um usuário só enxerga os dados da empresa à qual
pertence.

### Como funciona

1. `login.php` valida o e-mail e a senha com `password_verify()`.
2. Em caso de sucesso, a sessão guarda `usuario_id` e `empresa_id`.
3. `index.php` chama `exigir_login()` — nenhuma página abre sem sessão.
4. **Toda** consulta filtra por `empresa_id`. Prateleiras e ocupações chegam
   à empresa através do galpão:

```
empresas → galpoes.empresa_id → prateleiras.galpao_id → ocupacoes.prateleira_id
```

### Duas camadas de proteção

Filtrar apenas as listagens não basta: sem verificação, bastaria trocar o
`?id=` na URL para abrir ou excluir o registro de outra empresa. Por isso:

- **Leitura:** as consultas das telas de detalhe já incluem
  `AND g.empresa_id = ?`, então o registro alheio simplesmente não é
  encontrado.
- **Escrita:** cada action chama `galpao_da_empresa()`,
  `prateleira_da_empresa()` ou `ocupacao_da_empresa()` antes de gravar
  (funções em `includes/auth.php`).

### Senhas

Armazenadas como hash **bcrypt** via `password_hash()` — nunca em texto puro,
nem nos dados de teste. O cadastro exige no mínimo 6 caracteres.

Outras medidas: `session_regenerate_id()` no login (contra fixação de sessão),
cookie de sessão `HttpOnly`, e mensagem de erro idêntica para e-mail
inexistente e senha errada (evita descobrir quais e-mails estão cadastrados).

### Acessos de demonstração

O script SQL cria dez empresas e doze usuários. Estes são os acessos
principais para demonstrar a Reptec e o isolamento entre empresas:

| E-mail             | Senha  | Empresa               |
|--------------------|--------|-----------------------|
| `admin@reptec.com` | 123456 | Reptec Ferramentas    |
| `admin@nortec.com` | 123456 | Nortec Distribuidora  |
| `operador@reptec.com` | 123456 | Reptec Ferramentas |
| `tecnico@reptec.com` | 123456 | Reptec Ferramentas |

Entre com uma conta, depois com a outra: o dashboard, os galpões e as
ocupações são completamente diferentes.

> Estas senhas existem apenas para teste. Em uso real, troque-as no primeiro
> acesso.

Novas empresas se cadastram sozinhas em `cadastro.php`, que cria a empresa e
seu usuário administrador dentro de uma transação.

### SQL único para entregar ao professor

Envie **apenas `database/stocksense.sql`**. Ele inclui todas as alterações
que antes estavam distribuídas em migrações, além de instruções, logins,
roteiro da Reptec e uma consulta final com a contagem de registros.

| Tabela | Registros |
|--------|----------:|
| `empresas` | 10 |
| `usuarios` | 12 |
| `galpoes` | 12 |
| `prateleiras` | 20 |
| `ocupacoes` | 24 |
| `dispositivos` | 10 |
| `leituras` | 20 |
| `pedidos_medicao` | 12 |
| **Total** | **120** |

A Reptec mantém seus acessos e os galpões A e B. Sua demonstração inclui
prateleiras livres, parciais, em atenção e cheias, dois lotes na mesma
prateleira, produtos já retirados e uma prateleira inativa com histórico.
As demais empresas têm usuários e estoque próprios. Contatos, CNPJs,
movimentações e leituras da carga são exemplos fictícios.

O **Galpão Maquete** tem a prateleira **MAQ-01** (ID 12), com capacidade de
**0,0124752 m³**, equivalente a **12,4752 litros**, inicialmente livre.
O dispositivo 1 está ligado a ela. Para usar a placa na apresentação,
abra **Sensores**, renove o token de demonstração, baixe o `config.h`,
preencha o Wi-Fi e confira
a capacidade da caixa e a calibração na tela de detalhes. Os valores iniciais
são 22,30 cm para vazio e 3,90 cm para cheio; adapte-os às medidas da maquete.
Os outros dispositivos são exemplos e só medem de verdade se houver uma
placa configurada com o token correspondente.

As datas são relativas ao momento da importação. Os pedidos de exemplo
incluem conclusões, erro de sensor e um pedido já expirado, sem bloquear
uma nova solicitação pelo botão **Medir agora**. Os cargos dos usuários são
descritivos e ainda não limitam as ações disponíveis na interface.

O arquivo cria uma instalação nova e não exporta o conteúdo do banco em
execução. Para conservar dados já cadastrados, mantenha o banco atual e use
o atualizador; para testar a carga completa em paralelo, altere o nome do
banco nas instruções `CREATE DATABASE` e `USE` do SQL e configure a conexão.

## Trabalhos futuros

Perfis de permissão (administrador x operador), recuperação de senha,
gerenciamento de usuários pela interface, e os módulos de relatórios e de
monitoramento automatizado.


## Configuração do banco

O projeto não guarda mais a senha do banco em `database/conexao.php`.

No XAMPP, o sistema usa automaticamente `localhost`, usuário `root`, senha vazia e banco `stocksense`.

Em produção, copie `database/conexao.local.php.example` para `database/conexao.local.php` e preencha as credenciais do seu host. Esse arquivo está protegido pelo `.gitignore`.

Como uma credencial de banco foi enviada no arquivo original, troque essa senha no painel do provedor antes de voltar a publicar o sistema.

## Arduino / sensor

Foi adicionada a API `api/leitura.php`. O ESP envia um token, a distância em centímetros e o percentual calculado pelo sensor. A leitura é gravada em `leituras` e o valor mais recente fica em `prateleiras.pct_sensor`. O `pct_ocupado` continua representando a ocupação registrada manualmente, para permitir comparação depois no dashboard.

Para um banco já existente, execute `php database/atualizar.php` pela linha de comando. O atualizador verifica o que falta e pode ser executado novamente. Depois use **Sensores** para cadastrar o dispositivo e escolher a prateleira, ou para baixar a configuração de um sensor existente.

Um exemplo de ESP32 + HC-SR04 está em `arduino/esp32_hcsr04/esp32_hcsr04.ino`. Ajuste Wi-Fi, URL da API, token e as distâncias de vazio/cheio da maquete.

### Medição pelo botão do site

Execute `php database/atualizar.php` no banco existente. Copie
`arduino/esp32_hcsr04/config.h.example` para `config.h` e configure rede,
URL de `api/leitura.php` e token do dispositivo. A configuração local fica fora do Git.
Grave o programa atualizado na placa; versões antigas continuam enviando automaticamente.

Para a VM, prefira baixar o `config.h` pela tela **Sensores**, que preenche a URL
pública e o token corretos. O firmware usa `ca_cert.h` e sincroniza o relógio
por NTP antes de abrir a conexão HTTPS. A placa precisa ter acesso à internet;
o computador que abre o site pode estar em outra rede.

No formulário **Registrar ocupação**, escolha uma prateleira com sensor ativo e
clique em **Medir agora**. O ESP32 consulta pedidos a cada dois segundos e só
aciona o sensor quando houver solicitação. Cada pedido vence em 30 segundos.
A leitura estima o volume total na caixa; o formulário desconta o volume já
registrado e preenche o volume novo. Confira a estimativa antes de salvar.
Os detalhes da prateleira mostram a última leitura do sensor, separada da
ocupação cadastrada manualmente.
O ESP32 deve alcançar a API pública. Mantenha o servidor web e o MySQL da VM ativos.

Em **Detalhes da prateleira**, altere as distâncias de caixa vazia e cheia e
salve a calibração. As próximas medições usam os valores do banco, sem precisar
gravar novamente o ESP32. O painel compara a última medição com o volume
cadastrado e identifica leituras com mais de 24 horas. Ao registrar uma
ocupação, o formulário mostra a conta entre volume total medido, volume já
registrado e volume novo sugerido. O servidor protege o cadastro de entradas
simultâneas acima da capacidade.

`database/atualizar.php` aplica as alterações pendentes de sensor, precisão,
medição manual, calibração e índices; pode ser executado novamente. Ele
reutiliza somente as definições de tabelas do SQL consolidado e não carrega
os exemplos. `database/stocksense.sql` é destinado a uma instalação nova;
não o importe sobre um banco com dados.

Para verificar o fluxo HTTP local com dados temporários, execute
`php tests/medicao_integracao.php https://stocksense.147.15.2.73.sslip.io`
na VM, usando o mesmo banco do site. O teste cria e remove duas empresas e contas
temporárias, realiza login HTTP real e verifica sensores, isolamento, expiração,
erro do sensor, reenvios, calibração e persistência após novo login.
