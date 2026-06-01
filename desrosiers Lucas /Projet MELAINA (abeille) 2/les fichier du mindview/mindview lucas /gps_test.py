import RPi.GPIO as GPIO
import serial
import time

power_key = 6

try:
    ser = serial.Serial('/dev/serial0', 115200)
    ser.flushInput()
except Exception as e:
    print(f"Erreur d'ouverture du port série: {e}")
    ser = None

def send_at(command, back, timeout):
    rec_buff = ''
    ser.write((command+'\r\n').encode())
    time.sleep(timeout)
    if ser.inWaiting():
        time.sleep(0.01)
        rec_buff = ser.read(ser.inWaiting())
    if rec_buff != '':
        if back not in rec_buff.decode():
            return False
        else:
            return True
    else:
        return False

def convert_nmea_to_decimal(nmea_str, direction):
    """Convertit le format NMEA (DDMM.MMMM) en Degrés Décimaux (DD.DDDD)"""
    if not nmea_str:
        return 0.0
    
    # On trouve où est le point décimal
    dot_idx = nmea_str.find('.')
    if dot_idx == -1: 
        return 0.0
        
    # Les degrés sont tout ce qu'il y a avant les 2 derniers chiffres entiers
    degrees = float(nmea_str[:dot_idx-2])
    # Les minutes sont le reste
    minutes = float(nmea_str[dot_idx-2:])
    
    decimal = degrees + (minutes / 60.0)
    
    # Si c'est Sud (S) ou Ouest (W), la coordonnée devient négative
    if direction == 'S' or direction == 'W':
        decimal = -decimal
        
    return round(decimal, 6)

def get_gps_position():
    print('\n--- Activation du GPS ---')
    # On allume la puce GPS
    send_at('AT+CGPS=1,1', 'OK', 1)
    time.sleep(2)
    
    print("Recherche des satellites en cours...")
    print("⚠️ Attention: L'antenne GNSS doit être près d'une fenêtre ou en extérieur !")
    
    # On fait 60 tentatives (soit environ 2 minutes) pour capter le signal
    for i in range(60):
        ser.write(('AT+CGPSINFO\r\n').encode())
        time.sleep(2) # On attend 2 sec entre chaque vérification
        
        if ser.inWaiting():
            rep = ser.read(ser.inWaiting()).decode().strip()
            
            # Si le GPS n'a pas encore de position, il renvoie plein de virgules vides: +CGPSINFO: ,,,,,,,,
            if "+CGPSINFO: ," in rep or "+CGPSINFO: ,,,,,,,," in rep:
                print(f"Tentative {i+1}/60 : En attente du signal satellite...")
            
            # Si on reçoit des données non vides
            elif "+CGPSINFO:" in rep:
                try:
                    # On extrait la ligne de données
                    data_str = rep.split("+CGPSINFO: ")[1].split("\r")[0]
                    parts = data_str.split(',')
                    
                    if len(parts) >= 4:
                        lat_nmea = parts[0]
                        lat_dir = parts[1]
                        lon_nmea = parts[2]
                        lon_dir = parts[3]
                        
                        # On utilise notre fonction pour faire la conversion
                        lat_dec = convert_nmea_to_decimal(lat_nmea, lat_dir)
                        lon_dec = convert_nmea_to_decimal(lon_nmea, lon_dir)
                        
                        print("\n✅ --- POSITION TROUVÉE ! ---")
                        print(f"Latitude  (Décimal) : {lat_dec}")
                        print(f"Longitude (Décimal) : {lon_dec}")
                        print(f"📍 Lien Google Maps : https://www.google.com/maps?q={lat_dec},{lon_dec}")
                        
                        # On éteint le GPS pour économiser l'énergie
                        send_at('AT+CGPS=0', 'OK', 1)
                        return True
                except Exception as e:
                    print(f"Erreur de lecture: {e}")
                    
    print("\n❌ Échec: Impossible de capter les satellites.")
    print("Vérifie que l'antenne carrée est bien branchée et à l'extérieur.")
    send_at('AT+CGPS=0', 'OK', 1) # On éteint le GPS
    return False

def power_on(power_key):
    print('SIM7600X is starting:')
    GPIO.setmode(GPIO.BCM)
    GPIO.setwarnings(False)
    GPIO.setup(power_key,GPIO.OUT)
    time.sleep(0.1)
    GPIO.output(power_key,GPIO.HIGH)
    time.sleep(2)
    GPIO.output(power_key,GPIO.LOW)
    time.sleep(20)
    ser.flushInput()
    print('SIM7600X is ready')

def power_down(power_key):
    print('SIM7600X is loging off:')
    GPIO.output(power_key,GPIO.HIGH)
    time.sleep(3)
    GPIO.output(power_key,GPIO.LOW)
    time.sleep(18)
    print('Good bye')

# --- BLOC PRINCIPAL ---
try:
    power_on(power_key)
    
    # On lance la recherche GPS
    get_gps_position()
    
    power_down(power_key)

except KeyboardInterrupt:
    print("\nArrêt manuel.")
except Exception as e:
    print(f"Erreur globale: {e}")
finally:
    if ser != None:
        ser.close()
    GPIO.cleanup()
