"""
Biblioteca de minería de datos de la tienda (KDD / CRISP-DM).

Contiene la preparación de datos (limpieza, integración, transformación) y las
funciones que construyen las matrices y vistas minables usadas por los modelos,
las exportaciones (Weka, RapidMiner, Power BI) y el dashboard.
"""
from __future__ import annotations

from pathlib import Path

import numpy as np
import pandas as pd

BASE = Path(__file__).resolve().parents[1]
DATOS = BASE / "datos"
MODELOS = BASE / "modelos"
EXPORTES = BASE / "exportes"

DIAS_RECOMPRA = 7  # horizonte de la pregunta "¿volverá a comprar en los próximos N días?"
DIAS_SEMANA = ["lunes", "martes", "miércoles", "jueves", "viernes", "sábado", "domingo"]


# --------------------------------------------------------------------------
# 1. Selección + limpieza + integración
# --------------------------------------------------------------------------
def cargar_datos() -> dict[str, pd.DataFrame]:
    """Lee los CSV extraídos del volcado SQL y los integra."""
    pedidos = pd.read_csv(DATOS / "pedidos.csv", parse_dates=["fecha_pedido"])
    detalle = pd.read_csv(DATOS / "pedido_detalle.csv")
    productos = pd.read_csv(DATOS / "productos.csv")
    categorias = pd.read_csv(DATOS / "categorias.csv")
    usuarios = pd.read_csv(DATOS / "usuarios.csv", parse_dates=["fecha_registro"])

    for df in (pedidos, detalle, productos, categorias, usuarios):
        for col in df.select_dtypes("object"):
            df[col] = df[col].astype(str).str.strip()

    # Normalización de ciudades (valores escritos a mano por los usuarios)
    def ciudad_norm(c: str) -> str:
        c = str(c).strip().lower()
        if "soacha" in c:
            return "Soacha"
        if "bolivar" in c or "bolívar" in c:
            return "Bogotá"  # Ciudad Bolívar es una localidad de Bogotá
        return c.title() if c not in ("nan", "") else "Desconocida"

    pedidos["ciudad"] = pedidos["ciudad_entrega"].map(ciudad_norm)
    usuarios["ciudad"] = usuarios["ciudad"].map(ciudad_norm)

    productos = productos.merge(
        categorias[["id", "nombre"]].rename(columns={"id": "categoria_id", "nombre": "categoria"}),
        on="categoria_id",
        how="left",
    )
    detalle = detalle.merge(
        productos[["id", "categoria", "precio"]].rename(columns={"id": "producto_id", "precio": "precio_lista"}),
        on="producto_id",
        how="left",
    )
    detalle = detalle.merge(
        pedidos[["id", "usuario_id", "fecha_pedido", "estado", "ciudad"]].rename(columns={"id": "pedido_id"}),
        on="pedido_id",
        how="left",
    )
    detalle["costo_unitario"] = detalle["costo_unitario"].fillna(detalle["precio_unitario"] * 0.55)
    detalle["margen"] = (detalle["precio_unitario"] - detalle["costo_unitario"]) * detalle["cantidad"]

    clientes = usuarios[usuarios["rol"] == "cliente"].copy()
    return {
        "pedidos": pedidos,
        "detalle": detalle,
        "productos": productos,
        "categorias": categorias,
        "usuarios": usuarios,
        "clientes": clientes,
        "fecha_corte": pedidos["fecha_pedido"].max(),
    }


def lista_categorias(datos) -> list[str]:
    return sorted(datos["categorias"]["nombre"].tolist())


# --------------------------------------------------------------------------
# 2. Transformación: vista de transacciones (un registro por pedido)
# --------------------------------------------------------------------------
def tabla_transacciones(datos) -> pd.DataFrame:
    det, ped = datos["detalle"], datos["pedidos"]
    cats = lista_categorias(datos)

    # matriz pedido x categoría (unidades) -> también es la "cesta" para Apriori
    unidades = det.pivot_table(index="pedido_id", columns="categoria", values="cantidad", aggfunc="sum", fill_value=0)
    unidades = unidades.reindex(columns=cats, fill_value=0)
    gasto_cat = det.pivot_table(index="pedido_id", columns="categoria", values="subtotal", aggfunc="sum", fill_value=0)
    gasto_cat = gasto_cat.reindex(columns=cats, fill_value=0)
    dominante = gasto_cat.idxmax(axis=1).rename("categoria_principal")

    agg = det.groupby("pedido_id").agg(
        items=("cantidad", "sum"),
        lineas=("id", "count"),
        productos_distintos=("producto_id", "nunique"),
        margen=("margen", "sum"),
    )
    t = ped.rename(columns={"id": "pedido_id"}).set_index("pedido_id")
    t = t.join(agg).join(dominante).join(unidades.add_prefix("u_"))
    t = t.reset_index().sort_values(["usuario_id", "fecha_pedido"]).reset_index(drop=True)
    t["n_orden"] = t.groupby("usuario_id").cumcount() + 1
    t["dias_desde_anterior"] = t.groupby("usuario_id")["fecha_pedido"].diff().dt.total_seconds() / 86400
    t["cancelado"] = (t["estado"] == "cancelado").astype(int)
    t["envio_gratis"] = (t["costo_envio"] == 0).astype(int)
    t["dia_semana"] = t["fecha_pedido"].dt.dayofweek
    t["hora"] = t["fecha_pedido"].dt.hour
    return t


