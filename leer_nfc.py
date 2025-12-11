import requests
import webbrowser
from smartcard.System import readers

WEB_URL = "https://proyecto.intranet.local/request_token.php"
WEB_HOME = "https://proyecto.intranet.local/index.php"

print("📡 Acerca la tarjeta al lector...")

r = readers()
if not r:
    print("❌ No se detecta lector NFC")
    input("ENTER para cerrar...")
    exit()

lector = r[0]

try:
    conn = lector.createConnection()
    conn.connect()

    GET_UID = [0xFF, 0xCA, 0x00, 0x00, 0x00]
    data, sw1, sw2 = conn.transmit(GET_UID)

    uid = ''.join(f"{b:02X}" for b in data)
    print("🔑 UID:", uid)

    # Ignorar certificado: verify=False
    res = requests.post(WEB_URL, json={"uid": uid.lower()}, verify=False)
    print("🌍 Respuesta:", res.text)

    if '"ok":true' in res.text:
        print("✔️ Login correcto, abriendo web...")
        webbrowser.open(WEB_HOME)
    else:
        print("❌ Tarjeta no registrada o error")

except Exception as e:
    print("💥 ERROR:", e)

input("\nENTER para cerrar...")
