import requests
import json
import time
import smtplib
import math
import grovepi
import paho.mqtt.client as mqtt
import serial
import RPi.GPIO as GPIO
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

# temps d'attente pour le reseau au demarrage
time.sleep(15)

# configuration reseau et securite
URL_BEEAPP   = "http://beeapp.madebylucas.fr/reception.php"
URL_MELAINA  = "https://melaina.madebylucas.fr/reception.php"
URL_CONFIG   = "https://melaina.madebylucas.fr/config.json"

EMAIL_EXPEDITEUR   = "melainaprojet@gmail.com"
MOT_DE_PASSE_APP   = "yomvqexmhqttddwj"
EMAIL_DESTINATAIRE = "melainaprojet@gmail.com"

# ma partie : configuration materiel (module 4g et capteurs)
NUMERO_MME_ELISABETH = "+596696661545" # numero de l'apicultrice
POWER_KEY = 6
PORT_SERIE_4G = '/dev/ttyUSB2' # port specifique pour envoyer les commandes at

# je declare les broches du grovepi pour mes capteurs
port_meteo = 4  # capteur meteo exterieur sur le port numerique D4
port_sante = 3  # capteur interieur ruche sur le port numerique D3
dht_type = 0    # 0 pour dire que c'est le modele dht11 (bleu)

# variables mqtt pour la partie de julien (ruche fille)
julien_temp_interne = 30.0
julien_poids_ruche = 0.0
poids_precedent = 0.0

# reglages de debug
ACTIVER_ALERTES = True  # je met sur false quand je test pour pas vider le forfait

TEMPS_BOUCLE = 30      
DELAI_RAPPEL = 120     
INTERVALLE_SMS = 300   # on limite a 1 sms toutes les 5 min pour eviter le spam
INTERVALLE_APPEL = 600 # on limite les appels a 1 toutes les 10 min

def lire_config_du_site():
    try:
        r = requests.get(f"{URL_CONFIG}?t={time.time()}", timeout=5)
        if r.status_code == 200:
            conf = r.json()
            return float(conf.get("seuil_chaud", 38.0)), float(conf.get("seuil_froid", 15.0)), bool(conf.get("notif_push", True))
    except Exception as e:
        print(f"Erreur config.json ({e}). Utilisation des seuils de secours.")
    return 38.0, 15.0, True

def envoyer_donnees_bdd(id_ruche, t, h, p, m):
    # si le capteur renvoie nan je force a -999 pour pas faire planter le php
    if math.isnan(t): t = -999.0
    if math.isnan(h): h = -999.0
    if math.isnan(p): p = 0.0

    payload = {"id_ruche": id_ruche, "temperature": round(t, 1), "humidite": round(h, 1), "poids": round(p, 2), "latitude": 14.8347, "longitude": -61.0588, "meteo": m}
    for url in [URL_BEEAPP, URL_MELAINA]:
        try: requests.post(url, data=json.dumps(payload), headers={'Content-type': 'application/json'}, timeout=10)
        except: pass

