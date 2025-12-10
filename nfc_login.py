import requests
import webbrowser
from smartcard.System import readers

WEB_URL = "https://proyecto.intranet.local/request_token.php"  # ← CAMBIA si hace falta
WEB_HOME = "https://proyecto.intranet.local/"  # Página a abrir ya logueado

r = readers()
if not r:
    print("❌ No se detecta lector NFC")
    input("ENTER para cerrar...")
    exit()

lector = r[0]
print("📡 Acerca la tarjeta al lector...")

try:
    conn = lector.createConnection()
    conn.connect()

    GET_UID = [0xFF, 0xCA, 0x00, 0x00, 0x00]
    data, sw1, sw2 = conn.transmit(GET_UID)

    uid = ''.join(f"{b:02X}" for b in data)
    print("🔑 UID:", uid)

    res = requests.post(WEB_URL, json={"uid": uid.lower()}, verify=False)
    print("🌍 Respuesta:", res.text)

    if '"ok":true' in res.text:
        print("✔️ Login correcto, abriendo web...")
        webbrowser.open(WEB_HOME)
    else:
        print("❌ Tarjeta no válida")

except Exception as e:
    print("💥 ERROR:", e)

input("\nENTER para cerrar...")

