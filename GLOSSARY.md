# Tucu Roller

Fabricación y venta de cortinas a medida.

## Fabricación

**Orden de producción**:
La fabricación de un pedido. Hay una sola por pedido.
_Avoid_: orden de fabricación

**Unidad**:
Cada cortina física que se fabrica dentro de una orden de producción. El plan le asigna operario y tiempo, y controla su estado.
_Avoid_: producto, ítem

**Producto**:
El artículo del catálogo que se vende, por ejemplo una cortina roller. Puede tener atributos de tela y color.
_Avoid_: usarlo para la cortina física en fabricación

**Plan de producción**:
La organización de las órdenes de producción ya existentes: qué unidad hace cada operario y cuánto tiempo lleva.
_Avoid_: una segunda orden de fabricación

**Estado de la orden**:
La situación de la orden de producción completa: pendiente, en producción, terminado, entregado o cancelado.
_Avoid_: estado de la unidad

**Estado de la unidad**:
La situación de una unidad en el taller: en corte, armado, terminado, entregado o rechazado.
_Avoid_: estado de la orden

**Receta**:
La lista de materiales que un Producto consume para fabricar una Unidad.
_Avoid_: fórmula, BOM

**Condición**:
El filtro de una línea de receta. Si coincide con la Unidad (medidas o atributos elegidos), ese material se usa. Varias condiciones de una línea se cumplen todas juntas.
_Avoid_: regla

**Tela**:
El material de tela que consume una Unidad. En la receta se elige con una Condición sobre el atributo de tela (y color, si cada combinación es un material distinto).
_Avoid_: usarlo como el Producto vendido