def envoyer_rapport_alerte(nom_ruche, type_alerte, urgence, diagnostic, conseil):
    if not ACTIVER_ALERTES:
        return

    couleurs = {
        "URGENT":            "#D32F2F",
        "CRITIQUE":          "#D32F2F",
        "IMPORTANT":         "#F57C00",
        "ALERTE BIOLOGIQUE": "#E65100",
        "INFO":              "#1976D2"
    }
    couleur = couleurs.get(urgence, "#424242")

    horodatage = time.strftime('%d/%m/%Y à %H:%M:%S')
    sujet = f"[{urgence}] {nom_ruche} : {type_alerte} - BeeSecure"

    corps_texte = f"""BEE SECURE MONITORING SYSTEM
RAPPORT D'INCIDENT
============================================================
CIBLE             : {nom_ruche}
TYPE D'ALERTE     : {type_alerte}
NIVEAU D'URGENCE  : {urgence}
HORODATAGE        : {horodatage}

DIAGNOSTIC TECHNIQUE :
{diagnostic}

RECOMMANDATION :
{conseil}
============================================================"""

    corps_html = f"""\
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><title>BeeSecure - Alerte</title></head>
<body style="margin:0;padding:20px;background-color:#f4f6f8;font-family:Arial,Helvetica,sans-serif;">
  <table align="center" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.15);">
    <tr>
      <td style="background:linear-gradient(135deg,#FFB300 0%,#FF6F00 100%);padding:30px 20px;text-align:center;">
        <h1 style="margin:0;color:#ffffff;font-size:32px;letter-spacing:1px;">BeeSecure</h1>
        <p style="margin:8px 0 0 0;color:#fff8e1;font-size:14px;">Système de Surveillance Apicole Connectée</p>
      </td>
    </tr>
    <tr>
      <td style="background:{couleur};padding:20px;text-align:center;">
        <h2 style="margin:0;color:#ffffff;font-size:24px;">{type_alerte}</h2>
        <p style="margin:8px 0 0 0;color:#ffffff;font-weight:bold;font-size:16px;letter-spacing:2px;">NIVEAU : {urgence}</p>
      </td>
    </tr>
    <tr>
      <td style="padding:30px;">
        <table style="width:100%;border-collapse:collapse;font-size:15px;">
          <tr>
            <td style="padding:12px 8px;border-bottom:1px solid #e0e0e0;color:#666;width:40%;"><strong>Cible</strong></td>
            <td style="padding:12px 8px;border-bottom:1px solid #e0e0e0;color:#212121;">{nom_ruche}</td>
          </tr>
          <tr>
            <td style="padding:12px 8px;border-bottom:1px solid #e0e0e0;color:#666;"><strong>Horodatage</strong></td>
            <td style="padding:12px 8px;border-bottom:1px solid #e0e0e0;color:#212121;">{horodatage}</td>
          </tr>
          <tr>
            <td style="padding:12px 8px;border-bottom:1px solid #e0e0e0;color:#666;"><strong>Type d'alerte</strong></td>
            <td style="padding:12px 8px;border-bottom:1px solid #e0e0e0;color:#212121;">{type_alerte}</td>
          </tr>
        </table>

        <div style="background:#FFF8E1;border-left:5px solid #FFB300;padding:18px;margin:24px 0;border-radius:6px;">
          <h3 style="margin:0 0 10px 0;color:#E65100;font-size:17px;">Diagnostic technique</h3>
          <p style="margin:0;color:#424242;line-height:1.5;">{diagnostic}</p>
        </div>

        <div style="background:#E8F5E9;border-left:5px solid #4CAF50;padding:18px;margin:24px 0;border-radius:6px;">
          <h3 style="margin:0 0 10px 0;color:#1B5E20;font-size:17px;">Recommandation</h3>
          <p style="margin:0;color:#424242;line-height:1.5;">{conseil}</p>
        </div>
      </td>
    </tr>
    <tr>
      <td style="background:#263238;padding:20px;text-align:center;">
        <p style="margin:0;color:#b0bec5;font-size:12px;">Rapport généré automatiquement par <strong style="color:#FFB300;">BeeSecure</strong></p>
        <p style="margin:6px 0 0 0;color:#78909c;font-size:11px;">© {time.strftime('%Y')} · Système de surveillance apicole connectée</p>
      </td>
    </tr>
  </table>
</body>
</html>"""

    print(f"Envoi Email pour {nom_ruche} : {type_alerte}")
    msg = MIMEMultipart('alternative')
    msg['From']    = EMAIL_EXPEDITEUR
    msg['To']      = EMAIL_DESTINATAIRE
    msg['Subject'] = sujet
    msg.attach(MIMEText(corps_texte, 'plain', 'utf-8'))
    msg.attach(MIMEText(corps_html,  'html',  'utf-8'))

    for tentative in range(2):
        try:
            server = smtplib.SMTP('smtp.gmail.com', 587, timeout=30)
            server.ehlo()
            server.starttls()
            server.ehlo()
            server.login(EMAIL_EXPEDITEUR, MOT_DE_PASSE_APP)
            server.sendmail(EMAIL_EXPEDITEUR, EMAIL_DESTINATAIRE, msg.as_string())
            server.quit()
            print("Email envoye avec succes.")
            return
        except Exception as e:
            print(f"Erreur SMTP (tentative {tentative+1}/2) : {e}")
            if tentative == 0:
                time.sleep(3)
    print("Echec d'envoi du mail apres 2 tentatives.")


