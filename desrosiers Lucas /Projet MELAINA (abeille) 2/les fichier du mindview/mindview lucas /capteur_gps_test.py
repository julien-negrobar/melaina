import time
import random
from datetime import datetime

# ==========================================
# DÉTECTION DU MATÉRIEL (VRAI GPS OU PC ?)
# ==========================================
try:
    # On essaie de charger la bibliothèque pour le vrai GPS
    import serial
    # On tente d'ouvrir le port physique du Raspberry Pi
    port_gps = serial.Serial('/dev/serial0', baudrate=9600, timeout=1)
    MODE_SIMULATION = False
    print("✅ Module GPS détecté. Mode RÉEL activé.")
except:
    # Si ça plante (parce qu'on est sur ton PC Windows/Mac)
    MODE_SIMULATION = True
    print("⚠️ AVERTISSEMENT : Matériel non détecté.")
    print("🔄 Passage en MODE SIMULATION (Génération de fausses données).")


# ==========================================
# 1. FONCTION POUR LIRE LE GPS
# ==========================================
def lire_position():
    """ Lit la position (soit la vraie, soit une fausse) """
    if MODE_SIMULATION:
        # --- Mode PC (Simulation) ---
        lat = 14.605 + random.uniform(-0.010, 0.010)
        lon = -61.065 + random.uniform(-0.002, 0.002)
        return round(lat, 5), round(lon, 5)
    else:
        
        # Il faudrai que je rajoute ici les vrai valeur de ou va se situer la ruche 
        # Pour l'instant, on renvoie une valeur fixe pour éviter les erreurs
        return 14.605, -61.065


# ==========================================
# 2. FONCTION POUR SAUVEGARDER
# ==========================================
def sauvegarder_log(latitude, longitude):
    """ Enregistre la position dans un fichier texte (Format CSV) """
    heure = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    with open("capteur_gps.log", "a") as fichier:
        fichier.write(f"{heure}, {latitude}, {longitude}\n")


# ==========================================
# 3. BOUCLE PRINCIPALE (LE PROGRAMME)
# ==========================================
print("\n🚀 Démarrage de la surveillance de la Ruche...")
LATITUDE_ORIGINE = 14.605 # L'endroit où la ruche doit rester

while True:
    # 1. Lecture
    lat, lon = lire_position()
    
    # 2. Affichage propre
    print(f"📍 Position actuelle : Lat {lat} | Lon {lon}")
    sauvegarder_log(lat, lon)
    
    # 3. Alerte Antivol
    if abs(lat - LATITUDE_ORIGINE) > 0.008:
        print("   🚨 ALERTE : La ruche a quitté la zone ! Vol détecté !")
        
    print("-" * 40)
    time.sleep(5) # Attente de 5 secondes