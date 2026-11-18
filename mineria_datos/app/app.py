"""
Plataforma de predicción del comportamiento de compra — Minería de datos.

Ejecutar desde la carpeta mineria_datos:
    streamlit run app/app.py
"""
from __future__ import annotations

import json
import sys
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
import plotly.express as px
import plotly.graph_objects as go
import streamlit as st
from mlxtend.frequent_patterns import apriori, association_rules
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
import mineria as M  # noqa: E402

st.set_page_config(page_title="Predicción de compra · Minería de datos", page_icon="🔮", layout="wide")

# Paleta categórica validada (orden fijo; el color sigue a la categoría, no al ranking)
PALETA = ["#2a78d6", "#eb6834", "#1baf7a", "#eda100", "#e87ba4", "#008300", "#6250d6", "#e34948"]
SECUENCIAL = ["#cde2fb", "#86b6ef", "#3987e5", "#256abf", "#184f95", "#0d366b"]
DIVERGENTE = [[0, "#e34948"], [0.5, "#f1f0ed"], [1, "#2a78d6"]]
PLANTILLA = "plotly_white"


def grafico(fig, alto=360):
    fig.update_layout(template=PLANTILLA, height=alto, margin=dict(l=10, r=10, t=40, b=10),
                      font=dict(size=13), hoverlabel=dict(font_size=13), legend_title_text="")
    fig.update_xaxes(showgrid=False)
    fig.update_yaxes(gridcolor="rgba(0,0,0,0.06)")
    return fig


# ---------------------------------------------------------------- carga de datos
@st.cache_data
def cargar():
    datos = M.cargar_datos()
    cats = M.lista_categorias(datos)
    trans = M.tabla_transacciones(datos)
    vista = M.vista_minable(datos, trans)
    rfm = M.tabla_rfm(datos, trans)
    return datos, cats, trans, vista, rfm


@st.cache_resource
def modelos():
    if not (M.MODELOS / "resultados.json").exists():
        return None
    return {
        "categoria": joblib.load(M.MODELOS / "modelo_categoria.joblib"),
        "recompra": joblib.load(M.MODELOS / "modelo_recompra.joblib"),
        "ticket": joblib.load(M.MODELOS / "modelo_ticket.joblib"),
        "resultados": json.loads((M.MODELOS / "resultados.json").read_text(encoding="utf-8")),
        "P": pd.read_csv(M.MODELOS / "matriz_transicion.csv", index_col=0),
        "segmentos": pd.read_csv(M.MODELOS / "segmentos_clientes.csv", index_col=0),
        "reglas": pd.read_csv(M.MODELOS / "reglas_asociacion.csv"),
    }


datos, CATS, trans, vista, rfm = cargar()
MOD = modelos()
COLOR_CAT = {c: PALETA[i] for i, c in enumerate(CATS)}
if MOD is None:
    st.error("Primero entrene los modelos: `python scripts/02_entrenar_modelos.py`")
    st.stop()
RES = MOD["resultados"]
NUM, CATCOLS = M.columnas_atributos(CATS)


def cop(x):
    return f"${x:,.0f}".replace(",", ".")


def predecir(filas: pd.DataFrame) -> dict:
    probs = M.proba_hibrida(MOD["categoria"], filas, MOD["P"], CATS)
    clases = list(MOD["recompra"].classes_)
    return {
        "probs": probs,
        "recompra": MOD["recompra"].predict_proba(filas)[:, clases.index("si")],
        "ticket": MOD["ticket"].predict(filas),
    }


# ---------------------------------------------------------------- navegación
st.sidebar.title("🔮 Minería de datos")
st.sidebar.caption("Predicción de la decisión futura del cliente · Tienda FIRE CAT")
PAGINAS = [
    "🏠 Resumen y metodología",
    "🔮 Predicción por cliente",
    "🧪 Simulador (¿qué pasaría si…?)",
    "👥 Segmentación (K-Means)",
    "🛒 Reglas de asociación (Apriori)",
    "📊 Evaluación de modelos",
    "🧮 Matrices y factores",
    "📥 Exportar (Weka · RapidMiner · Power BI)",
]
pagina = st.sidebar.radio("Sección", PAGINAS, label_visibility="collapsed")
st.sidebar.divider()
st.sidebar.caption(
    f"Datos: {len(trans)} pedidos · {trans['usuario_id'].nunique()} clientes · {len(datos['detalle'])} líneas de pedido\n\n"
    f"Corte: {datos['fecha_corte']:%d/%m/%Y}"
)

