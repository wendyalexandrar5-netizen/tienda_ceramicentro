"""
Paso 1 (KDD: Selección) — Extrae las tablas del volcado MySQL/phpMyAdmin a CSV.

Uso:
    python scripts/01_extraer_sql.py ruta/al/tienda.sql

Genera datos/*.csv (anonimizados: sin nombres, correos, teléfonos, direcciones ni contraseñas).
No requiere tener MySQL instalado: lee directamente las sentencias CREATE TABLE / INSERT INTO.
"""
import re
import sys
from pathlib import Path

import pandas as pd

BASE = Path(__file__).resolve().parents[1]
DATOS = BASE / "datos"

# Tablas útiles para el análisis y columnas sensibles que NO se exportan
TABLAS = {
    "usuarios": ["nombre", "apellido", "email", "password_hash", "telefono", "direccion"],
    "pedidos": ["nombre_destinatario", "telefono_entrega", "direccion_entrega", "notas", "codigo"],
    "pedido_detalle": [],
    "pedido_historial": ["comentario"],
    "productos": ["descripcion", "imagen"],
    "categorias": ["imagen", "descripcion"],
    "variantes_producto": [],
    "tallas": [],
    "colores": [],
    "carritos": [],
    "carrito_detalle": [],
    "movimientos_inventario": ["motivo"],
    "fichas_tecnicas": ["notas"],
}


def parse_values(texto: str):
    """Convierte '(1,'a',NULL),(2,...)' en lista de filas respetando comillas y escapes."""
    filas, fila, i, n = [], [], 0, len(texto)
    token, en_cadena, era_cadena = [], False, False
    while i < n:
        c = texto[i]
        if en_cadena:
            if c == "\\" and i + 1 < n:
                sig = texto[i + 1]
                token.append({"n": "\n", "r": "\r", "t": "\t", "0": "\0"}.get(sig, sig))
                i += 2
                continue
            if c == "'":
                if i + 1 < n and texto[i + 1] == "'":
                    token.append("'")
                    i += 2
                    continue
                en_cadena = False
            else:
                token.append(c)
        else:
            if c == "'":
                en_cadena, era_cadena, token = True, True, []
            elif c == "(" and not fila and not token:
                pass
            elif c in ",)":
                valor = "".join(token).strip() if not era_cadena else "".join(token)
                if not era_cadena and valor.upper() == "NULL":
                    valor = None
                if c == "," or valor not in ("",) or era_cadena:
                    fila.append(valor)
                token, era_cadena = [], False
                if c == ")":
                    filas.append(fila)
                    fila = []
                    # saltar la coma entre filas
                    while i + 1 < n and texto[i + 1] in ", \n\r\t":
                        i += 1
            elif c == ";":
                break
            else:
                token.append(c)
        i += 1
    return filas


def extraer(sql: str):
    tablas = {}
    patron = re.compile(r"INSERT INTO `(\w+)` \(([^)]*)\) VALUES\s*(.*?);\s*$", re.S | re.M)
    for m in patron.finditer(sql):
        nombre = m.group(1)
        columnas = [c.strip(" `") for c in m.group(2).split(",")]
        filas = parse_values(m.group(3) + ";")
        df = pd.DataFrame(filas, columns=columnas)
        tablas[nombre] = pd.concat([tablas[nombre], df], ignore_index=True) if nombre in tablas else df
    return tablas


def main():
    if len(sys.argv) < 2:
        sys.exit("Uso: python scripts/01_extraer_sql.py ruta/al/tienda.sql")
    sql = Path(sys.argv[1]).read_text(encoding="utf-8", errors="replace")
    tablas = extraer(sql)
    DATOS.mkdir(exist_ok=True)
    for nombre, sensibles in TABLAS.items():
        if nombre not in tablas:
            print(f"  (sin datos) {nombre}")
            continue
        df = tablas[nombre].drop(columns=[c for c in sensibles if c in tablas[nombre].columns])
        for col in df.columns:
            try:
                df[col] = pd.to_numeric(df[col])
            except (ValueError, TypeError):
                pass
        df.to_csv(DATOS / f"{nombre}.csv", index=False)
        print(f"  {nombre:24s} {len(df):5d} filas")


if __name__ == "__main__":
    main()
