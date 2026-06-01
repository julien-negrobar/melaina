import time
import math
import json
import grovepi
import paho.mqtt.client as mqtt
import smtplib
from email.mime.text import MIMEText

# ma partie : configuration de mon serveur mail lws
SMTP_SERVER = "mail.madebylucas.fr"
SMTP_PORT   = 465

EMAIL_SENDER   = "system.melaina@madebylucas.fr"
EMAIL_PASSWORD = "Melaina_972!"
EMAIL_RECEIVER = "desrosiers.lucxs@gmail.com"

# ma partie : configuration de mes capteurs dht11 sur le grovepi
TEMPERATURE_CIBLE = 20.0
TOLERANCE         = 1.0

SEUIL_TEMP_HAUTE = TEMPERATURE_CIBLE + TOLERANCE  # 21.0
SEUIL_TEMP_BASSE = TEMPERATURE_CIBLE - TOLERANCE  # 19.0

PORT_CAPTEUR_INTERNE = 3  # D3 pour l'interieur
PORT_CAPTEUR_EXTERNE = 4  # D4 pour la meteo externe
DHT_TYPE             = 0

# partie de julien : configuration du mqtt pour le poids
MQTT_BROKER = "localhost"
MQTT_PORT   = 1883
TOPIC_POIDS = "melaina/ruche_fille/poids"

SEUIL_CHUTE_POIDS    = 3.0  # chute en kg pour declencher l'alerte
TOLERANCE_RETOUR     = 1.0  # marge pour dire que le poids est revenu a la normale
INTERVALLE_ALERTE    = 120  # on attend 2 min entre chaque mail pour pas spammer

# variables globales pour stocker les donnees en direct
t_int = None
h_int = None
t_ext = None
h_ext = None
poids_actuel = None

# reference de poids calculee au demarrage du script
poids_reference  = None
mesures_init     = []       

poids_en_alerte    = False
flag_alerte_poids  = False
valeur_chute_poids = 0.0

# timers pour gerer l'anti-spam des mails
dernier_mail_temperature = 0
dernier_mail_poids       = 0


# ma fonction principale pour generer et envoyer le mail d'alerte
def envoyer_alerte_mail(sujet, liste_motifs):
    print(f"\nEnvoi alerte mail...")

    poids_str = f"{poids_actuel} kg"         if poids_actuel  is not None else "En attente MQTT"
    ref_str   = f"{poids_reference} kg"      if poids_reference is not None else "Non etablie"
    t_int_str = f"{t_int} C"                 if t_int is not None else "Erreur capteur"
    h_int_str = f"{h_int}%"                  if h_int is not None else "Erreur capteur"
    t_ext_str = f"{t_ext} C"                 if t_ext is not None else "Erreur capteur"
    h_ext_str = f"{h_ext}%"                  if h_ext is not None else "Erreur capteur"

    motifs_format = "\n".join([f" - {motif}" for motif in liste_motifs])

    corps = (
        f"ALERTE BEESECURE\n"
        f"-----------------------------------\n"
        f"Probleme(s) detecte(s) :\n{motifs_format}\n\n"
        f"-----------------------------------\n"
        f"Donnees de la Ruche :\n"
        f" - Temperature interieure : {t_int_str} (Cible: {TEMPERATURE_CIBLE} C)\n"
        f" - Humidite interieure : {h_int_str}\n"
        f" - Poids actuel : {poids_str}\n"
        f" - Poids de reference : {ref_str}\n\n"
        f"Environnement Exterieur :\n"
        f" - Temperature exterieure : {t_ext_str}\n"
        f" - Humidite exterieure : {h_ext_str}\n\n"
        f"Verifiez la ruche immediatement."
    )

    try:
        # j'utilise le port 465 en ssl pour que la connexion lws soit securisee
        msg = MIMEText(corps)
        msg['Subject'] = sujet
        msg['From']    = EMAIL_SENDER
        msg['To']      = EMAIL_RECEIVER

        server = smtplib.SMTP_SSL(SMTP_SERVER, SMTP_PORT)
        server.login(EMAIL_SENDER, EMAIL_PASSWORD)
        server.send_message(msg)
        server.quit()
        print("E-mail envoye avec succes !")
    except Exception as e:
        print(f"Erreur envoi e-mail : {e}")


# ma fonction pour lire les capteurs dht11
def lire_capteur(port):
    # je protege la lecture pour pas faire planter le script si un fil bouge
    try:
        temp, hum = grovepi.dht(port, DHT_TYPE)
        if math.isnan(temp) or math.isnan(hum):
            return None, None
        return round(temp, 1), round(hum, 1)
    except IOError:
        return None, None


# fonctions de julien pour gerer la reception mqtt du poids
def on_connect(client, userdata, flags, rc, properties=None):
    if rc == 0:
        print("[OK] Connecte MQTT. Ecoute de la ruche fille activee.")
        client.subscribe(TOPIC_POIDS)

