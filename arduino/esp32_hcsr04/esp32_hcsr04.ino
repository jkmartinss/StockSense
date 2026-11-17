#include <WiFi.h>
#include <HTTPClient.h>

#include "config.h"

const int TRIG_PIN = 26;
const int ECHO_PIN = 25;

// Distancias medidas na maquete: vazio = 0%, cheio = 100%.
const float DISTANCIA_VAZIA_CM = 22.3;
const float DISTANCIA_CHEIA_CM = 3.9;

float lerDistanciaCm() {
    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG_PIN, LOW);

    long duracao = pulseIn(ECHO_PIN, HIGH, 30000);

    if (duracao <= 0) {
        return -1;
    }

    return duracao * 0.0343f / 2.0f;
}

float calcularPercentual(float distancia) {
    float percentual =
        (DISTANCIA_VAZIA_CM - distancia) * 100.0 /
        (DISTANCIA_VAZIA_CM - DISTANCIA_CHEIA_CM);

    return constrain(percentual, 0.0, 100.0);
}

String urlMedicao() {
    String url = API_URL;
    return url.substring(0, url.lastIndexOf('/') + 1) + "medicao_dispositivo.php";
}

void enviarLeitura(long pedido, float distancia, float percentual) {
    if (WiFi.status() != WL_CONNECTED) {
        return;
    }

    HTTPClient http;
    http.setTimeout(5000);
    http.begin(urlMedicao());
    http.addHeader("Content-Type", "application/x-www-form-urlencoded");
    http.addHeader("X-Device-Token", TOKEN);

    String dados = "pedido_id=" + String(pedido);
    if (distancia < 0) {
        dados += "&erro=sem_eco";
    } else {
        dados += "&distancia_cm=" + String(distancia, 2) +
                 "&percentual_ocupado=" + String(percentual, 2);
    }

    int codigo = http.POST(dados);

    Serial.print("HTTP: ");
    Serial.println(codigo);
    Serial.println(http.getString());

    http.end();
}

void setup() {
    Serial.begin(115200);

    pinMode(TRIG_PIN, OUTPUT);
    pinMode(ECHO_PIN, INPUT);

    WiFi.begin(WIFI_SSID, WIFI_SENHA);
    WiFi.setAutoReconnect(true);

    Serial.print("Conectando ao Wi-Fi");
    unsigned long inicio = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - inicio < 15000) {
        delay(500);
        Serial.print(".");
    }

    Serial.println();
    Serial.println(WiFi.status() == WL_CONNECTED ? "Wi-Fi conectado. Aguardando Medir agora no site." : "Wi-Fi indisponivel. Tentando reconectar...");
}

void loop() {
    // Consulta somente pedidos; o sensor so mede depois do clique no site.
    delay(2000);
    if (WiFi.status() != WL_CONNECTED) {
        WiFi.reconnect();
        return;
    }
    HTTPClient http;
    http.setTimeout(5000);
    http.begin(urlMedicao());
    http.addHeader("X-Device-Token", TOKEN);
    int codigo = http.GET();
    String resposta = codigo == 200 ? http.getString() : "0";
    http.end();
    if (codigo != 200) {
        Serial.print("Falha ao consultar pedido. HTTP: ");
        Serial.println(codigo);
        return;
    }
    long pedido = resposta.toInt();
    if (pedido <= 0) return;

    float distancia = lerDistanciaCm();

    if (distancia >= 0) {
        float percentual = calcularPercentual(distancia);

        Serial.print("Distancia: ");
        Serial.print(distancia, 2);
        Serial.print(" cm | Ocupacao: ");
        Serial.print(percentual, 2);
        Serial.println("%");

        enviarLeitura(pedido, distancia, percentual);
    } else {
        Serial.println("Falha na leitura do HC-SR04.");
        enviarLeitura(pedido, -1, 0);
    }

}