# --------------------------------------------------------------------------
# 3. Vista minable para predecir la decisión FUTURA del usuario
#    Cada fila = "foto" del cliente justo después de un pedido k.
#    Atributos: sólo información disponible hasta el pedido k (sin fuga de datos).
#    Clases:    categoría principal del pedido k+1  y  ¿recompra en <= N días?
# --------------------------------------------------------------------------
ATRIBUTOS_NUM = [
    "n_pedidos",
    "gasto_total",
    "ticket_promedio",
    "ticket_ultimo",
    "items_ultimo",
    "intervalo_promedio",
    "dias_desde_anterior",
    "tasa_cancelacion",
    "tasa_envio_gratis",
    "variedad_categorias",
    "dia_semana",
    "hora",
]


def _foto_cliente(hist: pd.DataFrame, cats: list[str]) -> dict:
    """Atributos de un cliente a partir de su historial (ordenado por fecha)."""
    ult = hist.iloc[-1]
    unidades = hist[[f"u_{c}" for c in cats]].sum()
    total_u = max(unidades.sum(), 1)
    gaps = hist["dias_desde_anterior"].dropna()
    fila = {
        "n_pedidos": len(hist),
        "gasto_total": float(hist["total"].sum()),
        "ticket_promedio": float(hist["total"].mean()),
        "ticket_ultimo": float(ult["total"]),
        "items_ultimo": int(ult["items"]),
        "intervalo_promedio": float(gaps.mean()) if len(gaps) else 30.0,
        "dias_desde_anterior": float(ult["dias_desde_anterior"]) if pd.notna(ult["dias_desde_anterior"]) else 30.0,
        "tasa_cancelacion": float(hist["cancelado"].mean()),
        "tasa_envio_gratis": float(hist["envio_gratis"].mean()),
        "variedad_categorias": int((unidades > 0).sum()),
        "dia_semana": int(ult["dia_semana"]),
        "hora": int(ult["hora"]),
        "ultima_categoria": ult["categoria_principal"],
        "ciudad": ult["ciudad"],
    }
    for c in cats:  # fila de la matriz cliente x categoría (proporciones)
        fila[f"pct_{c}"] = float(unidades[f"u_{c}"] / total_u)
    return fila


def vista_minable(datos, trans: pd.DataFrame | None = None) -> pd.DataFrame:
    trans = tabla_transacciones(datos) if trans is None else trans
    cats = lista_categorias(datos)
    corte = datos["fecha_corte"]
    filas = []
    for uid, hist in trans.groupby("usuario_id"):
        hist = hist.reset_index(drop=True)
        for k in range(len(hist)):
            f = _foto_cliente(hist.iloc[: k + 1], cats)
            f["usuario_id"] = uid
            f["pedido_id"] = hist.loc[k, "pedido_id"]
            f["fecha"] = hist.loc[k, "fecha_pedido"]
            if k + 1 < len(hist):
                sig = hist.loc[k + 1]
                gap = (sig["fecha_pedido"] - hist.loc[k, "fecha_pedido"]).total_seconds() / 86400
                f["siguiente_categoria"] = sig["categoria_principal"]
                f["dias_hasta_siguiente"] = gap
                f["recompra"] = "si" if gap <= DIAS_RECOMPRA else "no"
                f["ticket_siguiente"] = float(sig["total"])
            else:
                f["siguiente_categoria"] = None
                f["dias_hasta_siguiente"] = None
                f["ticket_siguiente"] = None
                # Sin pedido posterior: sólo sabemos que NO recompró si ya pasó la ventana
                dias_obs = (corte - hist.loc[k, "fecha_pedido"]).total_seconds() / 86400
                f["recompra"] = "no" if dias_obs >= DIAS_RECOMPRA else None
            f["es_ultimo"] = k == len(hist) - 1
            filas.append(f)
    return pd.DataFrame(filas)


