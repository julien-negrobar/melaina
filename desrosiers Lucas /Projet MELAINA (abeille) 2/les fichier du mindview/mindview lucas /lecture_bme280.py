#!/usr/bin/env python3
# -*- coding: utf-8 -*-

"""
Script de lecture du capteur BME280 (Température, Humidité, Pression)
Projet MELAINA - BTS CIEL IR 2026
Étudiant 2 : Lucas
Lycée Joseph Gaillard - Fort-de-France

Ce script permet de :
- Lire la température extérieure
- Lire l'humidité ambiante
- Lire la pression atmosphérique
- Afficher les données en temps réel
- (Optionnel) Sauvegarder les données dans un fichier
"""

# ============================================
# IMPORTS DES BIBLIOTHÈQUES
# ============================================

import time
from datetime import datetime
import sys

# Librairie pour le capteur BME280 Grove
try:
    from grove.grove_bme280 import BME280
except ImportError:
    print("ERREUR : Librairie grove.grove_bme280 non trouvée !")
    print("Installation : sudo pip3 install grove.py")
    sys.exit(1)

# ============================================
# CONFIGURATION
# ============================================

# Fréquence de lecture (en secondes)
FREQUENCE_LECTURE = 5  # Lecture toutes les 5 secondes (pour les tests)

# Option de sauvegarde dans un fichier
SAUVEGARDER_FICHIER = True
FICHIER_LOG = "donnees_meteo.log"

# Affichage détaillé ou simple
MODE_DEBUG = True

# ============================================
# CLASSE POUR GÉRER LES MESURES MÉTÉO
# ============================================

class MesureMeteo:
    """
    Classe représentant une mesure météorologique
    Correspond à la classe MesureMeteo du diagramme de classes
    """
    
    def __init__(self, temperature, humidite, pression):
        """
        Constructeur de la classe
        
        Paramètres :
        - temperature : float (en °C)
        - humidite : float (en %)
        - pression : float (en hPa)
        """
        self.temperature = temperature
        self.humidite = humidite
        self.pression = pression
        self.timestamp = datetime.now()
    
    def afficher(self):
        """
        Affiche les mesures de manière formatée
        """
        print("\n" + "="*50)
        print("📊 MESURE MÉTÉOROLOGIQUE - Rucher MELAINA")
        print("="*50)
        print(f"🕐 Date/Heure    : {self.timestamp.strftime('%d/%m/%Y %H:%M:%S')}")
        print(f"🌡️  Température  : {self.temperature:.1f}°C")
        print(f"💧 Humidité     : {self.humidite:.1f}%")
        print(f"🌫️  Pression     : {self.pression:.2f} hPa")
        print("="*50)
    
    def vers_chaine(self):
        """
        Convertit la mesure en chaîne de caractères pour sauvegarde
        Format CSV : timestamp, temperature, humidite, pression
        
        Retourne : string
        """
        return f"{self.timestamp.isoformat()},{self.temperature:.2f},{self.humidite:.2f},{self.pression:.2f}"

# ============================================
# FONCTIONS PRINCIPALES
# ============================================

def initialiser_capteur():
    """
    Initialise le capteur BME280
    
    Retourne : 
    - Objet BME280 si succès
    - None si échec
    """
    print("\n🔧 Initialisation du capteur BME280...")
    
    try:
        # Création de l'objet capteur
        # Le BME280 Grove utilise automatiquement l'adresse I2C 0x76 ou 0x77
        capteur = BME280()
        
        print("✅ Capteur BME280 initialisé avec succès !")
        return capteur
        
    except Exception as e:
        print(f"❌ ERREUR lors de l'initialisation du capteur : {e}")
        print("\n🔍 Vérifications à faire :")
        print("   1. Le capteur BME280 est bien branché sur un port I2C de la carte GrovePi+")
        print("   2. La carte GrovePi+ est bien connectée au Raspberry Pi")
        print("   3. I2C est activé sur le Raspberry Pi (sudo raspi-config)")
        print("   4. Les librairies sont installées (grove.py)")
        return None

def lire_capteur(capteur):
    """
    Lit les données du capteur BME280
    
    Paramètres :
    - capteur : objet BME280
    
    Retourne :
    - Objet MesureMeteo avec les données
    - None si erreur
    """
    try:
        # Lecture de la température (en °C)
        temperature = capteur.temperature
        
        # Lecture de l'humidité (en %)
        humidite = capteur.humidity
        
        # Lecture de la pression (en Pa, on convertit en hPa)
        pression = capteur.pressure / 100.0  # Conversion Pa → hPa
        
        # Création d'un objet MesureMeteo
        mesure = MesureMeteo(temperature, humidite, pression)
        
        return mesure
        
    except Exception as e:
        print(f"❌ ERREUR lors de la lecture du capteur : {e}")
        return None

