GNU nano 8.4                                                                                                   capteur_poids_off                                                                                                             
from Phidget22.Phidget import *
from Phidget22.Devices.VoltageRatioInput import *
import time

# --- VARIABLES DE CALIBRATION (À REMPLIR APRÈS TES TESTS) ---

# 1. L'OFFSET (La Tare) : La valeur brute quand il n'y a AUCUN poids sur le capteur.
# Remplace les 0.0 par les valeurs que tu as lues à vide pour les canaux 0, 1, 2 et 3.
OFFSETS = [-0.00000291, -0.00000036, -0.00000268, -0.00000003]

# 2. LE SCALE (Le Coefficient) : Le multiplicateur pour passer en Kg.
# Remplace les 1.0 par les coefficients que tu vas calculer.
SCALES = [51842.4, 51842.4, 51842.4, 51842.4]

poids_total = 0.0

print("--- Démarrage de la pesée de la ruche ---")

for canal in range(4):
    try:
        b = VoltageRatioInput()
        b.setChannel(canal)
        b.openWaitForAttachment(3000)

        # On laisse un petit temps au capteur pour se stabiliser
        time.sleep(1)

        # Lecture de la valeur électrique brute
        val_brute = b.getVoltageRatio()

        # Application de la formule mathématique : Poids = (Brut - Offset) * Scale
        poids_capteur = (val_brute - OFFSETS[canal]) * SCALES[canal]

        print(f"Canal {canal} : Brut = {val_brute:.7f} | Poids estimé = {poids_capteur:.2f} kg")

        # On ajoute le poids de ce pied au poids total de la ruche
        poids_total += poids_capteur

        b.close()

    except Exception as e:
        print(f"Canal {canal} : ERREUR - {e}")

print("-" * 40)
print(f"POIDS TOTAL DE LA RUCHE : {poids_total:.2f} kg")
print("-" * 40)