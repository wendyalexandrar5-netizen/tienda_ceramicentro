# CERAMISHOP – CERAMICENTRO

Tienda en línea de CERAMICENTRO: sitio web (PHP + Bootstrap), panel administrativo y app Android (Capacitor),
todo conectado al **mismo backend PHP en Apache** y a **MongoDB**.

> **Proyecto académico universitario.** No se realizan ventas, envíos ni cobros reales: el pago PSE es un simulador.
> Complete institución, programa y autores en `config/config.php` → `academico` (aparecen en el pie y en los textos legales).

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

Colecciones nuevas: `contadores`, `tokens_app`, `intentos_login`, `mensajes_contacto`, `recuperaciones_clave`. Campo nuevo en `pedidos`: `notas_admin`, `cancelado_por_vencimiento`.
Los pedidos antiguos (estado `Pagado`, sin número) se muestran como `CS-XXXXXXXX` (últimos 8 caracteres del ID).
Las contraseñas antiguas en `contraseña` o `contrasena` siguen funcionando.

**Estados de pedido**: Pendiente de pago → Pagado → En preparación → Enviado → Entregado
(o Pago rechazado / Cancelado, que devuelven el inventario automáticamente).

## 5. Flujo de compra web
Producto → Carrito → Confirmar pedido (reserva inventario) → **Simulador PSE** (aprobar/rechazar)
→ Confirmación con número de pedido → Mis pedidos → Comprobante PDF.
El simulador **no** abre sitios bancarios ni pide datos bancarios: no hay cobros reales.

## 6. Demostración sin hosting (red local)
No se necesita hosting: todo funciona en un computador con XAMPP y MongoDB, y el celular se conecta por la misma red Wi-Fi.

1. **Instalar**: XAMPP (Apache + PHP), [MongoDB Community Server](https://www.mongodb.com/try/download/community)
   (opcional: MongoDB Compass para ver los datos) y Composer.
2. **Extensión de PHP para MongoDB** (Windows): descargue el `php_mongodb.dll` que coincida con su versión de PHP
   (`php -v`: versión, «Thread Safe» y x64) desde la página de releases de *mongo-php-driver* en GitHub,
   cópielo en `C:\xampp\php\ext\` y agregue `extension=mongodb` en `C:\xampp\php\php.ini`. Reinicie Apache.
   Active también `extension=gd` (imágenes) y `extension=zip` (Excel).
3. Copie el proyecto en `C:\xampp\htdocs\tiendaonline_mongodb\`, ejecute `composer install` y
   `php scripts/crear_indices.php`. Si la base está vacía, cree el primer administrador con
   `php scripts/crear_admin_inicial.php "Su Nombre" correo@ejemplo.com`.
4. Abra `http://localhost/tiendaonline_mongodb/` en el navegador.
5. **App en el celular** (misma red Wi-Fi o el punto de acceso del celular):
   - En el PC, ejecute `ipconfig` y copie la «Dirección IPv4» (por ejemplo, `192.168.1.20`).
   - Póngala en `mobile-app/www/js/config.js` → `SERVIDOR: "http://192.168.1.20/tiendaonline_mongodb"`.
   - Permita Apache en el Firewall de Windows (redes privadas).
   - Ejecute `npx cap sync android` y compile la app en Android Studio.
   - Pruebe desde el navegador del celular `http://192.168.1.20/tiendaonline_mongodb/api/api_estado.php`:
     debe responder `"success":true`.
6. **Sin internet**: Bootstrap, los íconos y la fuente están incluidos en `assets/vendor/`, así que el sitio
   se ve completo aunque no haya conexión (solo el mapa de Google y WhatsApp necesitan internet).
7. **Sin correo configurado**: los mensajes de contacto se guardan en el panel admin y la recuperación de
   contraseña muestra el enlace en pantalla mientras `'entorno' => 'desarrollo'`.

Si más adelante necesitan mostrarlo por internet sin hosting, se puede exponer el Apache local temporalmente con un
túnel (por ejemplo, Cloudflare Tunnel o ngrok), que además da HTTPS; en ese caso use `'entorno' => 'produccion'`.

## 7. Funciones agregadas en la segunda etapa
- **Reservas que vencen**: un pedido «Pendiente de pago» reserva el inventario por `horas_reserva` (24 h por defecto);
  luego se cancela solo y el inventario vuelve. Se revisa automáticamente al navegar; también puede programarse
  `php scripts/liberar_reservas.php`.
- **Gestión de pedidos (admin)** en `admin_pedido.php`: detalle completo, cambio de estado, notas internas,
  edición de cantidades mientras está pendiente y eliminación de pedidos cancelados o rechazados.
  Los pedidos pagados no se editan para no alterar ventas.
- **¿Olvidaste tu contraseña?**: enlace temporal (1 hora, un solo uso) por correo.
- **Textos legales completos** (términos, aviso legal, privacidad según Ley 1581 de 2012, cookies).
- **Auditorías**: axe-core sin fallas de accesibilidad (WCAG 2 A/AA) en 26 páginas; Lighthouse 100 en
  accesibilidad, buenas prácticas y SEO, y 93–96 en rendimiento móvil (con compresión gzip como en Apache).

## 8. Datos de la empresa y analítica
- Datos de contacto en `config/config.php` → `empresa` (teléfono, dirección, redes sociales).
  Los enlaces de redes del pie solo aparecen si se configuran.
- Analítica: escriba el ID de Google Analytics 4 en `analitica.ga4_id`. Solo se carga si el visitante acepta cookies.
- Verifique la ubicación del mapa de `contacto.php` (el iframe existente apunta a «Ceramicentro Roosevelt»).

## 9. Pruebas
`tests/` contiene un MongoDB **simulado** (solo para pruebas, nunca se usa en producción) y pruebas E2E con Playwright:

```bash
CS_FAKE_DB=/tmp/cs_db.ser php -d auto_prepend_file=tests/fake_mongo.php tests/seed.php
CS_FAKE_DB=/tmp/cs_db.ser CS_BASE=/tiendaonline_mongodb php -S 127.0.0.1:8080 tests/router.php
node tests/e2e/web.e2e.js      # flujo web cliente + admin + exportaciones + responsive
node tests/e2e/app.e2e.js      # app (servir mobile-app/www en :8090)
node tests/e2e/extras.e2e.js   # reservas vencidas, gestión de pedidos, recuperar contraseña, legales
```
La carpeta `tests/` está bloqueada para la web por `.htaccess`.
