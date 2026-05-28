import RPi.GPIO as GPIO
import serial
import time
import math
import json
import grovepi

# ==========================================
# CONFIGURATION — À MODIFIER ICI
# ==========================================
NUMERO_DESTINATAIRE  = "+33600000000"  # Numéro de l'apicultrice (format international)
TEMPERATURE_CIBLE    = 20.0            # Température intérieure idéale à maintenir (°C)
TOLERANCE            = 1.0             # Écart toléré (±1°C → alerte si <19°C ou >21°C)

PORT_CAPTEUR_INTERNE = 3               # D3 = capteur température/humidité intérieur de la ruche
PORT_CAPTEUR_EXTERNE = 4               # D4 = capteur température/humidité extérieur
DHT_TYPE             = 0               # 0 = DHT11, 1 = DHT22

POWER_KEY            = 6               # PIN GPIO du bouton power SIM7600
PORT_SERIE           = '/dev/ttyUSB2'  # Port du module SIM7600 (SMS)
                                       # Si ça ne marche pas essaie '/dev/ttyUSB1' ou '/dev/ttyUSB3'

PORT_LORA            = '/dev/serial0'  # Port du HAT LoRa SKU22571
                                       # ATTENTION : doit être différent de PORT_SERIE

SEUIL_CHUTE_POIDS    = 2.0            # Chute en kg qui déclenche l'alerte SMS

TEMPS_BOUCLE         = 30             # Secondes entre chaque mesure
INTERVALLE_SMS       = 240            # Minimum 4 minutes entre deux SMS pour éviter le spam
# ==========================================

# Je calcule les seuils une seule fois au démarrage
SEUIL_HAUT = TEMPERATURE_CIBLE + TOLERANCE
SEUIL_BAS  = TEMPERATURE_CIBLE - TOLERANCE

# Timers anti-spam — 0 = aucun SMS encore envoyé
dernier_sms_chaud = 0
dernier_sms_froid = 0
dernier_sms_poids = 0

# Je garde le poids précédent pour calculer la chute entre deux mesures
poids_precedent = None
poids_actuel    = None

# Buffer pour reconstituer les lignes JSON reçues par LoRa
buffer_lora = ""


# ==========================================
# FONCTIONS SMS
# ==========================================

def power_on():
    # J'allume physiquement le module SIM7600 via le GPIO
    # Le sleep(20) est obligatoire, le module met du temps à booter
    print("Démarrage du module SIM7600...")
    GPIO.setmode(GPIO.BCM)
    GPIO.setwarnings(False)
    GPIO.setup(POWER_KEY, GPIO.OUT)
    time.sleep(0.1)
    GPIO.output(POWER_KEY, GPIO.HIGH)
    time.sleep(2)
    GPIO.output(POWER_KEY, GPIO.LOW)
    time.sleep(20)
    print("Module SIM7600 prêt.")


def power_down():
    # J'éteins le module après chaque SMS pour économiser l'énergie de la ruche
    print("Extinction du module SIM7600...")
    GPIO.output(POWER_KEY, GPIO.HIGH)
    time.sleep(3)
    GPIO.output(POWER_KEY, GPIO.LOW)
    time.sleep(18)
    print("Module éteint.")


def send_at(ser, command, expected, timeout):
    # J'envoie une commande AT au modem et je vérifie qu'il répond ce que j'attends
    ser.write((command + '\r\n').encode())
    time.sleep(timeout)
    response = b''
    if ser.inWaiting():
        time.sleep(0.01)
        response = ser.read(ser.inWaiting())
    if response and expected in response.decode():
        return True
    print(f"  Commande AT échouée : {command} → {response.decode().strip()}")
    return False


