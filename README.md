# CERAMISHOP – CERAMICENTRO

Tienda en línea de CERAMICENTRO: sitio web (PHP + Bootstrap), panel administrativo y app Android (Capacitor),
todo conectado al **mismo backend PHP en Apache** y a **MongoDB**.

```
App Android (Capacitor/WebView) ─┐
                                 ├─ HTTP/HTTPS ─> Apache + PHP (web + /api) ─> MongoDB (27017)
Navegador web ───────────────────┘
```

## 1. Instalación (XAMPP / Apache local)

1. Copie el proyecto en la carpeta de Apache, por ejemplo `htdocs/tiendaonline_mongodb/`.
2. Habilite en PHP la extensión **mongodb** (y **gd** para optimizar imágenes).
3. Instale dependencias: `composer install`
   (MongoDB, PhpSpreadsheet para Excel, TCPDF para PDF y PHPMailer para el contacto).
4. Copie `config/config.example.php` como `config/config.php` y ajuste los valores
   (este archivo **no** se sube al repositorio). Sin `config.php` se usan los valores
   anteriores: `mongodb://localhost:27017`, base `ceramicentro_mongo`.
5. Cree los índices de MongoDB (no modifica datos, se puede repetir):
   `php scripts/crear_indices.php`
6. (Opcional) Genere versiones WebP de las imágenes existentes, sin borrar los originales:
   `php scripts/optimizar_imagenes.php`
7. Asegúrese de que `mod_rewrite` esté activo (lo usan la página 404, `sitemap.xml` y `robots.txt`).

### ⚠️ Acción requerida: contraseña del correo de contacto
La versión anterior tenía la contraseña de aplicación de Gmail escrita en `contacto.php`.
Ya no está en el código. **Revóquela y genere una nueva** en la cuenta de Google y escríbala en
`config/config.php` → `'smtp' => ['clave' => '...']`. Mientras no esté configurada, los mensajes
del formulario se guardan en MongoDB y se ven en *Panel admin → Mensajes de contacto*.

## 2. HTTPS en producción
- Instale el certificado SSL en Apache.
- Active la redirección: `'forzar_https' => true` en `config/config.php`
  (o descomente el bloque HTTPS de `.htaccess`). `localhost` y las IP de red local nunca se redirigen.
- Cuando todo funcione por HTTPS, active `'hsts' => true`.
- Las cookies de sesión se marcan `Secure` automáticamente cuando la petición llega por HTTPS
  (`HttpOnly` y `SameSite=Lax` siempre).
- Defina `'url_sitio' => 'https://su-dominio'` para que sitemap, Open Graph y JSON-LD usen la URL correcta.

## 3. App Android
- La dirección del servidor está en **un solo lugar**: `mobile-app/www/js/config.js` (`SERVIDOR`).
- Tras cambiar archivos de `www/`: `cd mobile-app && npx cap sync android` y compile en Android Studio.
- La app ahora inicia sesión con un **token** (antes cualquiera podía pedir pedidos de otro usuario
  enviando su ID). Si hay celulares con la versión anterior instalada, puede activar temporalmente
  `'api' => ['modo_legado' => true]` hasta que todos actualicen.
- En producción con HTTPS: cambie `SERVIDOR` a `https://...` y ponga
  `cleartextTrafficPermitted="false"` en `android/app/src/main/res/xml/network_security_config.xml`.

### Endpoints de la API (`/api`)
| Endpoint | Método | Uso |
|---|---|---|
| `api_login.php` | POST `{correo,password}` | Devuelve `usuario` y `token` |
| `api_registro.php` | POST `{nombre,correo,password}` | Crea cliente, devuelve `token` |
| `api_logout.php` | POST | Invalida el token |
| `api_productos.php` | GET `[?categoria=&q=]` | Lista de productos (formato compatible) |
| `api_categorias.php` | GET | Categorías |
| `api_crear_pedido.php` | POST `{carrito:[{id,cantidad}], token_cliente, pago}` | Crea el pedido (precios/stock se validan en el servidor) |
| `api_pedidos.php` | POST | Pedidos del usuario del token |
| `descargar_pedido_app.php` | GET (enlace firmado, 30 min) | Comprobante PDF |
| `api_estado.php` | GET | Diagnóstico servidor + MongoDB |

El token se envía en la cabecera `X-Auth-Token` (o `Authorization: Bearer`).

## 4. MongoDB: colecciones y compatibilidad
No se renombró ni eliminó ninguna colección o campo existente. Campos **nuevos y opcionales**:

| Colección | Campos nuevos |
|---|---|
| `pedidos` | `numero` (CS-000123), `origen`, `metodo_pago`, `pago{referencia,banco,...}`, `historial_estados[]`, `stock_devuelto`, `cliente{nombre,correo}`, `token_cliente` |
| `pedido_detalle` | `imagen` (copia para comprobantes) |
| `categorias` | `descripcion` (opcional, se muestra en el catálogo) |
| `usuarios` | `activo` (false = cuenta desactivada), `origen` |
| `historial_productos` | `categoria_anterior` / `categoria_nueva` también al editar |

Colecciones nuevas: `contadores`, `tokens_app`, `intentos_login`, `mensajes_contacto`.
Los pedidos antiguos (estado `Pagado`, sin número) se muestran como `CS-XXXXXXXX` (últimos 8 caracteres del ID).
Las contraseñas antiguas en `contraseña` o `contrasena` siguen funcionando.

**Estados de pedido**: Pendiente de pago → Pagado → En preparación → Enviado → Entregado
(o Pago rechazado / Cancelado, que devuelven el inventario automáticamente).

## 5. Flujo de compra web
Producto → Carrito → Confirmar pedido (reserva inventario) → **Simulador PSE** (aprobar/rechazar)
→ Confirmación con número de pedido → Mis pedidos → Comprobante PDF.
El simulador **no** abre sitios bancarios ni pide datos bancarios: no hay cobros reales.

## 6. Pendientes para CERAMICENTRO
- Completar los textos marcados **[PENDIENTE – CERAMICENTRO]** en `aviso_legal.php`,
  `politica_privacidad.php` y `politica_cookies.php` (revisión jurídica).
- Datos de contacto en `config/config.php` → `empresa` (teléfono, dirección, NIT, redes sociales).
  Los enlaces de redes del pie solo aparecen si se configuran (antes apuntaban a facebook.com genérico).
- Analítica: escriba el ID de Google Analytics 4 en `analitica.ga4_id`. Solo se carga si el visitante acepta cookies.
- Verifique la ubicación del mapa de `contacto.php` (el iframe existente apunta a «Ceramicentro Roosevelt»).

## 7. Pruebas
`tests/` contiene un MongoDB **simulado** (solo para pruebas, nunca se usa en producción) y pruebas E2E con Playwright:

```bash
CS_FAKE_DB=/tmp/cs_db.ser php -d auto_prepend_file=tests/fake_mongo.php tests/seed.php
CS_FAKE_DB=/tmp/cs_db.ser CS_BASE=/tiendaonline_mongodb php -S 127.0.0.1:8080 tests/router.php
node tests/e2e/web.e2e.js      # flujo web cliente + admin + exportaciones + responsive
node tests/e2e/app.e2e.js      # app (servir mobile-app/www en :8090)
```
La carpeta `tests/` está bloqueada para la web por `.htaccess`.