def envoyer_sms_urgence(message_sms):
    # fonction pour envoyer les sms en cas de probleme sur la ruche
    if not ACTIVER_ALERTES:
        return

    print("Envoi SMS d'urgence via 4G")
    
    # j'allume le module sim7600 physiquement avec la broche 6
    print('Allumage du modem SIM7600...')
    GPIO.setmode(GPIO.BCM)
    GPIO.setwarnings(False)
    GPIO.setup(POWER_KEY, GPIO.OUT)
    GPIO.output(POWER_KEY, GPIO.HIGH)
    time.sleep(2)
    GPIO.output(POWER_KEY, GPIO.LOW)
    time.sleep(20) # il lui faut du temps pour trouver le reseau

    try:
        # je me connecte sur le port serie de la 4g
        ser = serial.Serial(PORT_SERIE_4G, 115200, timeout=5)
        
        # je nettoie le message pour enlever les accents qui font bugger
        msg_clean = message_sms.encode('ascii', 'ignore').decode('ascii')
        
        # commande at pour passer en mode texte
        ser.write(b'AT+CMGF=1\r\n')
        time.sleep(1)
        
        # je rentre le numero
        ser.write(('AT+CMGS="' + NUMERO_MME_ELISABETH + '"\r\n').encode())
        time.sleep(1)
        
        # j'envoie le texte. le \x1a c'est le ctrl+z pour valider l'envoi
        ser.write((msg_clean + '\x1a').encode())
        time.sleep(5)
        ser.close()
        print("SMS transmis au reseau")
    except Exception as e:
        print(f"Erreur SMS 4G : {e}")

    # j'eteins pour pas vider la batterie
    print('Extinction du modem SIM7600...')
    GPIO.output(POWER_KEY, GPIO.HIGH)
    time.sleep(3)
    GPIO.output(POWER_KEY, GPIO.LOW)
    time.sleep(18)


def passer_appel_urgence():
    # appel vocal automatique pour reveiller mme elisabeth
    if not ACTIVER_ALERTES:
        return

    print(f"Appel d'urgence -> {NUMERO_MME_ELISABETH}")
    
    # j'allume le module
    print('Allumage du modem SIM7600...')
    GPIO.setmode(GPIO.BCM)
    GPIO.setwarnings(False)
    GPIO.setup(POWER_KEY, GPIO.OUT)
    GPIO.output(POWER_KEY, GPIO.HIGH)
    time.sleep(2)
    GPIO.output(POWER_KEY, GPIO.LOW)
    time.sleep(20)

    try:
        ser = serial.Serial(PORT_SERIE_4G, 115200, timeout=5)
        
        ser.write(b'AT\r\n')
        time.sleep(1)

        # commande pour appeler. le point virgule est obligatoire sinon ca marche pas
        ser.write(f'ATD{NUMERO_MME_ELISABETH};\r\n'.encode())
        print("Sonnerie en cours (25 secondes)...")
        time.sleep(25) # je laisse sonner 25 sec

        # commande pour raccrocher
        ser.write(b'ATH\r\n')
        time.sleep(1)
        ser.close()
        print("Appel d'urgence termine.")
    except Exception as e:
        print(f"Erreur Appel 4G : {e}")

    # j'eteins le module
    print('Extinction du modem SIM7600...')
    GPIO.output(POWER_KEY, GPIO.HIGH)
    time.sleep(3)
    GPIO.output(POWER_KEY, GPIO.LOW)
    time.sleep(18)