def envoyer_sms(message):
    # J'allume le module, j'attends le réseau, j'envoie le SMS, puis j'éteins
    # Le chr(26) à la fin du message c'est le Ctrl+Z qui valide l'envoi côté modem
    # IMPORTANT : power_on() doit être appelé en premier pour initialiser le GPIO
    # avant power_down(), sinon Python plante si le port série est introuvable
    print(f"\n📱 Envoi SMS : {message[:50]}...")
    power_on()
    try:
        ser = serial.Serial(PORT_SERIE, 115200, timeout=1)
        ser.flushInput()

        print("Connexion au réseau cellulaire (10s)...")
        time.sleep(10)

        send_at(ser, "AT+CMGF=1", "OK", 1)  # Je passe le modem en mode texte

        ser.write(('AT+CMGS="' + NUMERO_DESTINATAIRE + '"\r\n').encode())
        time.sleep(1)
        ser.write((message + chr(26)).encode())
        time.sleep(5)

        ser.close()
        print("✅ SMS envoyé avec succès.")

    except Exception as e:
        # Si ça plante (mauvais port, modem qui répond pas...) je logge et je continue
        print(f"❌ Erreur envoi SMS : {e}")

    power_down()


# ==========================================
# FONCTIONS CAPTEURS
# ==========================================

def lire_capteur(port):
    # Je lis un capteur DHT sur le port demandé (D3 ou D4)
    # Je retourne None si la lecture échoue ou donne NaN
    try:
        temperature, humidite = grovepi.dht(port, DHT_TYPE)
        if math.isnan(temperature) or math.isnan(humidite):
            return None, None
        return round(temperature, 1), round(humidite, 1)
    except Exception as e:
        print(f"⚠️  Erreur lecture capteur D{port} : {e}")
        return None, None


def init_lora():
    # J'ouvre le port LoRa au démarrage et je le garde ouvert en permanence
    try:
        ser_lora = serial.Serial(PORT_LORA, 9600, timeout=1)
        print(f"[OK] LoRa {PORT_LORA} ouvert à 9600 bauds")
        return ser_lora
    except Exception as e:
        print(f"[ERREUR] Impossible d'ouvrir le port LoRa : {e}")
        return None


def lire_lora(ser_lora):
    # Je lis ce qui est disponible sur le port LoRa sans bloquer la boucle principale
    # Le publisher envoie du JSON terminé par \n, je reconstitue les lignes dans le buffer
    global buffer_lora
    poids_recu = None

    if ser_lora is None or ser_lora.in_waiting == 0:
        return None

    try:
        data = ser_lora.read(ser_lora.in_waiting).decode("utf-8", errors="ignore")
        buffer_lora += data

        while "\n" in buffer_lora:
            ligne, buffer_lora = buffer_lora.split("\n", 1)
            ligne = ligne.strip()
            if not ligne:
                continue
            try:
                donnees = json.loads(ligne)
                poids_recu = donnees.get("poids")
                print(f"[LoRa] Reçu → Poids : {poids_recu} kg")
            except json.JSONDecodeError:
                print(f"[LoRa] JSON invalide ignoré : {ligne}")

    except Exception as e:
        print(f"[LoRa] Erreur lecture : {e}")

    return poids_recu


def construire_sms(titre, t_int, h_int, t_ext, h_ext, poids):
    # Je formate le SMS de façon lisible pour l'apicultrice
    # Toutes les alertes ont le même bloc d'informations en dessous du titre
    poids_ligne = f"{poids} kg" if poids is not None else "inconnu"
    t_ext_ligne = f"{t_ext}°C" if t_ext is not None else "inconnue"
    h_ext_ligne = f"{h_ext}%" if h_ext is not None else "inconnue"

    return (
        f"[BeeSecure] ALERTE - {titre}\n"
        f"\n"
        f"Température interne : {t_int}°C\n"
        f"Humidité interne : {h_int}%\n"
        f"Poids de la ruche : {poids_ligne}\n"
        f"\n"
        f"Température extérieure : {t_ext_ligne}\n"
        f"Humidité extérieure : {h_ext_ligne}\n"
        f"\n"
        f"Vérifiez la ruche immédiatement."
    )


