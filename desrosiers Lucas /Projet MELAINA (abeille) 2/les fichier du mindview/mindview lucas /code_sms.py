import time
import sys

# ==========================================
# DÉTECTION DU MATÉRIEL 
# ==========================================
try:
    import serial
    # Ouvre le port série pour communiquer avec le SIM7600 a modifier en fonction 
    port_gsm = serial.Serial('/dev/serial0', baudrate=115200, timeout=1)
    MODE_SIMULATION = False
    print("✅ Module GSM détecté. Prêt à envoyer de vrais SMS.\n")
except:
    MODE_SIMULATION = True
    print("⚠️ AVERTISSEMENT : Module GSM non détecté.")
    print("🔄 Passage en MODE SIMULATION (Affichage sur PC uniquement).\n")


# FONCTION POUR ENVOYER UN SMS

def envoyer_sms(numero, message):
    """ Envoie un SMS via les commandes AT du module SIM7600 """
    
    print(f"📱 Préparation de l'envoi au {numero}...")
    print(f"✉️ Message : '{message}'")
    
    if MODE_SIMULATION:
        # --- Mode PC (Simulation) ---
        time.sleep(2) # Simule le temps de traitement réseau
        print("✅ [SIMULATION] -> SMS envoyé avec succès !\n")
        return True
        
    else:
        # --- Mode Raspberry (Vrai Envoi) ---
        try:
            # 1. AT : Vérifie si le module répond
            port_gsm.write(b'AT\r')
            time.sleep(1)
            
            # 2. AT+CMGF=1 : Passe le module en "Mode Texte" pour les SMS
            port_gsm.write(b'AT+CMGF=1\r')
            time.sleep(1)
            
            # 3. AT+CMGS : Prépare le numéro de téléphone de l'apiculteur
            commande = f'AT+CMGS="{numero}"\r'
            port_gsm.write(commande.encode())
            time.sleep(1)
            
            # 4. Envoie le texte + le caractère spécial (Ctrl+Z) pour valider l'envoi
            port_gsm.write(message.encode() + b'\x1A')
            time.sleep(3) # Laisse le temps au réseau de partir
            
            print("✅ [RÉEL] -> SMS envoyé avec succès !\n")
            return True
            
        except Exception as e:
            print(f"❌ ERREUR lors de l'envoi physique : {e}\n")
            return False

# TEST DU SCRIPT

if __name__ == "__main__":
    print("🚀 Test du système d'alerte SMS...")
    
    # Numéro de l'apiculteur (à modifier)
    numero_apiculteur = "+596 696 xx xx xx"
    
    # Test 1 : L'alerte météo (lié à ton BME280)
    alerte_meteo = "ALERTE MELAINA : Température critique de 38.5°C détectée !"
    envoyer_sms(numero_apiculteur, alerte_meteo)
    
    # Test 2 : L'alerte antivol (lié à ton GPS)
    alerte_vol = "ALERTE MELAINA : Mouvement suspect de la ruche (GPS) !"
    envoyer_sms(numero_apiculteur, alerte_vol)