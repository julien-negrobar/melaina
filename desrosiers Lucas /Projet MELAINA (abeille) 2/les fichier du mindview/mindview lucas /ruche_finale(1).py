import requests
import json
import time
import smtplib
import math
import grovepi
import paho.mqtt.client as mqtt
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

# ==========================================
# 1. CONFIGURATION RÉSEAU & SÉCURITÉ
# ==========================================
URL_BEEAPP   = "http://beeapp.madebylucas.fr/reception.php"
URL_MELAINA  = "https://melaina.madebylucas.fr/reception.php"
URL_CONFIG   = "https://melaina.madebylucas.fr/config.json"

EMAIL_EXPEDITEUR   = "melainaprojet@gmail.com"
MOT_DE_PASSE_APP   = "hznxgrlksihypdgp" 
EMAIL_DESTINATAIRE = "melainaprojet@gmail.com"

port_sensor = 4 
dht_type = 0    

julien_temp_interne = 30.0
julien_poids_ruche = 0.0
poids_precedent = 0.0 

# ==========================================
# 🚨 LE MODE DÉMO POUR L'EXAMEN 🚨
# ==========================================
MODE_DEMO = True  # Mettre à False quand la ruche sera vraiment dehors !

if MODE_DEMO:
    TEMPS_BOUCLE = 5      # 5 secondes entre chaque vérification au lieu de 30s
    DELAI_RAPPEL = 10     # 10 secondes avant de renvoyer un email de rappel au lieu de 5 min
    print("⚠️ ATTENTION : MODE DÉMO ACTIVÉ. Les délais sont raccourcis et le spam est possible ! ⚠️")
else:
    TEMPS_BOUCLE = 30     # Mode Production (Normal)
    DELAI_RAPPEL = 300    # 5 minutes en mode normal

# ==========================================
# 2. MOTEUR DE GÉNÉRATION D'EMAILS PRO
# ==========================================
def envoyer_rapport_alerte(type_alerte, urgence, diagnostic, conseil):
    sujet = f"[{urgence}] {type_alerte} - BeeSecure"
    
    corps = f"""BEE SECURE MONITORING SYSTEM
RAPPORT D'INCIDENT AUTOMATISÉ - UNITÉ BETA-VORTEX
------------------------------------------------------------

TYPE D'ALERTE : {type_alerte}
NIVEAU D'URGENCE : {urgence}
HORODATAGE : {time.strftime('%d/%m/%Y à %H:%M:%S')}

DIAGNOSTIC TECHNIQUE :
{diagnostic}

RECOMMANDATION :
{conseil}

------------------------------------------------------------
ACCÈS AUX DONNÉES EN TEMPS RÉEL :
Interface Web (BeeApp) : http://beeapp.madebylucas.fr/webapp.php

APPLICATION MOBILE MELAINA :
Veuillez vous connecter à votre application mobile pour consulter 
les graphiques détaillés et acquitter cette alerte.
Si vous n'avez pas l'application, téléchargez-la sur : 
https://melaina.madebylucas.fr
------------------------------------------------------------
Ceci est une notification automatique. Ne pas répondre à ce mail."""

    print(f"✉️ Envoi du rapport : {type_alerte}")
    msg = MIMEMultipart()
    msg['From'] = EMAIL_EXPEDITEUR
    msg['To'] = EMAIL_DESTINATAIRE
    msg['Subject'] = sujet
    msg.attach(MIMEText(corps, 'plain'))
    
    try:
        server = smtplib.SMTP('smtp.gmail.com', 587)
        server.starttls()
        server.login(EMAIL_EXPEDITEUR, MOT_DE_PASSE_APP)
        server.sendmail(EMAIL_EXPEDITEUR, EMAIL_DESTINATAIRE, msg.as_string())
        server.quit()
        print("✅ Rapport envoyé avec succès.")
    except Exception as e:
        print(f"❌ Erreur de transmission SMTP : {e}")

def lire_config_du_site():
    try:
        r = requests.get(f"{URL_CONFIG}?t={time.time()}", timeout=10)
        conf = r.json()
        return float(conf.get("seuil_chaud", 37.4)), float(conf.get("seuil_froid", 20.0)), bool(conf.get("notif_push", True))
    except:
        return 37.4, 20.0, True

def envoyer_donnees_bdd(id_ruche, t, h, p, m):
    payload = {"id_ruche": id_ruche, "temperature": round(t, 1), "humidite": round(h, 1), "poids": round(p, 2), "latitude": 14.8347, "longitude": -61.0588, "meteo": m}
    for url in [URL_BEEAPP, URL_MELAINA]:
        try: requests.post(url, data=json.dumps(payload), headers={'Content-type': 'application/json'}, timeout=10)
        except: pass

def on_connect(client, userdata, flags, rc, properties=None):
    client.subscribe("melaina/ruche_fille/temperature")
    client.subscribe("melaina/ruche_fille/poids")

def on_message(client, userdata, msg):
    global julien_temp_interne, julien_poids_ruche
    data = json.loads(msg.payload.decode())
    if "temperature" in msg.topic: julien_temp_interne = float(data['valeur'])
    if "poids" in msg.topic: julien_poids_ruche = float(data['valeur'])

# ==========================================
# 3. INITIALISATION & BOUCLE
# ==========================================
client_mqtt = mqtt.Client(mqtt.CallbackAPIVersion.VERSION2)
client_mqtt.on_connect = on_connect
client_mqtt.on_message = on_message
client_mqtt.connect("localhost", 1883)
client_mqtt.loop_start()