# ==========================================
# BOUCLE PRINCIPALE
# ==========================================
print(f"🌡️  Surveillance température + poids active.")
print(f"   Cible intérieure : {TEMPERATURE_CIBLE}°C  |  Plage normale : {SEUIL_BAS}°C → {SEUIL_HAUT}°C")
print(f"   Alerte poids si chute > {SEUIL_CHUTE_POIDS} kg  |  Anti-spam SMS : {INTERVALLE_SMS}s\n")

ser_lora = init_lora()

while True:
    maintenant = time.time()

    # --- LECTURE DES DEUX CAPTEURS ---
    t_int, h_int = lire_capteur(PORT_CAPTEUR_INTERNE)  # D3 = intérieur
    t_ext, h_ext = lire_capteur(PORT_CAPTEUR_EXTERNE)  # D4 = extérieur

    # Si le capteur intérieur ne répond pas je ne peux pas surveiller la ruche
    if t_int is None:
        print("⚠️  Lecture capteur intérieur (D3) impossible, nouvelle tentative dans 30s.")
        time.sleep(TEMPS_BOUCLE)
        continue

    # --- LECTURE POIDS VIA LORA ---
    poids_recu = lire_lora(ser_lora)
    if poids_recu is not None:
        poids_precedent = poids_actuel
        poids_actuel    = poids_recu

    # Ligne de log du cycle en cours
    poids_affiche = f"{poids_actuel} kg" if poids_actuel is not None else "en attente LoRa"
    print(f"[{time.strftime('%H:%M:%S')}] Int: {t_int}°C {h_int}% | Ext: {t_ext}°C {h_ext}% | Poids: {poids_affiche}")

    # --- ALERTE TEMPÉRATURE INTÉRIEURE TROP ÉLEVÉE ---
    if t_int > SEUIL_HAUT:
        print(f"🔴 ALERTE CHAUD : {t_int}°C > {SEUIL_HAUT}°C")

        if maintenant - dernier_sms_chaud >= INTERVALLE_SMS:
            sms = construire_sms(
                f"Température intérieure trop élevée ({t_int}°C)",
                t_int, h_int, t_ext, h_ext, poids_actuel
            )
            envoyer_sms(sms)
            dernier_sms_chaud = time.time()
        else:
            attente = int(INTERVALLE_SMS - (maintenant - dernier_sms_chaud))
            print(f"   (SMS anti-spam : prochain dans {attente}s)")

    # --- ALERTE TEMPÉRATURE INTÉRIEURE TROP BASSE ---
    elif t_int < SEUIL_BAS:
        print(f"🔵 ALERTE FROID : {t_int}°C < {SEUIL_BAS}°C")

        if maintenant - dernier_sms_froid >= INTERVALLE_SMS:
            sms = construire_sms(
                f"Température intérieure trop basse ({t_int}°C)",
                t_int, h_int, t_ext, h_ext, poids_actuel
            )
            envoyer_sms(sms)
            dernier_sms_froid = time.time()
        else:
            attente = int(INTERVALLE_SMS - (maintenant - dernier_sms_froid))
            print(f"   (SMS anti-spam : prochain dans {attente}s)")

    # --- ALERTE CHUTE DE POIDS ---
    # Je vérifie seulement si j'ai déjà reçu au moins deux mesures LoRa
    if poids_precedent is not None and poids_actuel is not None:
        chute = round(poids_precedent - poids_actuel, 2)

        if chute >= SEUIL_CHUTE_POIDS:
            print(f"⚖️  ALERTE POIDS : chute de {chute} kg ({poids_precedent} → {poids_actuel} kg)")

            if maintenant - dernier_sms_poids >= INTERVALLE_SMS:
                sms = construire_sms(
                    f"Chute de poids détectée (-{chute} kg)",
                    t_int, h_int, t_ext, h_ext, poids_actuel
                )
                envoyer_sms(sms)
                dernier_sms_poids = time.time()
            else:
                attente = int(INTERVALLE_SMS - (maintenant - dernier_sms_poids))
                print(f"   (SMS anti-spam : prochain dans {attente}s)")

    time.sleep(TEMPS_BOUCLE)