def on_message(client, userdata, msg):
    global poids_actuel, flag_alerte_poids, valeur_chute_poids
    global poids_reference, mesures_init, poids_en_alerte

    try:
        donnees = json.loads(msg.payload.decode("utf-8", errors="ignore"))
        valeur  = donnees.get("valeur")

        if msg.topic == TOPIC_POIDS and valeur is not None:
            nouveau_poids = round(float(valeur), 2)

            # on prend 2 mesures au demarrage pour faire la reference a vide
            if poids_reference is None:
                mesures_init.append(nouveau_poids)
                print(f"[INIT] Mesure {len(mesures_init)}/2 recue : {nouveau_poids} kg")
                if len(mesures_init) >= 2:
                    poids_reference = round(sum(mesures_init) / len(mesures_init), 2)
                    print(f"[OK] Poids de reference etabli : {poids_reference} kg")
                    print(f"     Alerte si poids < {round(poids_reference - SEUIL_CHUTE_POIDS, 2)} kg")

            else:
                # si le poids chute par rapport a la reference on lance l'alerte
                chute = round(poids_reference - nouveau_poids, 2)

                if chute >= SEUIL_CHUTE_POIDS and not poids_en_alerte:
                    flag_alerte_poids  = True
                    valeur_chute_poids = chute
                    poids_en_alerte    = True

                # on remet a zero si le poids revient a la normale
                if abs(nouveau_poids - poids_reference) <= TOLERANCE_RETOUR:
                    if poids_en_alerte:
                        print(f"[OK] Poids revenu a la normale ({nouveau_poids} kg approx. ref. {poids_reference} kg)")
                    poids_en_alerte = False

            poids_actuel = nouveau_poids

    except Exception as e:
        pass


# demarrage du client mqtt local
client_mqtt = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
client_mqtt.on_connect = on_connect
client_mqtt.on_message = on_message
client_mqtt.connect(MQTT_BROKER, MQTT_PORT)
client_mqtt.loop_start()

print("Demarrage de la surveillance intelligente... (Ctrl+C pour quitter)")
print(f"   En attente de 2 mesures pour etablir le poids de reference...\n")

while True:
    maintenant = time.time()

    # je lis mes deux capteurs
    t_int, h_int = lire_capteur(PORT_CAPTEUR_INTERNE)
    t_ext, h_ext = lire_capteur(PORT_CAPTEUR_EXTERNE)

    t_int_aff  = f"{t_int} C"        if t_int is not None        else "--"
    poids_aff  = f"{poids_actuel} kg" if poids_actuel is not None else "En attente"
    ref_aff    = f"ref: {poids_reference} kg" if poids_reference is not None else "ref: calcul en cours"
    print(f"[{time.strftime('%H:%M:%S')}] Temp Int: {t_int_aff} | Poids: {poids_aff} ({ref_aff})")

    # on bloque les alertes tant qu'on a pas le poids de base
    if poids_reference is None:
        print("Attente des 2 premieres mesures pour etablir la reference...")
        time.sleep(5)
        continue

    # moteur d'alertes
    motifs_actuels = []

    # mes verifications de temperature
    if t_int is not None:
        if t_int > SEUIL_TEMP_HAUTE:
            motifs_actuels.append(f"Surchauffe detectee ({t_int} C)")
        elif t_int < SEUIL_TEMP_BASSE:
            motifs_actuels.append(f"Refroidissement anormal ({t_int} C)")

    # verification du poids (partie julien)
    if flag_alerte_poids:
        flag_alerte_poids = False
        motifs_actuels.append(f"Chute de poids de {valeur_chute_poids} kg (ref: {poids_reference} kg -> actuel: {poids_actuel} kg)")

    # declenchement alerte globale
    if any("Chute de poids" in m for m in motifs_actuels):
        # je verifie mon delai anti-spam avant d'envoyer
        if maintenant - dernier_mail_poids >= INTERVALLE_ALERTE:
            envoyer_alerte_mail("ALERTE : Chute de poids sur la ruche !", motifs_actuels)
            dernier_mail_poids       = maintenant
            dernier_mail_temperature = maintenant  
        else:
            print(f"   (anti-spam poids : prochain dans {int(INTERVALLE_ALERTE-(maintenant-dernier_mail_poids))}s)")

    # declenchement alerte juste pour la temperature
    elif len(motifs_actuels) > 0:
        if maintenant - dernier_mail_temperature >= INTERVALLE_ALERTE:
            envoyer_alerte_mail("ALERTE : Temperature anormale sur la ruche !", motifs_actuels)
            dernier_mail_temperature = maintenant
        else:
            print(f"   (anti-spam temp : prochain dans {int(INTERVALLE_ALERTE-(maintenant-dernier_mail_temperature))}s)")

    time.sleep(15)