"""
Paso 2 (KDD: Minería de datos + Evaluación).

Entrena y compara técnicas de minería sobre la vista minable:
  * Clasificación — ¿qué categoría comprará el cliente en su próximo pedido?
  * Clasificación — ¿volverá a comprar en los próximos N días? (recompra)
  * Regresión     — ¿cuánto gastará en su próximo pedido?
  * Agrupamiento  — segmentación K-Means sobre RFM + mezcla de categorías
  * Asociación    — reglas Apriori (canasta de mercado)
  * Cadena de Markov — matriz de transición entre categorías
  * Análisis factorial (PCA) y matriz de correlación

Uso:  python scripts/02_entrenar_modelos.py
Salida: modelos/*.joblib, modelos/resultados.json y tablas en modelos/*.csv
"""
from __future__ import annotations

import json
import warnings

import joblib
import numpy as np
import pandas as pd
from mlxtend.frequent_patterns import apriori, association_rules
from sklearn.cluster import KMeans
from sklearn.compose import ColumnTransformer
from sklearn.decomposition import PCA
from sklearn.dummy import DummyClassifier, DummyRegressor
from sklearn.ensemble import GradientBoostingClassifier, RandomForestClassifier, RandomForestRegressor
from sklearn.linear_model import LinearRegression, LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    cohen_kappa_score,
    confusion_matrix,
    f1_score,
    mean_absolute_error,
    r2_score,
    roc_auc_score,
    silhouette_score,
)
from sklearn.model_selection import StratifiedKFold, KFold, cross_val_predict
from sklearn.naive_bayes import GaussianNB
from sklearn.neighbors import KNeighborsClassifier
from sklearn.pipeline import Pipeline
from sklearn.preprocessing import OneHotEncoder, StandardScaler
from sklearn.tree import DecisionTreeClassifier, export_text

import mineria as M

warnings.filterwarnings("ignore")
SEMILLA = 42


def preprocesador(num, cat):
    return ColumnTransformer(
        [
            ("num", StandardScaler(), num),
            ("cat", OneHotEncoder(handle_unknown="ignore", sparse_output=False), cat),
        ]
    )


def clasificadores():
    """Equivalentes de los algoritmos clásicos de Weka / RapidMiner."""
    return {
        "ZeroR (línea base)": DummyClassifier(strategy="most_frequent"),
        "Naive Bayes": GaussianNB(),
        "Árbol de decisión (J48/C4.5)": DecisionTreeClassifier(criterion="entropy", max_depth=4, min_samples_leaf=5, random_state=SEMILLA),
        "k-NN (IBk, k=7)": KNeighborsClassifier(n_neighbors=7),
        "Regresión logística": LogisticRegression(max_iter=2000, C=0.5),
        "Random Forest": RandomForestClassifier(n_estimators=300, max_depth=6, min_samples_leaf=3, random_state=SEMILLA),
        "Gradient Boosting": GradientBoostingClassifier(n_estimators=150, max_depth=2, learning_rate=0.05, random_state=SEMILLA),
    }


def evaluar_clasificacion(X, y, num, cat, nombre_tarea, binaria=False, extra_baseline=None):
    """Validación cruzada estratificada de 10 pliegues (igual que Weka) + holdout temporal."""
    pliegues = StratifiedKFold(n_splits=10, shuffle=True, random_state=SEMILLA)
    clases = sorted(y.unique())
    resultados, matrices = [], {}
    corte = int(len(X) * 0.8)  # holdout temporal: entrena con el 80% más antiguo
    orden = X["fecha"].argsort().values
    tr, te = orden[:corte], orden[corte:]

    for nombre, modelo in clasificadores().items():
        pipe = Pipeline([("prep", preprocesador(num, cat)), ("modelo", modelo)])
        pred = cross_val_predict(pipe, X, y, cv=pliegues)
        fila = {
            "modelo": nombre,
            "exactitud": accuracy_score(y, pred),
            "f1_macro": f1_score(y, pred, average="macro"),
            "kappa": cohen_kappa_score(y, pred),
        }
        if binaria:
            proba = cross_val_predict(pipe, X, y, cv=pliegues, method="predict_proba")[:, clases.index("si")]
            fila["auc_roc"] = roc_auc_score((y == "si").astype(int), proba)
        pipe.fit(X.iloc[tr], y.iloc[tr])
        fila["exactitud_temporal"] = accuracy_score(y.iloc[te], pipe.predict(X.iloc[te]))
        resultados.append(fila)
        matrices[nombre] = confusion_matrix(y, pred, labels=clases).tolist()

    if extra_baseline is not None:
        nombre, pred, pred_te = extra_baseline
        resultados.append(
            {
                "modelo": nombre,
                "exactitud": accuracy_score(y, pred),
                "f1_macro": f1_score(y, pred, average="macro"),
                "kappa": cohen_kappa_score(y, pred),
                "exactitud_temporal": accuracy_score(y.iloc[te], pred_te),
            }
        )
        matrices[nombre] = confusion_matrix(y, pred, labels=clases).tolist()

    tabla = pd.DataFrame(resultados).sort_values("f1_macro", ascending=False)
    print(f"\n=== {nombre_tarea} ({len(X)} instancias) ===")
    print(tabla.round(3).to_string(index=False))
    return tabla, matrices, clases


