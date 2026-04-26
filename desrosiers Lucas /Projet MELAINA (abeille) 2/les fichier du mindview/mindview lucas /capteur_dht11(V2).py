import grovepi
import math
import time
from datetime import datetime
# import serial <-- On l'activera plus tard pour communiquer avec le module 4G pour les SMS

# Capteur DHT11 branché sur le port D4
sensor = 4
blue = 0   # 0 = DHT11 bleu

# ==========================================
# 1. SAUVEGARDE LOCALE (Ta "Boîte Noire")
# ==========================================
def sauvegarder_log(temp, hum):
    heure = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    # Le mode "a" (append) ajoute les données à la fin du fichier sans écraser le reste
    with open("meteo_ruche.log", "a") as fichier:
        fichier.write(f"{heure},{temp},{hum}\n")
    print("💾 Données sécurisées dans meteo_ruche.log")

# ==========================================
# 2. GESTION DES ALERTES SMS (TON RÔLE)
# ==========================================
def envoyer_sms_alerte(message):
    print("\n🚨 ALERTE DÉCLENCHÉE !")
    print(f"Préparation de l'envoi du SMS : '{message}'")
    # --- C'est ici qu'on mettra les commandes AT pour le SIM7600 ---
    # sim.write(b'AT+CMGF=1\r')
    # sim.write(b'AT+CMGS="0600000000"\r')
    # sim.write(message.encode() + b'\x1A')
    print("📲 [Simulation] : Le SMS a bien été envoyé au téléphone de l'apiculteur !")

# ==========================================
# 3. BOUCLE PRINCIPALE
# ==========================================
while True:
    try:
        temp, humidity = grovepi.dht(sensor, blue)

        if not math.isnan(temp) and not math.isnan(humidity):
            maintenant = datetime.now().strftime("%d/%m/%Y %H:%M:%S")

            print("\n" + "="*30)
            print("📡 NOUVELLE MESURE")
            print("🕒 Date :", maintenant)
            print(f"🌡️ Température : {temp:.1f} °C")
            print(f"💧 Humidité : {humidity:.1f} %")
            
            # 1. Tu sauvegardes dans le fichier texte (Historique local)
            sauvegarder_log(temp, humidity)

            # 2. Tu gères les alertes (Si ça dépasse les seuils)
            if temp > 35:
                envoyer_sms_alerte(f"Alerte Ruche: Température critique ! {temp:.1f}C")
            elif humidity < 30:
                envoyer_sms_alerte(f"Alerte Ruche: Humidité trop faible ! {humidity:.1f}%")

        else:
            print("⚠️ Lecture invalide")

    except IOError:
        print("❌ Erreur de lecture du capteur")

    except KeyboardInterrupt:
        print("\n🛑 Programme arrêté")
        break

    time.sleep(5)