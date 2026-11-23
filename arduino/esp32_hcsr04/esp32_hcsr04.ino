#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <time.h>
#include "config.h"
#include "ca_cert.h"

#ifndef STOCKSENSE_TRIG_PIN
#define STOCKSENSE_TRIG_PIN 26
#endif
#ifndef STOCKSENSE_ECHO_PIN
#define STOCKSENSE_ECHO_PIN 25
#endif
#ifndef STOCKSENSE_HTTP_LOCAL
#define STOCKSENSE_HTTP_LOCAL 0
#endif

const unsigned long INTERVALO_CONSULTA_MS = 2000;
const unsigned long LIMITE_REENVIO_MS = 20000;
WiFiClientSecure clienteTLS;
WiFiClient clienteLocal;
long pedidoPendente = 0;
float distanciaPendente = -1;
unsigned long inicioPedido = 0;
unsigned long ultimaConsulta = 0;
bool configuracaoValida = false;

String urlMedicao() {
    String url(API_URL);
    // Aceita tambem o config.h antigo, apontado para leitura.php.
    if (url.endsWith("leitura.php")) {
        url = url.substring(0, url.lastIndexOf('/') + 1) + "medicao_dispositivo.php";
    }
    return url;
}

bool relogioPronto() {
    return time(nullptr) >= 1735689600; // 01/01/2025 UTC
}

bool iniciarHTTP(HTTPClient &http) {
    String url = urlMedicao();
    http.setConnectTimeout(5000);
    http.setTimeout(5000);
    // Redirecionamentos sao tratados como erro: o token fica apenas neste host.
    http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
    bool iniciou = false;
    if (url.startsWith("https://")) {
        if (!relogioPronto()) {
            Serial.println("Aguardando horario NTP para validar HTTPS. Libere NTP na rede.");
            return false;
        }
        iniciou = http.begin(clienteTLS, url);
    } else if (STOCKSENSE_HTTP_LOCAL && url.startsWith("http://")) {
        iniciou = http.begin(clienteLocal, url);
    }
    if (iniciou) {
        http.addHeader("X-Device-Token", TOKEN);
        http.addHeader("Cache-Control", "no-cache");
    }
    return iniciou;
}

void informarFalha(int codigo) {
    Serial.print("HTTP: "); Serial.println(codigo);
    if (codigo < 0) {
        Serial.println(HTTPClient::errorToString(codigo));
        char erro[160];
        clienteTLS.lastError(erro, sizeof(erro));
        Serial.print("TLS: "); Serial.println(erro);
    } else if (codigo == 401) {
        Serial.println("Confira TOKEN, sensor ativo e prateleira/galpao/empresa ativos.");
    } else if (codigo >= 300 && codigo < 400) {
        Serial.println("URL redirecionada. Use a URL HTTPS final da API no config.h.");
    }
}

float lerDistanciaCm() {
    float amostras[5];
    int validas = 0;
    for (int i = 0; i < 5; i++) {
        digitalWrite(STOCKSENSE_TRIG_PIN, LOW);
        delayMicroseconds(2);
        digitalWrite(STOCKSENSE_TRIG_PIN, HIGH);
        delayMicroseconds(10);
        digitalWrite(STOCKSENSE_TRIG_PIN, LOW);
        unsigned long duracao = pulseIn(STOCKSENSE_ECHO_PIN, HIGH, 30000);
        if (duracao > 0) amostras[validas++] = duracao * 0.0343f / 2.0f;
        delay(60);
    }
    if (validas < 3) return -1;
    for (int i = 0; i < validas - 1; i++) {
        for (int j = i + 1; j < validas; j++) {
            if (amostras[j] < amostras[i]) {
                float aux = amostras[i]; amostras[i] = amostras[j]; amostras[j] = aux;
            }
        }
    }
    return amostras[validas / 2];
}

