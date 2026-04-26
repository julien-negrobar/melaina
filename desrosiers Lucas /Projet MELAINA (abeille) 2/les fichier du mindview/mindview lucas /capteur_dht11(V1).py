import grovepi
import math
import time
from datetime import datetime

# Capteur DHT11 branché sur le port D4
sensor = 4
blue = 0   # 0 = DHT11 bleu

while True:
    try:
        # Lecture du capteur
        temp, humidity = grovepi.dht(sensor, blue)

        # Vérifie que les valeurs sont valides
        if not math.isnan(temp) and not math.isnan(humidity):
            maintenant = datetime.now().strftime("%d/%m/%Y %H:%M:%S")

            print("\n📡 Nouvelle mesure")
            print("🕒 Date :", maintenant)
            print(f"🌡️ Température : {temp:.1f} °C")
            print(f"💧 Humidité : {humidity:.1f} %")
            print("🌫️ Pression : non disponible sur ce capteur")

            # Petits messages d'alerte
            if temp > 35:
                print("⚠️ Température élevée")
            if humidity < 30:
                print("⚠️ Humidité faible")
            if humidity > 85:
                print("⚠️ Humidité très élevée")

        else:
            print("⚠️ Lecture invalide")

    except IOError:
        print("❌ Erreur de lecture du capteur")

    except KeyboardInterrupt:
        print("\n🛑 Programme arrêté")
        break

    time.sleep(2)