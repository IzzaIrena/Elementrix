#include <WiFi.h>
#include <HTTPClient.h>
#include <Firebase_ESP_Client.h>
#include <ArduinoJson.h>
#include <WebServer.h>

// ==================== KONFIGURASI JARINGAN ====================
const char* WIFI_SSID = "sinta";
const char* WIFI_PASSWORD = "sinta12345";

#define FIREBASE_HOST "ptc-yh-default-rtdb.asia-southeast1.firebasedatabase.app"
#define FIREBASE_AUTH "4wnwZgBFaAy43KFRV4r97vKzFpLoopy5yXI2Qjxu"

// ==================== KONFIGURASI PIN ====================
#define IR_SENSOR_PIN 4
#define BUZZER_PIN 5
#define LED_PIN 2

// ==================== KONFIGURASI ALAMAT IP ====================
String esp32camIP = "10.240.186.120";
const int esp32camPort = 80;
const int serverPort = 8080;

// ==================== DEKLARASI OBJEK ====================
WebServer server(serverPort);
FirebaseData fbdo;
FirebaseAuth auth;
FirebaseConfig config;

// ==================== VARIABEL SISTEM ====================
bool personDetected = false;
String lastQRCode = "";
unsigned long lastQRTime = 0;

const unsigned long QR_COOLDOWN = 2000; // duplikasi cepat (diabaikan)
int totalScans = 0;

const unsigned long BEEP_SHORT = 200;

// ==================== FUNGSI BUZZER ====================
void beepShort() {
  digitalWrite(BUZZER_PIN, HIGH);
  delay(BEEP_SHORT);
  digitalWrite(BUZZER_PIN, LOW);
}

// >>> PERBAIKAN <<<
void beepLong() {
  digitalWrite(BUZZER_PIN, HIGH);
  delay(600);
  digitalWrite(BUZZER_PIN, LOW);
}

// ==================== PARSING QR ====================
bool parseQRData(String qrData, FirebaseJson &qrJson) {

  // >>> PERBAIKAN FORMAT <<<
if (qrData.indexOf("ID:") < 0 ||
    qrData.indexOf("Nama:") < 0 ||
    qrData.indexOf("Sekolah:") < 0 ||
    qrData.indexOf("Tanggal:") < 0 ||
    qrData.indexOf("Jam:") < 0) {
    return false;
}

  qrData.replace("\n", "");
  qrData.trim();

  int start = 0;
  while (start < qrData.length()) {
    int end = qrData.indexOf(';', start);
    String pair;
    if (end == -1) {
      pair = qrData.substring(start);
      start = qrData.length();
    } else {
      pair = qrData.substring(start, end);
      start = end + 1;
    }

    pair.trim();
    if (pair.length() == 0) continue;

    int colon = pair.indexOf(':');
    if (colon != -1) {
      String key = pair.substring(0, colon);
      String value = pair.substring(colon + 1);
      key.trim();
      value.trim();
      if (key.length() > 0) {
        qrJson.set(key, value);
      }
    }
  }

  return true;
}

// ==================== CEK DUPLIKAT DI FIREBASE ====================
bool isQRAlreadyLogged(String qrID) {
  if (qrID.length() == 0) return false;
  String path = "/scan_logs/" + qrID;

  if (Firebase.RTDB.getJSON(&fbdo, path)) {
    Serial.println("ID " + qrID + " sudah ada di Firebase.");
    return true;
  }
  return false;
}

// ==================== SIMPAN KE FIREBASE ====================
bool logToFirebase(FirebaseJson &qrStructured, String qrID) {

  if (isQRAlreadyLogged(qrID)) {
    Serial.println("Duplikasi Firebase → Bunyikan panjang");
    beepLong();           // >>> PERBAIKAN <<<
    return false;
  }

  String path = "/scan_logs/" + qrID;

  FirebaseJson json;
  FirebaseJsonData tmp;

  if (qrStructured.get(tmp, "ID")) json.set("ID", tmp.stringValue);
  if (qrStructured.get(tmp, "Nama")) json.set("Nama", tmp.stringValue);
  if (qrStructured.get(tmp, "Sekolah")) json.set("Sekolah", tmp.stringValue);
  if (qrStructured.get(tmp, "Tanggal")) json.set("Tanggal", tmp.stringValue);
  if (qrStructured.get(tmp, "Jam")) json.set("Jam", tmp.stringValue);

  json.set("device_id", "esp32_main");
  json.set("timestamp", (long)millis());

  if (Firebase.RTDB.setJSON(&fbdo, path, &json)) {
    Serial.println("Data tersimpan: " + path);
    return true;
  } else {
    Serial.println("Gagal simpan: " + fbdo.errorReason());
    return false;
  }
}