# ================================================================= 1. RESUMEN
if pagina == PAGINAS[0]:
    st.title("Plataforma de predicción del comportamiento de compra")
    st.write(
        "A partir de la base de datos transaccional de la tienda, la plataforma aplica el proceso **KDD / CRISP-DM** "
        "para anticipar **la próxima decisión de cada cliente**: qué categoría comprará, si volverá a comprar pronto "
        "y cuánto gastará, y lo complementa con segmentación y reglas de asociación."
    )
    validos = trans[trans["cancelado"] == 0]
    c1, c2, c3, c4 = st.columns(4)
    c1.metric("Ventas (sin cancelados)", cop(validos["total"].sum()))
    c2.metric("Pedidos", f"{len(trans)}")
    c2.caption(f"{trans['cancelado'].sum()} cancelados")
    c3.metric("Ticket promedio", cop(validos["total"].mean()))
    c4.metric("Días entre compras (mediana)", f"{trans['dias_desde_anterior'].median():.1f}")

    col1, col2 = st.columns(2)
    semanal = validos.set_index("fecha_pedido").resample("W")["total"].sum().reset_index()
    fig = px.line(semanal, x="fecha_pedido", y="total", markers=True, title="Ventas por semana (COP)")
    fig.update_traces(line=dict(width=2, color=PALETA[0]), marker=dict(size=8),
                      hovertemplate="Semana del %{x|%d/%m}<br>%{y:$,.0f}<extra></extra>")
    col1.plotly_chart(grafico(fig).update_layout(xaxis_title="", yaxis_title=""), width="stretch")

    vc = datos["detalle"][datos["detalle"]["estado"] != "cancelado"].groupby("categoria")["subtotal"].sum().sort_values()
    fig = px.bar(x=vc.values, y=vc.index, orientation="h", title="Ventas por categoría (COP)",
                 color=vc.index, color_discrete_map=COLOR_CAT)
    fig.update_traces(hovertemplate="%{y}: %{x:$,.0f}<extra></extra>", marker_line_width=0)
    col2.plotly_chart(grafico(fig).update_layout(showlegend=False, xaxis_title="", yaxis_title="", bargap=0.35), width="stretch")

    col1, col2 = st.columns(2)
    ciudad = validos.groupby("ciudad")["total"].sum().sort_values()
    fig = px.bar(x=ciudad.values, y=ciudad.index, orientation="h", title="Ventas por ciudad (COP)")
    fig.update_traces(marker_color=PALETA[0], hovertemplate="%{y}: %{x:$,.0f}<extra></extra>")
    col1.plotly_chart(grafico(fig).update_layout(xaxis_title="", yaxis_title="", bargap=0.35), width="stretch")
    dias = validos.groupby("dia_semana").size().reindex(range(7), fill_value=0)
    fig = px.bar(x=M.DIAS_SEMANA, y=dias.values, title="Pedidos por día de la semana")
    fig.update_traces(marker_color=PALETA[0], hovertemplate="%{x}: %{y} pedidos<extra></extra>")
    col2.plotly_chart(grafico(fig).update_layout(xaxis_title="", yaxis_title="", bargap=0.35), width="stretch")

    st.subheader("Metodología (CRISP-DM)")
    st.markdown(
        """
| Fase | Qué se hizo en esta plataforma |
|---|---|
| **1. Comprensión del negocio** | Pregunta: *¿qué hará el cliente en su próxima compra?* → categoría, recompra en 7 días y gasto. |
| **2. Comprensión de los datos** | 13 tablas del volcado MySQL (`pedidos`, `pedido_detalle`, `productos`, `usuarios`, …); exploración de esta página. |
| **3. Preparación** | Limpieza (espacios, ciudades escritas a mano), integración (joins), anonimización y construcción de la **vista minable**: una fila por cliente después de cada pedido, con atributos calculados **solo con el pasado** (sin fuga de datos). |
| **4. Modelado** | Clasificación (ZeroR, Naive Bayes, J48, k-NN, regresión logística, Random Forest, Gradient Boosting), cadena de Markov, modelo híbrido, regresión, K-Means, Apriori, PCA. |
| **5. Evaluación** | Validación cruzada estratificada de 10 pliegues (como Weka) + prueba temporal (entrena con el 80 % más antiguo), exactitud, F1, Kappa, AUC y matrices de confusión. |
| **6. Despliegue** | Este dashboard + exportes ARFF (Weka), CSV (RapidMiner) y modelo estrella (Power BI). |
"""
    )

