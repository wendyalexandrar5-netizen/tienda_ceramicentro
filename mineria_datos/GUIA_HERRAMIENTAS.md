# Guía: usar los datos en Weka, RapidMiner Studio y Power BI

Todos los archivos se generan con `python scripts/03_exportar.py` en la carpeta `exportes/`.

## 1. Weka (Explorer)

| Archivo | Pestaña | Qué hacer |
|---|---|---|
| `weka/siguiente_categoria.arff` | **Classify** | Clase = `siguiente_categoria`. Probar `rules.ZeroR` (línea base), `bayes.NaiveBayes`, `trees.J48`, `lazy.IBk` (k=7), `functions.Logistic`, `trees.RandomForest`. *Test options → Cross-validation, Folds 10*. Comparar **Kappa statistic** y la **Confusion Matrix**. |
| `weka/recompra_7_dias.arff` | **Classify** | Clase = `recompra` (si/no). Mismos algoritmos; revisar además el **ROC Area**. En J48, clic derecho sobre el resultado → *Visualize tree*. |
| `weka/clientes_rfm.arff` | **Cluster** | `SimpleKMeans` con `numClusters` 2–4 (en *Preprocess* aplicar antes `filters.unsupervised.attribute.Normalize`). También `EM` para que Weka estime k. |
| `weka/cestas_categorias.arff` | **Associate** | `Apriori` con `lowerBoundMinSupport = 0.03`, `metricType = Confidence`, `minMetric = 0.2`. `?` significa "no comprado". |

Atributos útiles en *Select attributes*: `InfoGainAttributeEval` + `Ranker` (comparar con la importancia de atributos del dashboard).

## 2. RapidMiner Studio ("Radio/Rapid Studio")

Archivos `rapidminer/*.csv` (separador `;`, decimal `,`).

**Clasificación (próxima decisión)**
1. `Read CSV` → `vista_minable.csv` (en el asistente: separador `;`, decimal `,`).
2. `Filter Examples` → condición `siguiente_categoria` *is not missing* (o `recompra` para la otra tarea).
3. `Select Attributes` → quitar `usuario_id`, `pedido_id`, `fecha`, `dias_hasta_siguiente`, `ticket_siguiente` y la clase que no se use (¡evita fuga de datos!).
4. `Set Role` → `siguiente_categoria` (o `recompra`) como **label**.
5. `Nominal to Numerical` (para k-NN / Logistic) y `Normalize`.
6. `Cross Validation` (10 folds, *stratified sampling*) con dentro: `Decision Tree` / `Naive Bayes` / `k-NN` / `Random Forest` → `Apply Model` → `Performance (Classification)` (accuracy, kappa).
7. Opcional: `Optimize Parameters (Grid)` sobre la profundidad del árbol o k.

**Segmentación**: `Read CSV (clientes_rfm.csv)` → `Select Attributes` (numéricos) → `Normalize` → `k-Means` → `Cluster Distance Performance` (Davies-Bouldin).

**Canasta**: `Read CSV (cestas_categorias.csv)` → `Numerical to Binominal` → `FP-Growth` (min support 0.03) → `Create Association Rules` (min confidence 0.2).

## 3. Power BI Desktop

*Obtener datos → Texto/CSV* con los archivos de `powerbi/` (modelo estrella):

```
dim_fecha (fecha) ──┐
dim_producto (id) ──┼──< hechos_ventas >── dim_cliente_predicciones (usuario_id)
                    │
hechos_pedidos (pedido_id, usuario_id, fecha_pedido)
```

Relaciones (1 → *): `dim_producto[id]` → `hechos_ventas[producto_id]`, `dim_cliente_predicciones[usuario_id]` → `hechos_ventas[usuario_id]` y → `hechos_pedidos[usuario_id]`, `dim_fecha[fecha]` → `hechos_ventas[fecha]`.

Medidas DAX sugeridas:

```DAX
Ventas = CALCULATE(SUM(hechos_ventas[subtotal]), hechos_ventas[estado] <> "cancelado")
Margen = CALCULATE(SUM(hechos_ventas[margen]), hechos_ventas[estado] <> "cancelado")
% Margen = DIVIDE([Margen], [Ventas])
Pedidos = DISTINCTCOUNT(hechos_ventas[pedido_id])
Ticket promedio = DIVIDE([Ventas], [Pedidos])
Clientes que recomprarán = CALCULATE(COUNTROWS(dim_cliente_predicciones), dim_cliente_predicciones[prob_recompra_7d] >= 0.5)
Prob. media de recompra = AVERAGE(dim_cliente_predicciones[prob_recompra_7d])
Demanda esperada (categoría) = COUNTROWS(FILTER(dim_cliente_predicciones, dim_cliente_predicciones[proxima_categoria] = SELECTEDVALUE(dim_producto[categoria])))
```

Páginas recomendadas del informe:
1. **Ventas**: tarjetas (Ventas, Pedidos, Ticket, % Margen), línea por semana, barras por categoría y ciudad.
2. **Clientes y segmentos**: dispersión `pc1` vs `pc2` coloreada por `segmento_nombre`; tabla RFM.
3. **Predicciones**: tabla por cliente con `proxima_categoria`, `prob_proxima_categoria`, `prob_recompra_7d`, `gasto_esperado`, `dias_sin_comprar`; formato condicional en la probabilidad; segmentador por segmento.
4. **Modelos**: `evaluacion_modelos.csv` en un gráfico de barras de Kappa / exactitud por modelo y tarea; `reglas_asociacion.csv` en una tabla.