// ==================== UPDATE STATISTIK ====================
void updateStatistics() {
  totalScans++;
  FirebaseJson json;
  json.set("total_scans", totalScans);
  json.set("last_scan_time", (long)millis());

  Firebase.RTDB.setJSON(&fbdo, "/statistics/device_01", &json);
  Firebase.RTDB.setInt(&fbdo, "/system/last_update", (long)millis());
}

// ==================== PROSES QR ====================
void processQRCode(String qrData) {
  Serial.println("\n=== PROSES QR ===");

  FirebaseJson qrStructured;

  // Validasi Format QR
  if (!parseQRData(qrData, qrStructured)) {
    Serial.println("QR Format salah → Beep panjang");
    beepLong();
    return;
  }

  FirebaseJsonData idData;
  qrStructured.get(idData, "ID");
  String qrID = idData.stringValue;

  if (qrID.length() == 0) {
    Serial.println("ID kosong → Beep panjang");
    beepLong();
    return;
  }

  // >>> CEK DUPLIKAT FIREBASE DULU <<<
  if (isQRAlreadyLogged(qrID)) {
    Serial.println("Duplikasi Firebase → Bunyikan panjang");
    beepLong();
    return;          // DIHENTIKAN, AGAR TIDAK ADA BEEP PENDEK
  }

  // >>> BEEP PENDEK HANYA UNTUK SCAN VALID BARU <<<
  beepShort();

  // Simpan ke Firebase
  if (!logToFirebase(qrStructured, qrID)) {
    return;
  }

  updateStatistics();
  Serial.println("=== SELESAI ===\n");
}

// ==================== API QR ====================
void handleQRData() {
  if (server.method() != HTTP_POST) {
    server.send(405, "application/json", "{\"error\":\"Method tidak diizinkan\"}");
    return;
  }

  String body = server.arg("plain");
  StaticJsonDocument<512> doc;

  if (deserializeJson(doc, body)) {
    server.send(400, "application/json", "{\"error\":\"Format JSON salah\"}");
    return;
  }

  String qrData = doc["qr_data"].as<String>();
  qrData.trim();

  if (qrData.length() < 3) {
    server.send(400, "application/json", "{\"error\":\"QR terlalu pendek\"}");
    return;
  }

  // >>> Duplikasi cepat → TIDAK ADA SUARA <<<
  if (millis() - lastQRTime < QR_COOLDOWN && qrData == lastQRCode) {
    server.send(200, "application/json", "{\"status\":\"duplicate_ignored\"}");
    return;
  }

  lastQRCode = qrData;
  lastQRTime = millis();

  server.send(200, "application/json", "{\"status\":\"processing\"}");

  processQRCode(qrData);
}

// ==================== STATUS ====================
void handleStatus() {
  FirebaseJson json;
  json.set("device_id", "ESP32_MAIN");
  json.set("ip_address", WiFi.localIP().toString());
  json.set("last_qr_code", lastQRCode);
  json.set("firebase_connected", Firebase.ready());
  json.set("total_scans", totalScans);

  String response;
  json.toString(response, true);
  server.send(200, "application/json", response);
}

// ==================== WIFI / FIREBASE SETUP ====================
void setupWiFi() {
  Serial.print("Menghubungkan WiFi...");
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  while (WiFi.status() != WL_CONNECTED) {
    delay(400);
    Serial.print(".");
  }

  Serial.println("\nWiFi Terhubung!");
}

void setupFirebase() {
  Serial.println("Inisialisasi Firebase...");

  config.host = FIREBASE_HOST;
  config.signer.tokens.legacy_token = FIREBASE_AUTH;

  Firebase.begin(&config, &auth);
  fbdo.setResponseSize(8192);
}

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);

  pinMode(IR_SENSOR_PIN, INPUT);
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(LED_PIN, OUTPUT);

  setupWiFi();
  setupFirebase();

  server.on("/api/qr-data", HTTP_POST, handleQRData);
  server.on("/api/status", HTTP_GET, handleStatus);

  server.begin();

  beepShort();
  delay(100);
  beepShort();
}

// ==================== LOOP ====================
void loop() {
  server.handleClient();
}
