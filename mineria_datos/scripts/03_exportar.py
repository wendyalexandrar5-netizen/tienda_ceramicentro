"""
Paso 3 (KDD: Interpretación / despliegue) — Exporta los datos para otras herramientas.

  exportes/weka/        archivos .arff listos para abrir en Weka Explorer
  exportes/rapidminer/  CSV (separador ;) para RapidMiner Studio (Read CSV)
  exportes/powerbi/     modelo estrella (hechos + dimensiones) + predicciones por cliente

Uso:  python scripts/03_exportar.py   (después de 02_entrenar_modelos.py)
"""
from __future__ import annotations

import json
import re

import joblib
import numpy as np
import pandas as pd

import mineria as M


# ------------------------------------------------------------------ Weka (ARFF)
def _nombre_arff(s: str) -> str:
    return re.sub(r"[^A-Za-z0-9_]", "_", s.replace("á", "a").replace("é", "e").replace("í", "i").replace("ó", "o").replace("ú", "u").replace("ñ", "n"))


def _valor_arff(v):
    if v is None or (isinstance(v, float) and np.isnan(v)):
        return "?"
    if isinstance(v, (int, np.integer)):
        return str(v)
    if isinstance(v, (float, np.floating)):
        return f"{v:.6g}"
    return "'" + str(v).replace("'", "\\'") + "'"


def escribir_arff(df: pd.DataFrame, relacion: str, ruta, nominales: dict[str, list] | None = None, comentario: str = ""):
    nominales = nominales or {}
    lineas = [f"% {l}" for l in comentario.strip().splitlines()] + [f"@RELATION {relacion}", ""]
    for col in df.columns:
        nombre = _nombre_arff(col)
        if col in nominales:
            valores = ",".join(_valor_arff(v) for v in nominales[col])
            lineas.append(f"@ATTRIBUTE {nombre} {{{valores}}}")
        elif pd.api.types.is_numeric_dtype(df[col]):
            lineas.append(f"@ATTRIBUTE {nombre} NUMERIC")
        else:
            valores = ",".join(_valor_arff(v) for v in sorted(df[col].dropna().unique()))
            lineas.append(f"@ATTRIBUTE {nombre} {{{valores}}}")
    lineas += ["", "@DATA"]
    for fila in df.itertuples(index=False):
        lineas.append(",".join(_valor_arff(v) for v in fila))
    ruta.write_text("\n".join(lineas) + "\n", encoding="utf-8")


