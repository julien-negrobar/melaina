import time
import serial
from datetime import datetime

# ==========================================
# 1. CONNEXION AU MATÉRIEL (SANS FILET)
# ==========================================
print("Tentative d'ouverture du port /dev/serial0...")

# Si le port Série n'est pas activé, le programme va planter EXACTEMENT ici.
port_gps = serial.Serial('/dev/serial0', baudrate=9600, timeout=1)
print("✅ Port série ouvert avec succès ! Le Raspberry Pi communique avec le module.")

# ==========================================
# 2. FONCTION POUR LIRE LE GPS
# ==========================================
def lire_position():
    """ Lit les lignes brutes (trames NMEA) envoyées par le GPS """
    # On lit une ligne de texte envoyée par le capteur
    ligne = port_gps.readline().decode('utf-8', errors='ignore').strip()
    return ligne

# ==========================================
# 3. BOUCLE PRINCIPALE
# ==========================================
print("\n🚀 Démarrage de l'écoute du GPS...")

while True:
    try:
        donnees_brutes = lire_position()
        
        # Le GPS envoie plein de lignes vides, on n'affiche que s'il y a du texte
        if donnees_brutes: 
            print(f"📡 Trame reçue : {donnees_brutes}")
        
    except Exception as e:
        print(f"❌ Erreur pendant la lecture : {e}")

    time.sleep(1) # On lit rapidement pour ne pas rater de données