# ================================================================= 2. PREDICCIÓN POR CLIENTE
elif pagina == PAGINAS[1]:
    st.title("Predicción de la próxima decisión del cliente")
    actual = vista[vista["es_ultimo"]].set_index("usuario_id")
    seg = MOD["segmentos"]
    opciones = sorted(actual.index)
    uid = st.selectbox("Cliente", opciones, format_func=lambda u: f"Cliente #{u} · {actual.loc[u, 'ciudad']} · {int(actual.loc[u, 'n_pedidos'])} pedidos")
    fila = actual.loc[[uid]].reset_index()
    pred = predecir(fila)
    probs = {k: v[0] for k, v in pred["probs"].items()}
    hib = probs["Híbrido"]
    top = CATS[int(np.argmax(hib))]
    dias_sin = (datos["fecha_corte"] - fila.loc[0, "fecha"]).total_seconds() / 86400
    intervalo = fila.loc[0, "intervalo_promedio"]
    ratio = dias_sin / max(intervalo, 1)
    estado = "🟢 Activo" if ratio <= 1.5 else ("🟡 Atrasado" if ratio <= 3 else "🔴 En riesgo")

    c1, c2, c3, c4 = st.columns(4)
    c1.metric("Próxima categoría", top, help="Predicción del modelo híbrido")
    c1.caption(f"{hib.max():.0%} de probabilidad")
    c2.metric(f"Recompra ≤ {M.DIAS_RECOMPRA} días", f"{pred['recompra'][0]:.0%}",
              help=f"Probabilidad de volver a comprar en los {M.DIAS_RECOMPRA} días siguientes a su último pedido .")
    c2.caption("contados desde su último pedido")
    c3.metric("Gasto esperado", cop(pred["ticket"][0]))
    c3.caption("próximo pedido")
    c4.metric("Estado actual", estado, help="Días sin comprar comparados con su intervalo habitual entre compras")
    c4.caption(f"{dias_sin:.0f} días sin comprar · suele tardar {intervalo:.1f}")
    if uid in seg.index:
        st.info(f"Segmento K-Means: **{seg.loc[uid, 'segmento_nombre']}**")

    col1, col2 = st.columns([1, 1])
    df = pd.DataFrame(probs, index=CATS).reset_index(names="categoria").melt(id_vars="categoria", var_name="fuente", value_name="prob")
    fig = px.bar(df, x="categoria", y="prob", color="fuente", barmode="group",
                 title="Probabilidad de la próxima categoría según cada técnica",
                 color_discrete_map={"ML": PALETA[0], "Markov": PALETA[1], "Preferencia": PALETA[2], "Híbrido": PALETA[6]})
    fig.update_traces(hovertemplate="%{x}<br>%{y:.1%}<extra>%{fullData.name}</extra>", marker_line_color="white", marker_line_width=2)
    col1.plotly_chart(grafico(fig, 400).update_layout(yaxis_tickformat=".0%", xaxis_title="", yaxis_title="", legend_orientation="h", legend_y=-0.12, legend_x=0), width="stretch")
    col1.caption(f"**ML** = {RES['categoria']['mejor']} · **Markov** = última categoría → siguiente · "
                 "**Preferencia** = lo que más ha comprado · **Híbrido** = promedio de las tres (predicción final).")

    # Recomendación de productos: filtrado colaborativo (similitud coseno cliente-cliente) + categoría prevista
    mcp = M.matriz_cliente_producto(datos)
    norma = np.linalg.norm(mcp.values, axis=1, keepdims=True)
    normal = mcp.values / np.where(norma == 0, 1, norma)
    sim = pd.DataFrame(normal @ normal.T, index=mcp.index, columns=mcp.index)
    vecinos = sim.loc[uid].drop(uid).nlargest(5)
    puntaje = (mcp.loc[vecinos.index].T @ vecinos) / vecinos.sum()
    prods = datos["productos"].set_index("id")
    rec = pd.DataFrame({"puntaje_cf": puntaje}).join(prods[["nombre", "categoria", "precio"]])
    rec["prob_categoria"] = rec["categoria"].map(dict(zip(CATS, hib)))
    rec["ya_lo_compro"] = rec.index.isin(mcp.columns[mcp.loc[uid] > 0])
    rec["puntaje"] = (rec["puntaje_cf"] / max(rec["puntaje_cf"].max(), 1e-9)) * 0.5 + (rec["prob_categoria"] / hib.max()) * 0.5
    rec = rec.sort_values("puntaje", ascending=False).head(6)
    col2.markdown("**Productos recomendados** (filtrado colaborativo + categoría prevista)")
    col2.dataframe(
        rec[["nombre", "categoria", "precio", "puntaje"]],
        hide_index=True, width="stretch",
        column_config={"precio": st.column_config.NumberColumn(format="$%d"),
                       "puntaje": st.column_config.ProgressColumn(min_value=0, max_value=1, format="%.2f")},
    )
    col2.caption("Clientes más parecidos: " + ", ".join(f"#{v} ({s:.2f})" for v, s in vecinos.items()))
    reglas = MOD["reglas"]
    ult = fila.loc[0, "ultima_categoria"]
    aplicables = reglas[(reglas["nivel"] == "categoría") & (reglas["antecedente"] == ult)]
    if len(aplicables):
        r = aplicables.iloc[0]
        col2.success(f"Regla Apriori: quien compra **{r['antecedente']}** también lleva **{r['consecuente']}** "
                     f"(confianza {r['confianza']:.0%}, lift {r['lift']:.2f}) → oferta cruzada sugerida.")

    st.subheader("Historial del cliente")
    h = trans[trans["usuario_id"] == uid][["pedido_id", "fecha_pedido", "estado", "total", "items", "categoria_principal", "dias_desde_anterior"]]
    fig = px.scatter(h, x="fecha_pedido", y="total", color="categoria_principal", color_discrete_map=COLOR_CAT,
                     title="Pedidos en el tiempo (color = categoría principal)", hover_data=["estado", "items"])
    fig.update_traces(marker=dict(size=12, line=dict(color="white", width=2)))
    st.plotly_chart(grafico(fig, 320).update_layout(xaxis_title="", yaxis_title="Total (COP)"), width="stretch")
    st.dataframe(h.sort_values("fecha_pedido", ascending=False), hide_index=True, width="stretch")