# fonctions mqtt pour la reception de julien
def on_connect(client, userdata, flags, rc, properties=None):
    if rc == 0:
        print("Connecte au broker MQTT local avec succes")
        client.subscribe("melaina/ruche_fille/temperature")
        client.subscribe("melaina/ruche_fille/poids")
    else:
        print(f"Echec de connexion MQTT (Code: {rc})")

def on_message(client, userdata, msg):
    global julien_temp_interne, julien_poids_ruche
    try:
        data = json.loads(msg.payload.decode())
        if "temperature" in msg.topic: 
            julien_temp_interne = float(data['valeur'])
        elif "poids" in msg.topic: 
            julien_poids_ruche = float(data['valeur'])
    except Exception as e:
        print(f"Erreur decodage MQTT : {e}")

client_mqtt = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
client_mqtt.on_connect = on_connect
client_mqtt.on_message = on_message
client_mqtt.connect("127.0.0.1", 1883, 60)
client_mqtt.loop_start() 

def afficher_log(t_meteo, h_meteo, t_mere, h_mere, t_fille, p_fille, s_chaud, s_froid):
    print("\n--------------------------------------------------")
    print(f"CYCLE DE MESURE : {time.strftime('%H:%M:%S')}")
    print(f"SEUILS ACTIFS  : Froid={s_froid}C | Chaud={s_chaud}C")
    print(f"METEO (D4)     : Temp = {t_meteo}C | Hum = {h_meteo}%")
    print(f"MERE  (D3)     : Temp = {t_mere}C | Hum = {h_mere}%")
    print(f"FILLE (MQTT)   : Temp = {t_fille}C | Poids = {p_fille} kg")
    print("--------------------------------------------------")

# boucle principale
etats          = {"chaud": 0, "froid": 0, "essaim": 0, "vol": 0, "recolte": 0}
derniers_sms   = {"chaud": 0, "froid": 0, "essaim": 0, "vol": 0, "recolte": 0}
derniers_appels = {"essaim": 0, "vol": 0}

print("Systeme BeeSecure Demarre")