def columnas_atributos(cats: list[str]) -> tuple[list[str], list[str]]:
    num = ATRIBUTOS_NUM + [f"pct_{c}" for c in cats]
    cat = ["ultima_categoria", "ciudad"]
    return num, cat


# --------------------------------------------------------------------------
# 4. Matrices de apoyo
# --------------------------------------------------------------------------
def matriz_cliente_producto(datos) -> pd.DataFrame:
    det = datos["detalle"]
    m = det.pivot_table(index="usuario_id", columns="producto_id", values="cantidad", aggfunc="sum", fill_value=0)
    return m


def matriz_transicion(trans: pd.DataFrame, cats: list[str]) -> pd.DataFrame:
    """Cadena de Markov: P(categoría del siguiente pedido | categoría del pedido actual)."""
    t = trans.sort_values(["usuario_id", "fecha_pedido"]).copy()
    t["siguiente"] = t.groupby("usuario_id")["categoria_principal"].shift(-1)
    conteo = pd.crosstab(t["categoria_principal"], t["siguiente"]).reindex(index=cats, columns=cats, fill_value=0)
    suavizado = conteo + 1  # suavizado de Laplace
    return suavizado.div(suavizado.sum(axis=1), axis=0)


def tabla_rfm(datos, trans: pd.DataFrame) -> pd.DataFrame:
    """Recencia, Frecuencia, Valor monetario + mezcla de categorías por cliente."""
    corte = datos["fecha_corte"]
    cats = lista_categorias(datos)
    validos = trans[trans["cancelado"] == 0]
    g = validos.groupby("usuario_id")
    rfm = pd.DataFrame(
        {
            "recencia_dias": (corte - g["fecha_pedido"].max()).dt.total_seconds() / 86400,
            "frecuencia": g.size(),
            "monetario": g["total"].sum(),
            "ticket_promedio": g["total"].mean(),
            "items_promedio": g["items"].mean(),
            "margen_total": g["margen"].sum(),
        }
    )
    u = validos.groupby("usuario_id")[[f"u_{c}" for c in cats]].sum()
    u = u.div(u.sum(axis=1).replace(0, 1), axis=0)
    u.columns = [f"pct_{c}" for c in cats]
    rfm = rfm.join(u)
    ciudades = datos["clientes"].set_index("id")["ciudad"]
    rfm["ciudad"] = ciudades.reindex(rfm.index).fillna("Desconocida")
    return rfm


def cestas(trans: pd.DataFrame, cats: list[str]) -> pd.DataFrame:
    """Matriz booleana pedido x categoría para reglas de asociación."""
    return trans.set_index("pedido_id")[[f"u_{c}" for c in cats]].gt(0).rename(columns=lambda c: c[2:])


def cestas_producto(datos) -> pd.DataFrame:
    det = datos["detalle"]
    return det.pivot_table(index="pedido_id", columns="nombre_producto", values="cantidad", aggfunc="sum", fill_value=0).gt(0)


# --------------------------------------------------------------------------
# 5. Modelo híbrido para la próxima categoría
#    Promedia tres fuentes de evidencia: el clasificador de ML, la cadena de
#    Markov (última categoría -> siguiente) y la preferencia histórica del cliente.
# --------------------------------------------------------------------------
PESOS_HIBRIDO = {"ml": 1 / 3, "markov": 1 / 3, "preferencia": 1 / 3}


def markov_desde_vista(vista: pd.DataFrame, cats: list[str]) -> pd.DataFrame:
    d = vista.dropna(subset=["siguiente_categoria"])
    conteo = pd.crosstab(d["ultima_categoria"], d["siguiente_categoria"]).reindex(index=cats, columns=cats, fill_value=0)
    conteo = conteo + 1
    return conteo.div(conteo.sum(axis=1), axis=0)


def proba_preferencia(filas: pd.DataFrame, cats: list[str]) -> np.ndarray:
    m = filas[[f"pct_{c}" for c in cats]].to_numpy() * filas[["n_pedidos"]].to_numpy() + 0.5
    return m / m.sum(axis=1, keepdims=True)


def proba_hibrida(modelo, filas: pd.DataFrame, P: pd.DataFrame, cats: list[str], pesos=PESOS_HIBRIDO):
    clases = list(modelo.classes_)
    ml = pd.DataFrame(modelo.predict_proba(filas), columns=clases).reindex(columns=cats, fill_value=0).to_numpy()
    mk = P.reindex(index=filas["ultima_categoria"], columns=cats).fillna(1 / len(cats)).to_numpy()
    pr = proba_preferencia(filas, cats)
    total = pesos["ml"] * ml + pesos["markov"] * mk + pesos["preferencia"] * pr
    return {"ML": ml, "Markov": mk, "Preferencia": pr, "Híbrido": total / total.sum(axis=1, keepdims=True)}