def sauvegarder_mesure(mesure, fichier):
    """
    Sauvegarde la mesure dans un fichier log
    
    Paramètres :
    - mesure : objet MesureMeteo
    - fichier : nom du fichier de sauvegarde
    """
    try:
        # Ouverture du fichier en mode ajout (append)
        with open(fichier, 'a') as f:
            f.write(mesure.vers_chaine() + '\n')
        
        if MODE_DEBUG:
            print(f"💾 Mesure sauvegardée dans {fichier}")
            
    except Exception as e:
        print(f"⚠️  Erreur lors de la sauvegarde : {e}")

def afficher_en_tete():
    """
    Affiche l'en-tête du programme
    """
    print("\n")
    print("╔" + "═"*60 + "╗")
    print("║" + " "*60 + "║")
    print("║" + "  📡 SYSTÈME MELAINA - STATION MÉTÉO RUCHER".center(60) + "║")
    print("║" + " "*60 + "║")
    print("║" + "  🐝 Surveillance Intelligente de Ruches".center(60) + "║")
    print("║" + "  Étudiant 2 : Lucas - BTS CIEL IR 2026".center(60) + "║")
    print("║" + " "*60 + "║")
    print("╚" + "═"*60 + "╝")
    print("\n")

# ============================================
# PROGRAMME PRINCIPAL
# ============================================

def main():
    """
    Fonction principale du programme
    """
    
    # Affichage de l'en-tête
    afficher_en_tete()
    
    # Initialisation du capteur
    capteur = initialiser_capteur()
    
    if capteur is None:
        print("\n❌ Impossible de démarrer le programme sans capteur.")
        print("Arrêt du programme.")
        sys.exit(1)
    
    print(f"\n⏱️  Fréquence de lecture : {FREQUENCE_LECTURE} secondes")
    
    if SAUVEGARDER_FICHIER:
        print(f"💾 Sauvegarde activée : {FICHIER_LOG}")
        # Création de l'en-tête du fichier CSV s'il n'existe pas
        try:
            with open(FICHIER_LOG, 'a') as f:
                # Vérifier si le fichier est vide pour ajouter l'en-tête
                if f.tell() == 0:
                    f.write("timestamp,temperature_celsius,humidite_pourcent,pression_hpa\n")
        except Exception as e:
            print(f"⚠️  Erreur lors de la création du fichier : {e}")
    
    print("\n🚀 Démarrage de la surveillance...")
    print("   (Appuyez sur Ctrl+C pour arrêter)\n")
    
    # Compteur de mesures
    compteur = 0
    
    try:
        # Boucle infinie de lecture
        while True:
            compteur += 1
            
            if MODE_DEBUG:
                print(f"\n📡 Lecture #{compteur}...")
            
            # Lecture du capteur
            mesure = lire_capteur(capteur)
            
            if mesure is not None:
                # Affichage de la mesure
                mesure.afficher()
                
                # Sauvegarde si activée
                if SAUVEGARDER_FICHIER:
                    sauvegarder_mesure(mesure, FICHIER_LOG)
                
                # Vérification de conditions particulières (bonus)
                # Alerte si température trop élevée
                if mesure.temperature > 35:
                    print("\n⚠️  ATTENTION : Température extérieure élevée (> 35°C)")
                    print("   → Risque pour les abeilles !")
                
                # Alerte si humidité trop basse
                if mesure.humidite < 30:
                    print("\n⚠️  ATTENTION : Humidité très basse (< 30%)")
                    print("   → Conditions sèches, surveiller les abeilles")
                
                # Alerte si humidité trop haute
                if mesure.humidite > 85:
                    print("\n⚠️  ATTENTION : Humidité très élevée (> 85%)")
                    print("   → Risque de moisissures dans la ruche")
            
            else:
                print("⚠️  Échec de la lecture, nouvelle tentative...")
            
            # Attente avant la prochaine lecture
            time.sleep(FREQUENCE_LECTURE)
    
    except KeyboardInterrupt:
        # Arrêt propre du programme (Ctrl+C)
        print("\n\n🛑 Arrêt du programme demandé par l'utilisateur")
        print(f"📊 Total de mesures effectuées : {compteur}")
        
        if SAUVEGARDER_FICHIER:
            print(f"💾 Données sauvegardées dans : {FICHIER_LOG}")
        
        print("\n👋 Au revoir !\n")
        sys.exit(0)
    
    except Exception as e:
        print(f"\n❌ ERREUR FATALE : {e}")
        sys.exit(1)

# ============================================
# POINT D'ENTRÉE DU PROGRAMME
# ============================================

if __name__ == "__main__":
    """
    Point d'entrée : exécuté uniquement si le script est lancé directement
    (pas importé comme module)
    """
    main()