def main():
    datos = M.cargar_datos()
    cats = M.lista_categorias(datos)
    trans = M.tabla_transacciones(datos)
    vista = M.vista_minable(datos, trans)
    num, cat = M.columnas_atributos(cats)
    for sub in ("weka", "rapidminer", "powerbi"):
        (M.EXPORTES / sub).mkdir(parents=True, exist_ok=True)

    # ---------- Weka ----------
    ciudades = sorted(vista["ciudad"].unique())
    nom = {"ultima_categoria": cats, "ciudad": ciudades}
    d1 = vista.dropna(subset=["siguiente_categoria"])[num + cat + ["siguiente_categoria"]]
    escribir_arff(d1, "siguiente_categoria", M.EXPORTES / "weka" / "siguiente_categoria.arff", {**nom, "siguiente_categoria": cats},
                  "Clase: categoría principal del PRÓXIMO pedido del cliente.\nSugerido: Classify > trees.J48, bayes.NaiveBayes, lazy.IBk, functions.Logistic, trees.RandomForest con 10-fold CV.")
    d2 = vista.dropna(subset=["recompra"])[num + cat + ["recompra"]]
    escribir_arff(d2, "recompra_7_dias", M.EXPORTES / "weka" / "recompra_7_dias.arff", {**nom, "recompra": ["si", "no"]},
                  f"Clase: ¿el cliente vuelve a comprar en los próximos {M.DIAS_RECOMPRA} días?\nSugerido: J48, NaiveBayes, Logistic; revisar área ROC y matriz de confusión.")
    rfm = M.tabla_rfm(datos, trans)
    escribir_arff(rfm.drop(columns=["ciudad"]).reset_index(drop=True), "clientes_rfm", M.EXPORTES / "weka" / "clientes_rfm.arff",
                  comentario="Agrupamiento: Cluster > SimpleKMeans (normalizar) o EM.")
    canasta = M.cestas(trans, cats)
    cesta_arff = canasta.apply(lambda col: col.map({True: "t", False: None}))
    escribir_arff(cesta_arff.reset_index(drop=True), "cestas_categorias", M.EXPORTES / "weka" / "cestas_categorias.arff",
                  {c: ["t"] for c in cesta_arff.columns}, "Reglas de asociación: Associate > Apriori (minSupport 0.03, minMetric 0.2). '?' = no comprado.")

    # ---------- RapidMiner ----------
    vista.drop(columns=["es_ultimo"]).to_csv(M.EXPORTES / "rapidminer" / "vista_minable.csv", sep=";", index=False, decimal=",")
    rfm.to_csv(M.EXPORTES / "rapidminer" / "clientes_rfm.csv", sep=";", decimal=",")
    canasta.astype(int).to_csv(M.EXPORTES / "rapidminer" / "cestas_categorias.csv", sep=";")

    # ---------- Power BI (modelo estrella) ----------
    pbi = M.EXPORTES / "powerbi"
    det = datos["detalle"]
    hechos = det[["id", "pedido_id", "usuario_id", "producto_id", "fecha_pedido", "estado", "ciudad", "talla", "color",
                  "cantidad", "precio_unitario", "costo_unitario", "subtotal", "margen"]].rename(columns={"id": "linea_id"})
    hechos["fecha"] = hechos["fecha_pedido"].dt.date
    hechos.drop(columns=["fecha_pedido"]).to_csv(pbi / "hechos_ventas.csv", index=False)
    trans.drop(columns=[c for c in trans.columns if c.startswith("u_")] + ["ciudad_entrega"]).to_csv(pbi / "hechos_pedidos.csv", index=False)
    datos["productos"][["id", "nombre", "categoria", "precio", "destacado", "estado"]].to_csv(pbi / "dim_producto.csv", index=False)
    fechas = pd.date_range(trans["fecha_pedido"].min().normalize(), datos["fecha_corte"].normalize(), freq="D")
    pd.DataFrame({"fecha": fechas.date, "anio": fechas.year, "mes": fechas.month, "nombre_mes": fechas.month_name(),
                  "semana": fechas.isocalendar().week.values, "dia_semana": [M.DIAS_SEMANA[d] for d in fechas.dayofweek]}).to_csv(pbi / "dim_fecha.csv", index=False)

    # Predicciones actuales por cliente (la "decisión futura")
    seg = pd.read_csv(M.MODELOS / "segmentos_clientes.csv", index_col=0)
    m_cat = joblib.load(M.MODELOS / "modelo_categoria.joblib")
    m_rec = joblib.load(M.MODELOS / "modelo_recompra.joblib")
    m_tic = joblib.load(M.MODELOS / "modelo_ticket.joblib")
    P = pd.read_csv(M.MODELOS / "matriz_transicion.csv", index_col=0)
    actual = vista[vista["es_ultimo"]].reset_index(drop=True)
    probs = M.proba_hibrida(m_cat, actual, P, cats)["Híbrido"]
    pred = pd.DataFrame({"usuario_id": actual["usuario_id"]})
    pred["proxima_categoria"] = np.array(cats)[probs.argmax(axis=1)]
    pred["prob_proxima_categoria"] = probs.max(axis=1).round(3)
    clases = list(m_rec.classes_)
    pred["prob_recompra_7d"] = m_rec.predict_proba(actual)[:, clases.index("si")].round(3)
    pred["gasto_esperado"] = m_tic.predict(actual).round(0)
    pred["dias_sin_comprar"] = ((datos["fecha_corte"] - actual["fecha"]).dt.total_seconds() / 86400).round(1)
    for i, c in enumerate(cats):
        pred[f"prob_{c}"] = probs[:, i].round(3)
    dim_cliente = seg.reset_index().rename(columns={"index": "usuario_id"}).merge(pred, on="usuario_id", how="outer")
    dim_cliente.to_csv(pbi / "dim_cliente_predicciones.csv", index=False)
    pd.read_csv(M.MODELOS / "reglas_asociacion.csv").to_csv(pbi / "reglas_asociacion.csv", index=False)
    res = json.loads((M.MODELOS / "resultados.json").read_text(encoding="utf-8"))
    pd.concat([pd.DataFrame(res["categoria"]["tabla"]).assign(tarea="siguiente_categoria"),
               pd.DataFrame(res["recompra"]["tabla"]).assign(tarea="recompra_7d")]).to_csv(pbi / "evaluacion_modelos.csv", index=False)

    print("Exportes generados en", M.EXPORTES)
    for p in sorted(M.EXPORTES.rglob("*.*")):
        if p.suffix in (".arff", ".csv"):
            print("  ", p.relative_to(M.BASE))


if __name__ == "__main__":
    main()