def entrenar_final(X, y, num, cat, nombre_modelo):
    pipe = Pipeline([("prep", preprocesador(num, cat)), ("modelo", clasificadores()[nombre_modelo])])
    pipe.fit(X, y)
    return pipe


def importancia(X, y, num, cat):
    rf = Pipeline([("prep", preprocesador(num, cat)), ("modelo", RandomForestClassifier(n_estimators=500, random_state=SEMILLA))])
    rf.fit(X, y)
    nombres = rf.named_steps["prep"].get_feature_names_out()
    imp = pd.Series(rf.named_steps["modelo"].feature_importances_, index=[n.split("__", 1)[1] for n in nombres])
    # agrupa las columnas one-hot por atributo original
    agrupado = {}
    for k, v in imp.items():
        base = next((c for c in cat if k.startswith(c + "_")), k)
        agrupado[base] = agrupado.get(base, 0) + v
    return pd.Series(agrupado).sort_values(ascending=False)


def main():
    M.MODELOS.mkdir(exist_ok=True)
    datos = M.cargar_datos()
    cats = M.lista_categorias(datos)
    trans = M.tabla_transacciones(datos)
    vista = M.vista_minable(datos, trans)
    num, cat = M.columnas_atributos(cats)
    vista.to_csv(M.MODELOS / "vista_minable.csv", index=False)
    resultados = {"categorias": cats, "dias_recompra": M.DIAS_RECOMPRA, "fecha_corte": str(datos["fecha_corte"])}

    # ---------------- Tarea 1: siguiente categoría ----------------
    d1 = vista.dropna(subset=["siguiente_categoria"]).reset_index(drop=True)
    X1, y1 = d1[num + cat + ["fecha"]], d1["siguiente_categoria"]
    P = M.matriz_transicion(trans, cats)
    tabla1, mat1, clases1 = evaluar_clasificacion(X1, y1, num, cat, "Siguiente categoría")
    mejor1 = tabla1[tabla1["modelo"] != "ZeroR (línea base)"].iloc[0]["modelo"]

    # Métodos sin entrenamiento clásico + modelo híbrido, evaluados con los MISMOS pliegues
    pliegues = StratifiedKFold(n_splits=10, shuffle=True, random_state=SEMILLA)
    preds = {"Cadena de Markov": pd.Series(index=d1.index, dtype=object),
             "Preferencia personal (moda)": pd.Series(index=d1.index, dtype=object),
             "Híbrido (ML + Markov + preferencia)": pd.Series(index=d1.index, dtype=object)}
    for tr, te in pliegues.split(X1, y1):
        m = entrenar_final(X1.iloc[tr], y1.iloc[tr], num, cat, mejor1)
        Pf = M.markov_desde_vista(d1.iloc[tr], cats)
        pr = M.proba_hibrida(m, d1.iloc[te], Pf, cats)
        for clave, nombre in (("Markov", "Cadena de Markov"), ("Preferencia", "Preferencia personal (moda)"), ("Híbrido", "Híbrido (ML + Markov + preferencia)")):
            preds[nombre].iloc[te] = np.array(cats)[pr[clave].argmax(axis=1)]
    orden = X1["fecha"].argsort().values
    tr_t, te_t = orden[: int(len(X1) * 0.8)], orden[int(len(X1) * 0.8):]
    m_t = entrenar_final(X1.iloc[tr_t], y1.iloc[tr_t], num, cat, mejor1)
    pr_t = M.proba_hibrida(m_t, d1.iloc[te_t], M.markov_desde_vista(d1.iloc[tr_t], cats), cats)
    temporales = {"Cadena de Markov": "Markov", "Preferencia personal (moda)": "Preferencia", "Híbrido (ML + Markov + preferencia)": "Híbrido"}
    filas_extra = []
    for nombre, pred in preds.items():
        pred_t = np.array(cats)[pr_t[temporales[nombre]].argmax(axis=1)]
        filas_extra.append({"modelo": nombre, "exactitud": accuracy_score(y1, pred), "f1_macro": f1_score(y1, pred, average="macro"),
                            "kappa": cohen_kappa_score(y1, pred), "exactitud_temporal": accuracy_score(y1.iloc[te_t], pred_t)})
        mat1[nombre] = confusion_matrix(y1, pred, labels=clases1).tolist()
    tabla1 = pd.concat([tabla1, pd.DataFrame(filas_extra)]).sort_values("f1_macro", ascending=False)
    print("\n+ métodos probabilísticos y híbrido:\n" + pd.DataFrame(filas_extra).round(3).to_string(index=False))

    modelo1 = entrenar_final(X1, y1, num, cat, mejor1)
    joblib.dump(modelo1, M.MODELOS / "modelo_categoria.joblib")
    resultados["categoria"] = {
        "tabla": tabla1.to_dict("records"),
        "matrices": mat1,
        "clases": clases1,
        "mejor": mejor1,
        "distribucion": y1.value_counts().to_dict(),
        "importancia": importancia(X1, y1, num, cat).round(4).to_dict(),
    }

    # ---------------- Tarea 2: recompra en N días ----------------
    d2 = vista.dropna(subset=["recompra"]).reset_index(drop=True)
    X2, y2 = d2[num + cat + ["fecha"]], d2["recompra"]
    tabla2, mat2, clases2 = evaluar_clasificacion(X2, y2, num, cat, f"Recompra en {M.DIAS_RECOMPRA} días", binaria=True)
    mejor2 = tabla2[tabla2["modelo"] != "ZeroR (línea base)"].sort_values("auc_roc", ascending=False).iloc[0]["modelo"]
    modelo2 = entrenar_final(X2, y2, num, cat, mejor2)
    joblib.dump(modelo2, M.MODELOS / "modelo_recompra.joblib")
    resultados["recompra"] = {
        "tabla": tabla2.to_dict("records"),
        "matrices": mat2,
        "clases": clases2,
        "mejor": mejor2,
        "distribucion": y2.value_counts().to_dict(),
        "importancia": importancia(X2, y2, num, cat).round(4).to_dict(),
    }

    # Árbol interpretable (reglas SI-ENTONCES, como J48 en Weka)
    arbol = Pipeline([("prep", preprocesador(num, cat)), ("modelo", DecisionTreeClassifier(criterion="entropy", max_depth=3, min_samples_leaf=8, random_state=SEMILLA))])
    arbol.fit(X2, y2)
    nombres = [n.split("__", 1)[1] for n in arbol.named_steps["prep"].get_feature_names_out()]
    resultados["recompra"]["arbol_texto"] = export_text(arbol.named_steps["modelo"], feature_names=nombres)
    print("\nÁrbol de decisión (recompra) — atributos estandarizados (z):\n" + resultados["recompra"]["arbol_texto"])

    # ---------------- Tarea 3: gasto del siguiente pedido (regresión) ----------------
    d3 = vista.dropna(subset=["ticket_siguiente"]).reset_index(drop=True)
    X3, y3 = d3[num + cat], d3["ticket_siguiente"]
    regs = {
        "Media (línea base)": DummyRegressor(),
        "Regresión lineal": LinearRegression(),
        "Random Forest": RandomForestRegressor(n_estimators=300, max_depth=5, min_samples_leaf=4, random_state=SEMILLA),
    }
    filas = []
    for nombre, reg in regs.items():
        pipe = Pipeline([("prep", preprocesador(num, cat)), ("modelo", reg)])
        pred = cross_val_predict(pipe, X3, y3, cv=KFold(10, shuffle=True, random_state=SEMILLA))
        filas.append({"modelo": nombre, "mae": mean_absolute_error(y3, pred), "r2": r2_score(y3, pred)})
    tabla3 = pd.DataFrame(filas).sort_values("mae")
    print("\n=== Gasto del siguiente pedido ===\n" + tabla3.round(3).to_string(index=False))
    mejor3 = tabla3.iloc[0]["modelo"]
    modelo3 = Pipeline([("prep", preprocesador(num, cat)), ("modelo", regs[mejor3])]).fit(X3, y3)
    joblib.dump(modelo3, M.MODELOS / "modelo_ticket.joblib")
    resultados["ticket"] = {"tabla": tabla3.to_dict("records"), "mejor": mejor3}

    # ---------------- Agrupamiento K-Means (segmentación) ----------------
    rfm = M.tabla_rfm(datos, trans)
    vars_cluster = ["recencia_dias", "frecuencia", "monetario", "ticket_promedio"] + [f"pct_{c}" for c in cats]
    Z = StandardScaler().fit_transform(rfm[vars_cluster])
    siluetas = {}
    for k in range(2, 7):
        etiquetas = KMeans(n_clusters=k, n_init=20, random_state=SEMILLA).fit_predict(Z)
        siluetas[k] = float(silhouette_score(Z, etiquetas))
    k_opt = max(siluetas, key=siluetas.get)
    km = KMeans(n_clusters=k_opt, n_init=20, random_state=SEMILLA).fit(Z)
    rfm["segmento"] = km.labels_
    perfil = rfm.groupby("segmento")[["recencia_dias", "frecuencia", "monetario", "ticket_promedio"]].mean()
    # nombres de negocio según valor y recencia
    orden_valor = perfil["monetario"].rank(ascending=False)
    nombres_seg = {}
    for s in perfil.index:
        if perfil.loc[s, "recencia_dias"] > 21 and orden_valor[s] == 1:
            nombres_seg[s] = "Alto valor inactivos (recuperar)"
        elif perfil.loc[s, "recencia_dias"] > 21:
            nombres_seg[s] = "En riesgo / inactivos"
        elif orden_valor[s] == 1:
            nombres_seg[s] = "Campeones (alto valor)"
        elif perfil.loc[s, "frecuencia"] <= 3:
            nombres_seg[s] = "Nuevos"
        else:
            nombres_seg[s] = "Leales (valor medio)"
    # evita nombres repetidos
    vistos = {}
    for s, n in nombres_seg.items():
        vistos[n] = vistos.get(n, 0) + 1
        if vistos[n] > 1:
            nombres_seg[s] = f"{n} {vistos[n]}"
    rfm["segmento_nombre"] = rfm["segmento"].map(nombres_seg)
    pca = PCA(n_components=2, random_state=SEMILLA).fit(Z)
    coords = pca.transform(Z)
    rfm["pc1"], rfm["pc2"] = coords[:, 0], coords[:, 1]
    rfm.to_csv(M.MODELOS / "segmentos_clientes.csv")
    resultados["clustering"] = {
        "k": k_opt,
        "siluetas": siluetas,
        "nombres": {int(k): v for k, v in nombres_seg.items()},
        "varianza_pca": pca.explained_variance_ratio_.tolist(),
    }
    print(f"\n=== K-Means: k óptimo = {k_opt} (silueta {siluetas[k_opt]:.3f}) ===")
    print(rfm.groupby("segmento_nombre")[["recencia_dias", "frecuencia", "monetario"]].mean().round(1))

    # ---------------- Reglas de asociación (Apriori) ----------------
    reglas_total = []
    for nivel, canasta, soporte in (("categoría", M.cestas(trans, cats), 0.03), ("producto", M.cestas_producto(datos), 0.02)):
        frecuentes = apriori(canasta, min_support=soporte, use_colnames=True)
        if len(frecuentes) and frecuentes["itemsets"].map(len).max() > 1:
            r = association_rules(frecuentes, metric="confidence", min_threshold=0.2)
            r = r[r["lift"] > 1].copy()
            r["nivel"] = nivel
            reglas_total.append(r)
    reglas = pd.concat(reglas_total) if reglas_total else pd.DataFrame()
    if len(reglas):
        reglas["antecedente"] = reglas["antecedents"].map(lambda s: ", ".join(sorted(s)))
        reglas["consecuente"] = reglas["consequents"].map(lambda s: ", ".join(sorted(s)))
        reglas = reglas[["nivel", "antecedente", "consecuente", "support", "confidence", "lift"]].sort_values("lift", ascending=False)
        reglas.columns = ["nivel", "antecedente", "consecuente", "soporte", "confianza", "lift"]
    reglas.to_csv(M.MODELOS / "reglas_asociacion.csv", index=False)
    print(f"\n=== Apriori: {len(reglas)} reglas con lift > 1 ===")
    print(reglas.head(10).round(3).to_string(index=False))

    # ---------------- Matrices y análisis factorial ----------------
    M.markov_desde_vista(vista, cats).to_csv(M.MODELOS / "matriz_transicion.csv")
    corr = vista[num].corr().round(3)
    corr.to_csv(M.MODELOS / "matriz_correlacion.csv")
    Zf = StandardScaler().fit_transform(vista[num])
    pca_f = PCA(random_state=SEMILLA).fit(Zf)
    cargas = pd.DataFrame(pca_f.components_[:4].T, index=num, columns=[f"Factor {i+1}" for i in range(4)]).round(3)
    cargas.to_csv(M.MODELOS / "cargas_factoriales.csv")
    resultados["factores"] = {"varianza": pca_f.explained_variance_ratio_[:8].round(4).tolist()}
    M.matriz_cliente_producto(datos).to_csv(M.MODELOS / "matriz_cliente_producto.csv")

    (M.MODELOS / "resultados.json").write_text(json.dumps(resultados, ensure_ascii=False, indent=2, default=float), encoding="utf-8")
    print("\nModelos y resultados guardados en", M.MODELOS)


if __name__ == "__main__":
    main()