void enviarPendente() {
    if (pedidoPendente <= 0) return;
    if (millis() - inicioPedido > LIMITE_REENVIO_MS) {
        Serial.println("Tempo de reenvio esgotado. Solicite outra medicao no site.");
        pedidoPendente = 0;
        return;
    }
    HTTPClient http;
    if (!iniciarHTTP(http)) return;
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    String dados = "pedido_id=" + String(pedidoPendente);
    dados += distanciaPendente < 0 ? "&erro=sem_eco" : "&distancia_cm=" + String(distanciaPendente, 2);
    int codigo = http.POST(dados);
    if (codigo == 200 || codigo == 201) {
        Serial.print("Medicao recebida pelo servidor. Pedido "); Serial.println(pedidoPendente);
        pedidoPendente = 0;
    } else {
        informarFalha(codigo);
        // O mesmo pedido e a mesma distancia sao reenviados sem medir novamente.
        // A API impede duplicacao quando uma resposta se perde.
        if (codigo >= 400 && codigo < 500 && codigo != 429) pedidoPendente = 0;
    }
    http.end();
}

void setup() {
    Serial.begin(115200);
    pinMode(STOCKSENSE_TRIG_PIN, OUTPUT);
    pinMode(STOCKSENSE_ECHO_PIN, INPUT);
    digitalWrite(STOCKSENSE_TRIG_PIN, LOW);
    String url = urlMedicao();
    configuracaoValida = strlen(TOKEN) >= 32 && String(TOKEN) != "TOKEN_DO_DISPOSITIVO"
        && url.endsWith("/medicao_dispositivo.php")
        && (url.startsWith("https://") || (STOCKSENSE_HTTP_LOCAL && url.startsWith("http://")));
    if (!configuracaoValida) {
        Serial.println("Configuracao invalida: baixe o config.h na tela Sensores e preencha o Wi-Fi.");
        return;
    }
    clienteTLS.setCACert(STOCKSENSE_ROOT_CA);
    clienteTLS.setHandshakeTimeout(8);
    WiFi.mode(WIFI_STA);
    WiFi.setAutoReconnect(true);
    WiFi.begin(WIFI_SSID, WIFI_SENHA);
    configTime(0, 0, "pool.ntp.org", "time.google.com", "time.cloudflare.com");
    Serial.println("Iniciando Wi-Fi. Consulta da API a cada 2 segundos, apos sincronizar NTP.");
    Serial.print("API: "); Serial.println(url);
}

void loop() {
    delay(20);
    if (!configuracaoValida) return;
    if (millis() - ultimaConsulta < INTERVALO_CONSULTA_MS) return;
    ultimaConsulta = millis();
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("Wi-Fi desconectado. Reconectando...");
        WiFi.reconnect();
        return;
    }
    if (pedidoPendente > 0) { enviarPendente(); return; }
    HTTPClient http;
    if (!iniciarHTTP(http)) return;
    int codigo = http.GET();
    String resposta = codigo == 200 ? http.getString() : "";
    http.end();
    if (codigo != 200) { informarFalha(codigo); return; }
    resposta.trim();
    // Rejeita HTML de proxy/login, em vez de tratar qualquer texto como pedido.
    if (resposta.length() == 0 || resposta.length() > 10) {
        Serial.println("Resposta inesperada. Confira a URL e o proxy da VM."); return;
    }
    for (unsigned int i = 0; i < resposta.length(); i++) {
        if (!isDigit(resposta[i])) { Serial.println("A API nao devolveu um numero de pedido."); return; }
    }
    long pedido = resposta.toInt();
    if (pedido <= 0) return;
    inicioPedido = millis();
    distanciaPendente = lerDistanciaCm();
    pedidoPendente = pedido;
    Serial.print("Pedido "); Serial.print(pedido);
    if (distanciaPendente < 0) Serial.println(": sensor sem eco.");
    else { Serial.print(" | Distancia: "); Serial.print(distanciaPendente, 2); Serial.println(" cm"); }
    enviarPendente();
}