etats = {"chaud": 0, "froid": 0, "recolte": 0, "vol": 0, "essaim": 0}

print("🚀 Système BeeSecure V3 - Initialisation terminée.")

while True:
    try:
        maintenant = time.time()
        s_chaud, s_froid, active = lire_config_du_site()

        try:
            t_ext, h_ext = grovepi.dht(port_sensor, dht_type)
            if math.isnan(t_ext): t_ext, h_ext = 26.5, 65.0
        except: t_ext, h_ext = 26.5, 65.0

        envoyer_donnees_bdd(1, t_ext, h_ext, 0, "Météo")
        envoyer_donnees_bdd(2, julien_temp_interne, 60.0, julien_poids_ruche, "Santé")

        # --- GESTION DES ALERTES & RAPPELS ---
        if active:
            # 🚨 1. SURCHAUFFE
            if julien_temp_interne >= s_chaud:
                if etats["chaud"] == 0 or (maintenant - etats["chaud"] >= DELAI_RAPPEL):
                    prefixe = "ALERTE" if etats["chaud"] == 0 else "RAPPEL CONTINU"
                    diag = f"- Température interne : {julien_temp_interne}°C\n- Seuil maximal : {s_chaud}°C\n- Écart critique : +{round(julien_temp_interne-s_chaud,1)}°C"
                    conseil = "Une intervention physique est requise pour assurer la ventilation de la ruche et prévenir la fonte des cires."
                    envoyer_rapport_alerte(f"{prefixe} : SURCHAUFFE CRITIQUE", "URGENT", diag, conseil)
                    etats["chaud"] = maintenant
            else: 
                etats["chaud"] = 0

            # ❄️ 2. HYPOTHERMIE
            if julien_temp_interne <= s_froid:
                if etats["froid"] == 0 or (maintenant - etats["froid"] >= DELAI_RAPPEL):
                    prefixe = "ALERTE" if etats["froid"] == 0 else "RAPPEL CONTINU"
                    diag = f"- Température interne : {julien_temp_interne}°C\n- Seuil minimal : {s_froid}°C\n- Déficit thermique : {round(s_froid-julien_temp_interne,1)}°C"
                    conseil = "La grappe d'abeilles est en danger. Vérifiez l'isolation de la ruche ou la présence de nourriture suffisante."
                    envoyer_rapport_alerte(f"{prefixe} : HYPOTHERMIE DÉTECTÉE", "IMPORTANT", diag, conseil)
                    etats["froid"] = maintenant
            else: 
                etats["froid"] = 0

            # 🐝 3. ESSAIMAGE
            if poids_precedent > 0 and (poids_precedent - julien_poids_ruche) >= 2.0:
                if etats["essaim"] == 0 or (maintenant - etats["essaim"] >= DELAI_RAPPEL):
                    prefixe = "ALERTE" if etats["essaim"] == 0 else "RAPPEL CONTINU"
                    diag = f"- Masse perdue : {round(poids_precedent - julien_poids_ruche, 2)} kg\n- Poids actuel : {julien_poids_ruche} kg"
                    conseil = "La chute de poids brutale indique le départ probable d'un essaim. Tentez de localiser l'essaim à proximité."
                    envoyer_rapport_alerte(f"{prefixe} : SUSPICION D'ESSAIMAGE", "ALERTE BIOLOGIQUE", diag, conseil)
                    etats["essaim"] = maintenant
            else: 
                etats["essaim"] = 0

            # 🍯 4. RÉCOLTE
            if julien_poids_ruche >= 40.0:
                if etats["recolte"] == 0 or (maintenant - etats["recolte"] >= DELAI_RAPPEL):
                    prefixe = "NOTIFICATION" if etats["recolte"] == 0 else "RAPPEL"
                    diag = f"- Masse totale : {julien_poids_ruche} kg\n- Objectif de récolte : 40.0 kg"
                    conseil = "Le poids cible est atteint. Planifiez une visite pour la récolte des hausses de miel."
                    envoyer_rapport_alerte(f"{prefixe} : SEUIL DE RÉCOLTE ATTEINT", "GESTION", diag, conseil)
                    etats["recolte"] = maintenant
            else: 
                etats["recolte"] = 0

            # 🚨 5. VOL / CHUTE
            if 0.0 < julien_poids_ruche <= 5.0:
                if etats["vol"] == 0 or (maintenant - etats["vol"] >= DELAI_RAPPEL):
                    prefixe = "ALERTE" if etats["vol"] == 0 else "RAPPEL CONTINU"
                    diag = f"- Poids résiduel : {julien_poids_ruche} kg\n- Alerte déclenchée sous : 5.0 kg"
                    conseil = "Alerte de sécurité majeure. La ruche est peut-être renversée ou a été déplacée. Intervention immédiate conseillée."
                    envoyer_rapport_alerte(f"{prefixe} : ALERTE SÉCURITÉ / POIDS", "CRITIQUE", diag, conseil)
                    etats["vol"] = maintenant
            elif julien_poids_ruche > 5.0: 
                etats["vol"] = 0

        poids_precedent = julien_poids_ruche
    except Exception as e: print(f"⚠️ Erreur de cycle : {e}")
    
    # 🔴 On utilise la variable dynamique au lieu de "30" en dur
    time.sleep(TEMPS_BOUCLE)