while True:
    try:
        maintenant = time.time()
        s_chaud, s_froid, active = lire_config_du_site()

        # ma partie : lecture de la temperature et humidite
        # je met un try except pour pas que le code plante si le fil bouge
        try:
            # je lis le port D4
            t_meteo, h_meteo = grovepi.dht(port_meteo, dht_type)
            
            # si le capteur renvoie nan je met une valeur par defaut -999
            if math.isnan(t_meteo): t_meteo = -999.0
            if math.isnan(h_meteo): h_meteo = -999.0
        except: 
            t_meteo, h_meteo = -999.0, -999.0

        # je fais la meme chose pour le port D3
        try:
            t_mere, h_mere = grovepi.dht(port_sante, dht_type)
            if math.isnan(t_mere): t_mere = -999.0
            if math.isnan(h_mere): h_mere = -999.0
        except: 
            t_mere, h_mere = -999.0, -999.0

        afficher_log(t_meteo, h_meteo, t_mere, h_mere, julien_temp_interne, julien_poids_ruche, s_chaud, s_froid)

        envoyer_donnees_bdd(1, t_meteo, h_meteo, 0, "Meteo")
        envoyer_donnees_bdd(2, t_mere, h_mere, 0, "Sante")
        envoyer_donnees_bdd(3, julien_temp_interne, 60.0, julien_poids_ruche, "Sante")

        # gestion des alertes
        if active:
            # surchauffe
            if julien_temp_interne >= s_chaud and julien_temp_interne != -999.0:
                if maintenant - etats["chaud"] >= DELAI_RAPPEL:
                    envoyer_rapport_alerte("RUCHE FILLE", "SURCHAUFFE CRITIQUE", "URGENT", f"Temp : {julien_temp_interne}C (Seuil: {s_chaud}C)", "Ventilez la ruche.")
                    etats["chaud"] = maintenant
                if maintenant - derniers_sms["chaud"] >= INTERVALLE_SMS:
                    envoyer_sms_urgence(f"[BeeSecure] SURCHAUFFE : {julien_temp_interne}C. Risque de fonte des cires !")
                    derniers_sms["chaud"] = maintenant
            else: etats["chaud"] = 0

            # hypothermie
            if 0 < julien_temp_interne <= s_froid:
                if maintenant - etats["froid"] >= DELAI_RAPPEL:
                    envoyer_rapport_alerte("RUCHE FILLE", "HYPOTHERMIE", "IMPORTANT", f"Temp : {julien_temp_interne}C (Seuil: {s_froid}C)", "Verifiez l'isolation.")
                    etats["froid"] = maintenant
                if maintenant - derniers_sms["froid"] >= INTERVALLE_SMS:
                    envoyer_sms_urgence(f"[BeeSecure] HYPOTHERMIE : {julien_temp_interne}C. Colonie en danger.")
                    derniers_sms["froid"] = maintenant
            else: etats["froid"] = 0

            # essaimage
            if poids_precedent > 0 and (poids_precedent - julien_poids_ruche) >= 2.0:
                perte = round(poids_precedent - julien_poids_ruche, 1)
                if maintenant - etats["essaim"] >= DELAI_RAPPEL:
                    envoyer_rapport_alerte("RUCHE FILLE", "SUSPICION D'ESSAIMAGE", "ALERTE BIOLOGIQUE", f"Perte de {perte} kg", "Un essaim a quitte la ruche.")
                    etats["essaim"] = maintenant
                if maintenant - derniers_sms["essaim"] >= INTERVALLE_SMS:
                    envoyer_sms_urgence(f"[BeeSecure] ESSAIMAGE DETECTE ! Perte : {perte} kg. Appel en cours...")
                    derniers_sms["essaim"] = maintenant
                if maintenant - derniers_appels["essaim"] >= INTERVALLE_APPEL:
                    passer_appel_urgence()
                    derniers_appels["essaim"] = maintenant
            else: etats["essaim"] = 0

            # vol ou chute
            if 0.0 < julien_poids_ruche <= 5.0:
                if maintenant - etats["vol"] >= DELAI_RAPPEL:
                    envoyer_rapport_alerte("RUCHE FILLE", "ALERTE VOL / RENVERSEMENT", "CRITIQUE", f"Poids critique : {julien_poids_ruche} kg", "Intervention immediate requise.")
                    etats["vol"] = maintenant
                if maintenant - derniers_sms["vol"] >= INTERVALLE_SMS:
                    envoyer_sms_urgence(f"[BeeSecure] ALERTE VOL / RENVERSEMENT ! Poids : {julien_poids_ruche} kg. Appel en cours...")
                    derniers_sms["vol"] = maintenant
                if maintenant - derniers_appels["vol"] >= INTERVALLE_APPEL:
                    passer_appel_urgence()
                    derniers_appels["vol"] = maintenant
            elif julien_poids_ruche > 5.0:
                etats["vol"] = 0
            
            # recolte
            if julien_poids_ruche >= 40.0:
                if maintenant - etats["recolte"] >= DELAI_RAPPEL:
                    envoyer_rapport_alerte("RUCHE FILLE", "SEUIL DE RECOLTE ATTEINT", "INFO", f"Poids : {julien_poids_ruche} kg", "Preparez les hausses.")
                    etats["recolte"] = maintenant
                if maintenant - derniers_sms["recolte"] >= INTERVALLE_SMS:
                    envoyer_sms_urgence(f"[BeeSecure] RECOLTE PRETE. Poids : {julien_poids_ruche} kg.")
                    derniers_sms["recolte"] = maintenant
            else: etats["recolte"] = 0

        poids_precedent = julien_poids_ruche

    except Exception as e:
        print(f"Erreur de cycle general : {e}")

    time.sleep(TEMPS_BOUCLE)