# ================================================================= 3. SIMULADOR
elif pagina == PAGINAS[2]:
    st.title("Simulador de escenarios")
    st.write("Describa un cliente (real o hipotético) y la plataforma predice su próxima decisión con los modelos entrenados.")
    base = vista[vista["es_ultimo"]].median(numeric_only=True)
    c1, c2, c3 = st.columns(3)
    n_ped = c1.slider("Pedidos realizados", 1, 30, int(base["n_pedidos"]))
    ticket = c1.slider("Ticket promedio (COP)", 50_000, 500_000, int(base["ticket_promedio"]), step=5_000)
    ticket_ult = c1.slider("Valor del último pedido (COP)", 50_000, 600_000, int(base["ticket_ultimo"]), step=5_000)
    intervalo = c2.slider("Días promedio entre compras", 0.0, 40.0, float(round(base["intervalo_promedio"], 1)))
    desde_ant = c2.slider("Días entre los dos últimos pedidos", 0.0, 40.0, float(round(base["dias_desde_anterior"], 1)))
    items_ult = c2.slider("Unidades en el último pedido", 1, 8, int(base["items_ultimo"]))
    ultima = c3.selectbox("Categoría del último pedido", CATS)
    ciudad = c3.selectbox("Ciudad", sorted(vista["ciudad"].unique()))
    dia = c3.selectbox("Día del último pedido", range(7), format_func=lambda d: M.DIAS_SEMANA[d])
    hora = c3.slider("Hora del último pedido", 0, 23, 15)
    st.markdown("**Mezcla histórica de compras (%)** — fila de la matriz cliente × categoría")
    cols = st.columns(len(CATS))
    pesos = [cols[i].number_input(c, 0, 100, 100 // len(CATS), key=f"p_{c}") for i, c in enumerate(CATS)]
    total_p = max(sum(pesos), 1)
    envio = st.slider("Proporción de pedidos con envío gratis", 0.0, 1.0, float(base["tasa_envio_gratis"]))

    fila = {"n_pedidos": n_ped, "gasto_total": ticket * n_ped, "ticket_promedio": ticket, "ticket_ultimo": ticket_ult,
            "items_ultimo": items_ult, "intervalo_promedio": intervalo, "dias_desde_anterior": desde_ant,
            "tasa_cancelacion": 0.0, "tasa_envio_gratis": envio, "variedad_categorias": sum(p > 0 for p in pesos),
            "dia_semana": dia, "hora": hora, "ultima_categoria": ultima, "ciudad": ciudad}
    fila.update({f"pct_{c}": p / total_p for c, p in zip(CATS, pesos)})
    pred = predecir(pd.DataFrame([fila]))
    hib = pred["probs"]["Híbrido"][0]
    st.divider()
    c1, c2, c3 = st.columns(3)
    c1.metric("Próxima categoría", CATS[int(np.argmax(hib))])
    c1.caption(f"{hib.max():.0%} de probabilidad")
    c2.metric(f"Probabilidad de recompra ≤ {M.DIAS_RECOMPRA} días", f"{pred['recompra'][0]:.0%}")
    c3.metric("Gasto esperado", cop(pred["ticket"][0]))
    s = pd.Series(hib, index=CATS).sort_values()
    fig = px.bar(x=s.values, y=s.index, orientation="h", color=s.index, color_discrete_map=COLOR_CAT, title="Probabilidad (modelo híbrido)")
    fig.update_traces(hovertemplate="%{y}: %{x:.1%}<extra></extra>")
    st.plotly_chart(grafico(fig, 300).update_layout(showlegend=False, xaxis_tickformat=".0%", xaxis_title="", yaxis_title="", bargap=0.35), width="stretch")

# ================================================================= 4. SEGMENTACIÓN
elif pagina == PAGINAS[3]:
    st.title("Segmentación de clientes — K-Means sobre RFM")
    st.write("**R**ecencia (días desde la última compra), **F**recuencia (pedidos) y **M**onetario (gasto), más la mezcla de categorías. Variables estandarizadas (z-score).")
    vars_c = st.multiselect("Variables del agrupamiento", list(rfm.columns.drop("ciudad")),
                            default=["recencia_dias", "frecuencia", "monetario", "ticket_promedio"] + [f"pct_{c}" for c in CATS])
    if len(vars_c) < 2:
        st.warning("Seleccione al menos dos variables.")
        st.stop()
    Z = StandardScaler().fit_transform(rfm[vars_c])
    sil = {k: silhouette_score(Z, KMeans(k, n_init=20, random_state=42).fit_predict(Z)) for k in range(2, 8)}
    inercia = {k: KMeans(k, n_init=20, random_state=42).fit(Z).inertia_ for k in range(1, 8)}
    k = st.slider("Número de grupos (k)", 2, 7, max(sil, key=sil.get))
    etiquetas = KMeans(k, n_init=20, random_state=42).fit_predict(Z)
    pcs = PCA(2, random_state=42).fit_transform(Z)
    d = rfm.copy()
    d["grupo"] = [f"Grupo {e + 1}" for e in etiquetas]
    d["PC1"], d["PC2"] = pcs[:, 0], pcs[:, 1]
    col1, col2 = st.columns(2)
    fig = go.Figure()
    fig.add_scatter(x=list(inercia), y=list(inercia.values()), mode="lines+markers", name="Inercia (codo)", line=dict(color=PALETA[0], width=2), marker=dict(size=8))
    col1.plotly_chart(grafico(fig, 300).update_layout(title="Método del codo", xaxis_title="k", yaxis_title="Inercia"), width="stretch")
    fig = go.Figure()
    fig.add_scatter(x=list(sil), y=list(sil.values()), mode="lines+markers", name="Silueta", line=dict(color=PALETA[1], width=2), marker=dict(size=8))
    col2.plotly_chart(grafico(fig, 300).update_layout(title="Coeficiente de silueta (mayor es mejor)", xaxis_title="k", yaxis_title="Silueta"), width="stretch")
    colores_g = {f"Grupo {i + 1}": PALETA[i] for i in range(k)}
    fig = px.scatter(d.reset_index(), x="PC1", y="PC2", color="grupo", color_discrete_map=colores_g, size="monetario",
                     hover_data={"usuario_id": True, "frecuencia": True, "recencia_dias": ":.0f", "monetario": ":$,.0f", "PC1": False, "PC2": False},
                     title="Clientes proyectados en 2 componentes principales (PCA)")
    fig.update_traces(marker=dict(line=dict(color="white", width=2)))
    st.plotly_chart(grafico(fig, 420), width="stretch")
    perfil = d.groupby("grupo")[vars_c].mean()
    perfil.insert(0, "clientes", d.groupby("grupo").size())
    st.subheader("Perfil promedio de cada grupo")
    st.dataframe(perfil.style.format(precision=2), width="stretch")
    st.caption("Interpretación sugerida: alta recencia = clientes que hace tiempo no compran (reactivar con campañas); alta frecuencia y monetario = clientes de alto valor (fidelizar).")

# ================================================================= 5. APRIORI
elif pagina == PAGINAS[4]:
    st.title("Reglas de asociación — Apriori (análisis de canasta)")
    nivel = st.radio("Nivel de la canasta", ["categoría", "producto"], horizontal=True)
    c1, c2 = st.columns(2)
    soporte = c1.slider("Soporte mínimo", 0.01, 0.20, 0.03 if nivel == "categoría" else 0.02, 0.01)
    confianza = c2.slider("Confianza mínima", 0.05, 0.9, 0.2, 0.05)
    canasta = M.cestas(trans, CATS) if nivel == "categoría" else M.cestas_producto(datos)
    frec = apriori(canasta, min_support=soporte, use_colnames=True)
    st.write(f"Canastas analizadas: **{len(canasta)}** · Conjuntos frecuentes: **{len(frec)}**")
    if len(frec) and frec["itemsets"].map(len).max() > 1:
        r = association_rules(frec, metric="confidence", min_threshold=confianza)
        r["antecedente"] = r["antecedents"].map(lambda s: ", ".join(sorted(s)))
        r["consecuente"] = r["consequents"].map(lambda s: ", ".join(sorted(s)))
        r = r[["antecedente", "consecuente", "support", "confidence", "lift"]].rename(columns={"support": "soporte", "confidence": "confianza"}).sort_values("lift", ascending=False)
        if len(r):
            st.dataframe(r.style.format({"soporte": "{:.3f}", "confianza": "{:.1%}", "lift": "{:.2f}"}), hide_index=True, width="stretch")
            fig = px.scatter(r, x="soporte", y="confianza", size="lift", color="lift", color_continuous_scale=SECUENCIAL,
                             hover_data=["antecedente", "consecuente"], title="Soporte vs. confianza (tamaño y color = lift)")
            st.plotly_chart(grafico(fig, 380).update_layout(yaxis_tickformat=".0%"), width="stretch")
        else:
            st.warning("No hay reglas con esa confianza. Baje los umbrales.")
    else:
        st.warning("No hay conjuntos frecuentes de 2 o más elementos con ese soporte. Baje el soporte mínimo.")
    st.caption("**Lift > 1**: los artículos se compran juntos más de lo esperado por azar. Con pocos pedidos multi-categoría (≈45 %), las reglas son débiles; "
               "el resultado es el mismo que da *Associate → Apriori* en Weka con el archivo `cestas_categorias.arff`.")
    co = canasta.astype(int).T @ canasta.astype(int)
    if nivel == "categoría":
        fig = px.imshow(co, text_auto=True, color_continuous_scale=SECUENCIAL, title="Matriz de co-ocurrencia (pedidos que incluyen ambas categorías)")
        st.plotly_chart(grafico(fig, 420), width="stretch")

# ================================================================= 6. EVALUACIÓN
elif pagina == PAGINAS[5]:
    st.title("Evaluación y comparación de modelos")
    tarea = st.radio("Tarea", ["Siguiente categoría", f"Recompra en {M.DIAS_RECOMPRA} días", "Gasto del siguiente pedido"], horizontal=True)
    if tarea == "Gasto del siguiente pedido":
        t = pd.DataFrame(RES["ticket"]["tabla"])
        st.dataframe(t.style.format({"mae": "${:,.0f}", "r2": "{:.3f}"}), hide_index=True, width="stretch")
        st.info("Ningún regresor supera a la media (R² ≤ 0). El gasto del próximo pedido no se puede anticipar con estos datos más allá del promedio; "
                "la plataforma usa el mejor por MAE.")
        st.stop()
    clave = "categoria" if tarea == "Siguiente categoría" else "recompra"
    r = RES[clave]
    t = pd.DataFrame(r["tabla"])
    fmt = {c: "{:.3f}" for c in t.columns if c != "modelo"}
    st.dataframe(t.style.format(fmt).highlight_max(subset=[c for c in t.columns if c != "modelo"], color="#cde2fb"), hide_index=True, width="stretch")
    st.caption("Exactitud, F1 macro y Kappa: validación cruzada estratificada de 10 pliegues. *Exactitud temporal*: entrenado con el 80 % de pedidos más antiguos y probado con el 20 % más reciente.")
    base = t[t["modelo"].str.startswith("ZeroR")].iloc[0]
    mejor_k = t.sort_values("kappa", ascending=False).iloc[0]
    st.warning(
        f"**Lectura honesta de los resultados.** El mejor Kappa es {mejor_k['kappa']:.3f} ({mejor_k['modelo']}); un Kappa cercano a 0 significa que el acierto "
        f"apenas supera al azar. La línea base ZeroR acierta {base['exactitud']:.1%} siempre diciendo la clase más común. "
        "Con 19 clientes y ~200 pedidos, buena parte de ellos de prueba, **los datos no contienen un patrón fuerte**: la plataforma está lista, "
        "pero su precisión mejorará a medida que la tienda acumule pedidos reales. Esto también es un hallazgo válido de minería de datos."
    )
    col1, col2 = st.columns(2)
    lista_m = list(r["matrices"])
    modelo_sel = col1.selectbox("Matriz de confusión del modelo", lista_m, index=lista_m.index(t.iloc[0]["modelo"]))
    cm = pd.DataFrame(r["matrices"][modelo_sel], index=r["clases"], columns=r["clases"])
    fig = px.imshow(cm, text_auto=True, color_continuous_scale=SECUENCIAL, labels=dict(x="Predicho", y="Real", color="Casos"), title=f"Matriz de confusión — {modelo_sel}")
    col1.plotly_chart(grafico(fig, 420), width="stretch")
    imp = pd.Series(r["importancia"]).sort_values().tail(12)
    fig = px.bar(x=imp.values, y=imp.index, orientation="h", title="Importancia de atributos (Random Forest)")
    fig.update_traces(marker_color=PALETA[0], hovertemplate="%{y}: %{x:.3f}<extra></extra>")
    col2.plotly_chart(grafico(fig, 420).update_layout(xaxis_title="", yaxis_title="", bargap=0.3), width="stretch")
    dist = pd.Series(r["distribucion"])
    st.write("Distribución de la clase:", ", ".join(f"**{k}**: {v}" for k, v in dist.items()))
    if clave == "recompra":
        with st.expander("Árbol de decisión (reglas SI–ENTONCES, equivalente a J48)"):
            st.code(r["arbol_texto"], language="text")
            st.caption("Los umbrales están en unidades estandarizadas (z-score).")

# ================================================================= 7. MATRICES
elif pagina == PAGINAS[6]:
    st.title("Matrices y análisis factorial")
    tab1, tab2, tab3, tab4 = st.tabs(["Transición (Markov)", "Cliente × categoría", "Correlación", "Factores (PCA)"])
    with tab1:
        P = MOD["P"]
        fig = px.imshow(P, text_auto=".0%", color_continuous_scale=SECUENCIAL, labels=dict(x="Siguiente categoría", y="Categoría actual", color="P"),
                        title="Matriz de transición P(siguiente | actual) con suavizado de Laplace")
        st.plotly_chart(grafico(fig, 460), width="stretch")
        st.caption("Cada fila suma 100 %. Se usa como una de las tres fuentes del modelo híbrido.")
    with tab2:
        mcc = rfm[[f"pct_{c}" for c in CATS]].rename(columns=lambda c: c[4:])
        mcc.index = [f"#{i}" for i in mcc.index]
        fig = px.imshow(mcc, text_auto=".0%", color_continuous_scale=SECUENCIAL, aspect="auto", title="Proporción de unidades compradas por categoría (fila = cliente)")
        st.plotly_chart(grafico(fig, 560), width="stretch")
    with tab3:
        corr = pd.read_csv(M.MODELOS / "matriz_correlacion.csv", index_col=0)
        fig = px.imshow(corr, color_continuous_scale=DIVERGENTE, zmin=-1, zmax=1, aspect="auto", title="Matriz de correlación de Pearson (atributos de la vista minable)")
        st.plotly_chart(grafico(fig, 620), width="stretch")
    with tab4:
        cargas = pd.read_csv(M.MODELOS / "cargas_factoriales.csv", index_col=0)
        var = RES["factores"]["varianza"]
        fig = px.bar(x=[f"F{i + 1}" for i in range(len(var))], y=var, title="Varianza explicada por cada factor")
        fig.update_traces(marker_color=PALETA[0], hovertemplate="%{x}: %{y:.1%}<extra></extra>")
        st.plotly_chart(grafico(fig, 300).update_layout(yaxis_tickformat=".0%", xaxis_title="", yaxis_title="", bargap=0.35), width="stretch")
        fig = px.imshow(cargas, text_auto=".2f", color_continuous_scale=DIVERGENTE, zmin=-1, zmax=1, aspect="auto", title="Cargas factoriales (componentes principales)")
        st.plotly_chart(grafico(fig, 560), width="stretch")
        st.caption("Cargas altas (|valor| > 0,4) indican qué atributos definen cada factor latente: p. ej. un factor de *volumen* (n_pedidos, gasto_total) y otro de *ticket*.")

# ================================================================= 8. EXPORTAR
else:
    st.title("Exportar a Weka, RapidMiner y Power BI")
    if not M.EXPORTES.exists():
        st.error("Ejecute `python scripts/03_exportar.py`.")
        st.stop()
    for carpeta, titulo, ayuda in (
        ("weka", "Weka (.arff)", "Abrir en *Explorer → Open file*. Clasificar con J48 / NaiveBayes / IBk / Logistic usando *Cross-validation 10 folds*; `clientes_rfm` en *Cluster → SimpleKMeans*; `cestas_categorias` en *Associate → Apriori*."),
        ("rapidminer", "RapidMiner Studio (.csv, separador ;)", "Operador *Read CSV* → *Set Role* (label = `siguiente_categoria` o `recompra`) → *Cross Validation* con *Decision Tree* / *Naive Bayes* / *k-NN* → *Performance (Classification)*. Para canasta: *Numerical to Binominal* → *FP-Growth* → *Create Association Rules*."),
        ("powerbi", "Power BI (modelo estrella)", "*Obtener datos → Texto/CSV*. Relaciones: `hechos_ventas[producto_id]` → `dim_producto[id]`, `hechos_ventas[usuario_id]` → `dim_cliente_predicciones[usuario_id]`, `hechos_ventas[fecha]` → `dim_fecha[fecha]`. Ver `GUIA_HERRAMIENTAS.md` para medidas DAX."),
    ):
        st.subheader(titulo)
        st.caption(ayuda)
        archivos = sorted((M.EXPORTES / carpeta).glob("*.*"))
        cols = st.columns(3)
        for i, f in enumerate(archivos):
            cols[i % 3].download_button(f"⬇ {f.name}", f.read_bytes(), file_name=f.name, key=str(f))
