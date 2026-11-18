# Plataforma de minería de datos: predicción de la decisión futura del cliente

Proyecto de **Minería de Datos (8.º semestre)** sobre la base de datos de la tienda en línea (volcado MySQL
`if0_43160697_tienda.sql`, tienda de ropa *FIRE CAT*: 220 pedidos, 344 líneas de pedido, 19 clientes, 22 productos en 6 categorías).

La plataforma responde **¿qué hará el cliente en su próxima compra?**

| Pregunta | Técnica | Tipo |
|---|---|---|
| ¿Qué **categoría** comprará en su próximo pedido? | ZeroR, Naive Bayes, J48 (árbol C4.5), k-NN (IBk), regresión logística, Random Forest, Gradient Boosting, **cadena de Markov**, preferencia personal y **modelo híbrido** | Clasificación multiclase |
| ¿**Volverá a comprar** en los próximos 7 días? | Los mismos clasificadores + árbol de decisión interpretable (reglas SI–ENTONCES) | Clasificación binaria |
| ¿**Cuánto gastará**? | Regresión lineal, Random Forest | Regresión |
| ¿Qué **tipos de cliente** hay? | K-Means sobre RFM (recencia, frecuencia, monetario) + PCA | Agrupamiento |
| ¿Qué se **compra junto**? | Apriori (canasta por categoría y por producto) | Asociación |
| ¿Qué le **recomiendo**? | Filtrado colaborativo (similitud coseno en la matriz cliente × producto) + categoría prevista | Recomendación |

## Estructura

```
mineria_datos/
├── scripts/
│   ├── 01_extraer_sql.py       # Selección: volcado .sql → CSV anonimizados (sin MySQL)
│   ├── mineria.py              # Limpieza, integración, transformación, vista minable y matrices
│   ├── 02_entrenar_modelos.py  # Minería + evaluación (10-fold CV y prueba temporal)
│   └── 03_exportar.py          # ARFF (Weka), CSV (RapidMiner), modelo estrella (Power BI)
├── app/app.py                  # Dashboard / plataforma (Streamlit)
├── datos/                      # Tablas extraídas (sin nombres, correos, teléfonos ni contraseñas)
├── modelos/                    # Modelos .joblib, resultados.json y matrices en CSV
├── exportes/{weka,rapidminer,powerbi}/
└── GUIA_HERRAMIENTAS.md        # Paso a paso en Weka, RapidMiner Studio y Power BI (con medidas DAX)
```

## Cómo ejecutarlo

```bash
cd mineria_datos
pip install -r requirements.txt
```

Solo si cambia la base de datos (los CSV ya están incluidos):

```bash
python scripts/01_extraer_sql.py ruta/al/if0_43160697_tienda.sql
```

```bash
python scripts/02_entrenar_modelos.py
```

```bash
python scripts/03_exportar.py
```

```bash
streamlit run app/app.py
```

Se abre en `http://localhost:8501` (si al abrirla aparece un error al cargar los modelos `.joblib` por otra versión de scikit-learn, vuelva a ejecutar `02_entrenar_modelos.py`). Secciones: Resumen y metodología · Predicción por cliente · Simulador ·
Segmentación · Reglas de asociación · Evaluación de modelos · Matrices y factores · Exportar.

## Metodología (CRISP-DM / KDD)

1. **Comprensión del negocio** – anticipar la próxima decisión del cliente para campañas, inventario y venta cruzada.
2. **Comprensión de los datos** – 13 tablas útiles; los pedidos van del 01/07/2026 al 10/11/2026.
3. **Preparación**
   - Limpieza: espacios en textos, ciudades escritas a mano (`soacha-ciudad verde` → Soacha, `Ciudad Bolivar` → Bogotá), costos faltantes.
   - Integración: `pedido_detalle` ⨝ `productos` ⨝ `categorias` ⨝ `pedidos` ⨝ `usuarios`.
   - Anonimización: se eliminan nombres, correos, teléfonos, direcciones y hashes de contraseña.
   - **Vista minable**: una fila por cliente *después de cada pedido k*; los atributos se calculan **solo con los pedidos 1..k**
     y la clase es lo que ocurrió en el pedido k+1 (así se evita la fuga de datos). Atributos: número de pedidos, gasto total,
     ticket promedio y último, unidades, intervalo promedio entre compras, días desde el pedido anterior, tasa de cancelación,
     tasa de envío gratis, variedad de categorías, día y hora, ciudad, última categoría y la **fila de la matriz cliente × categoría** (proporciones).
4. **Modelado** – algoritmos equivalentes a los de Weka/RapidMiner (ver tabla de arriba).
5. **Evaluación** – validación cruzada estratificada de 10 pliegues (igual que Weka) + prueba temporal
   (entrenar con el 80 % más antiguo y probar con el 20 % más reciente). Métricas: exactitud, F1 macro, Kappa, AUC-ROC, MAE, R², silueta, soporte/confianza/lift.
6. **Despliegue** – dashboard en Streamlit y exportes para Weka, RapidMiner y Power BI.

### Matrices usadas

- Matriz **cliente × categoría** y **cliente × producto** (perfil de compra y filtrado colaborativo).
- Matriz **pedido × categoría** (canastas para Apriori) y su **co-ocurrencia**.
- Matriz de **transición de Markov** P(siguiente categoría | categoría actual), con suavizado de Laplace.
- Matriz de **correlación** de Pearson y matriz de **cargas factoriales** (PCA: factores latentes).
- **Matrices de confusión** de cada clasificador.

## Resultados (y su lectura honesta)

| Tarea | Línea base (ZeroR) | Mejor modelo | Kappa |
|---|---|---|---|
| Próxima categoría (6 clases, 201 casos) | exactitud 29,9 % | Regresión logística: 26,9 % · Gradient Boosting: mejor F1 (0,209 vs 0,077) | 0,053 |
| Recompra ≤ 7 días (219 casos, 68 % "sí") | exactitud 68,0 %, AUC 0,50 | Naive Bayes: AUC 0,54 | 0,046 |
| Gasto del siguiente pedido | MAE $84.015 | ningún modelo supera a la media (R² ≤ 0) | – |

Los modelos **apenas superan al azar** (Kappa < 0,06). Con 19 clientes y unos 200 pedidos, la mayoría de julio a
septiembre con apariencia de datos de prueba, la base **no tiene un patrón fuerte de comportamiento**. Esto es en sí
mismo un hallazgo de minería de datos (y la razón para comparar siempre contra ZeroR). Por eso la predicción final de la
plataforma usa un **modelo híbrido** que promedia ML, Markov y la preferencia histórica, y muestra las tres fuentes.
Todo el flujo es reproducible: si se carga un volcado nuevo con más pedidos reales, basta con ejecutar de nuevo los pasos 1–3.

Otros hallazgos:
- **K-Means** (k = 2 por silueta 0,35): *Alto valor inactivos (recuperar)*, con unos 13 pedidos y 52 días sin comprar,
  y *Nuevos* (3 pedidos, cuentas creadas en oct–nov).
- **Apriori**: Pantalones ↔ Accesorios (lift 1,20); *Sudadera bicolor Fanstore → Gorro Street* (lift 1,90): oportunidad de venta cruzada.
- Los atributos más importantes (Random Forest) son el ticket y el gasto acumulado; día/hora tienen poco peso.
