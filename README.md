# 🍕 Sistema de Gestión y Envío Automático de Comandas – Pizzería Sebastiani

## 📌 Descripción del Proyecto

Este proyecto consiste en el desarrollo de un sistema de gestión de comandas para la pizzería **Sebastiani**, orientado a optimizar y automatizar el flujo de atención en el restaurante.

El sistema permite que el mesero registre los pedidos desde una interfaz digital y que estos se impriman automáticamente como tickets en cocina, reduciendo tiempos de espera, errores manuales y mejorando la comunicación entre el área de atención y producción.

Además, las comandas quedan almacenadas como órdenes dentro del sistema para que posteriormente puedan ser procesadas por caja y utilizadas para la generación de comprobantes mediante un sistema externo.

---

# 🎯 Objetivos

- Automatizar el envío de comandas a cocina.
- Reducir errores humanos en el registro de pedidos.
- Mejorar los tiempos de atención.
- Centralizar la gestión de órdenes y mesas.
- Facilitar el control de ventas desde caja.
- Permitir la impresión automática de tickets térmicos.

---

# ⚙️ Tecnologías Utilizadas

- PHP
- MySQL
- HTML5
- CSS3
- JavaScript
- Tailwind CSS
- Impresión térmica tipo rollo

---

# 🗂️ Estructura General del Proyecto

## 📁 conexion.php
Permite establecer la conexión con la base de datos para ser utilizada por los demás módulos del sistema.

---

## 📁 index.php
Módulo de autenticación de usuarios.

Funciones:
- Verificación de usuario y contraseña.
- Validación contra la base de datos.
- Limitador de 5 intentos fallidos.
- Bloqueo temporal mediante temporizador de seguridad.

---

## 📁 logout.php
Destruye la sesión activa del usuario y finaliza el acceso al sistema.

---

# 🍽️ Gestión de Mesas y Órdenes

## 📁 mesas.php
Consulta y muestra las mesas registradas en la base de datos.

Funciones:
- Mostrar número de mesa.
- Mostrar estado de la mesa.
- Mostrar precio acumulado.
- Acceder a una orden abierta.
- Crear nuevas órdenes.

---

## 📁 orden.php
Módulo principal para la gestión de órdenes.

Funciones:
- Crear nuevas órdenes.
- Visualizar órdenes abiertas.
- Modificar pedidos existentes.
- Separar pedidos impresos y no impresos.
- Mostrar nombres, precios y detalles adicionales.
- Agregar nuevos productos.
- Modificar pedidos.
- Aplicar modificaciones al precio final.
- Mover órdenes entre mesas.
- Imprimir pedidos pendientes.
- Crear nuevas órdenes.
- Cancelar órdenes.

Este módulo trabaja junto con otros módulos externos ubicados dentro del sistema.

---

## 📁 obtener_pedido.php
Consulta los pedidos asociados a una orden y envía la información a `orden.php`.

---

# 🍕 Gestión de Productos y Pedidos

## 📁 carta.php
Consulta y muestra todos los productos registrados.

Funciones:
- Mostrar nombre del producto.
- Mostrar imagen del producto.
- Mostrar descripción.
- Mostrar precio.
- Seleccionar productos para agregarlos a una orden.

---

## 📁 guardar_pedido.php
Registra un nuevo pedido dentro de una orden utilizando la información enviada desde `carta.php`.

---

## 📁 actualizar_pedido.php
Permite modificar los datos de un pedido ya registrado dentro de una orden.

---

## 📁 eliminar_pedido.php
Elimina un pedido seleccionado de la base de datos.

---

# 🧾 Gestión de Órdenes

## 📁 cancelar_orden.php
Cambia el estado de una orden a **Cancelada**.

---

## 📁 cerrar_y_nueva_orden.php
Cierra la orden actual cambiando su estado a **Pendiente** y genera automáticamente una nueva orden abierta para la misma mesa.

---

## 📁 confirmar_pedido.php
Marca un pedido como impreso cambiando el atributo `impreso` a `1`.

---

## 📁 mover_orden.php
Permite mover una orden abierta hacia otra mesa disponible.

---

# 🖨️ Sistema de Impresión

## 📁 ticket_cocina.php
Genera tickets de cocina en formato compatible con impresoras térmicas tipo boleta.

Funciones:
- Generación automática de tickets.
- Formato optimizado para rollo térmico.
- Impresión de pedidos pendientes.

---

# 💲 Modificaciones de Precio

## 📁 guardar_modificacion.php
Agrega modificaciones o cargos adicionales a una orden.

---

## 📁 eliminar_modificacion.php
Elimina modificaciones previamente registradas dentro de la orden.

---

# 💰 Módulo de Caja

## 📁 caja.php
Panel principal de caja.

Funciones:
- Visualizar todas las órdenes.
- Filtrar órdenes por estado.
- Acceder al módulo de productos.

---

## 📁 cargar_ordenes.php
Carga dinámicamente las órdenes y sus estilos visuales para mostrarlas en `caja.php`.

---

# 📦 Gestión de Productos

## 📁 productos.php
Permite administrar los productos del sistema.

Funciones:
- Consultar productos.
- Crear nuevos productos.
- Editar productos existentes.
- Eliminar productos.

---

# 🔐 Características del Sistema

- Sistema de autenticación segura.
- Control de sesiones.
- Bloqueo por intentos fallidos.
- Gestión dinámica de mesas.
- Gestión completa de órdenes.
- Impresión automática de comandas.
- Panel de caja integrado.
- Administración de productos.
- Interfaz visual personalizada.

---

# 🚀 Flujo General del Sistema

1. El usuario inicia sesión.
2. El mesero selecciona una mesa.
3. Se crea o abre una orden.
4. Se agregan productos desde la carta.
5. Los pedidos pendientes se imprimen automáticamente en cocina.
6. La orden queda registrada en el sistema.
7. Caja procesa la orden para generar la venta y comprobantes.

---

# 📚 Estado del Proyecto

Proyecto en desarrollo orientado a la automatización de procesos internos para restaurantes y pizzerías.

---

# 👨‍💻 Autor

Desarrollado para la pizzería **Sebastiani** como solución de automatización y gestión de comandas.
