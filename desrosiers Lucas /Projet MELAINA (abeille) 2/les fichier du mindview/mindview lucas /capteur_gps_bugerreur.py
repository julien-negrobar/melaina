import time
import serial

# On tente d'ouvrir le port que tu as déjà activé
try:
    port_gps = serial.Serial('/dev/serial0', baudrate=9600, timeout=2)
    print("✅ Port /dev/serial0 ouvert.")
except Exception as e:
    print(f"❌ ERREUR CRITIQUE : Impossible d'accéder au port. Erreur : {e}")
    exit()

print("🛰️ En attente de données GPS (NMEA)...")

while True:
    try:
        # On lit une ligne
        ligne = port_gps.readline().decode('utf-8', errors='ignore').strip()
        
        if not ligne:
            print("... Silence radio (Le port est ouvert mais aucune donnée ne sort) ...")
        else:
            print(f"📡 DONNÉE BRUTE : {ligne}")
            
            # Si on voit $GPRMC ou $GPGGA, c'est que le GPS a capté un satellite
            if "$GPRMC" in ligne or "$GPGGA" in ligne:
                print("✨ INFO : Le GPS envoie des trames de position !")

    except KeyboardInterrupt:
        print("\nArrêt du script.")
        break
    except Exception as e:
        print(f"⚠️ Erreur de lecture : {e}")
    
    time.sleep(1)