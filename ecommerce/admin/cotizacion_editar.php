
<?php
// cotizacion_editar.php
// ...aquí va el código PHP inicial si corresponde...

?>
<!-- Aquí comienza el HTML y luego el JavaScript -->
<script>
// ...existing code...

                    var attrHTML = '<div class="mb-2 modal-attr-item" data-attr-id="' + attr.id + '" data-attr-nombre="' + attr.nombre + '" data-required="' + (attr.es_obligatorio ? 1 : 0) + '">' +
                        '<label class="form-label small mb-1">' + attr.nombre +
                    var attrHTML = '<div class="mb-2 modal-attr-item" data-attr-id="' + attr.id + '" data-attr-nombre="' + attr.nombre + '" data-required="' + (attr.es_obligatorio ? 1 : 0) + '">' +
                        '<label class="form-label small mb-1">' + attr.nombre +
                        (attr.costo_adicional > 0 ? '<span class="badge bg-warning text-dark">+$' + parseFloat(attr.costo_adicional).toFixed(2) + '</span>' : '') +
                        '</label>' + inputHTML +
                        '<input type="hidden" id="modal_attr_costo_' + attr.id + '" value="0" data-base="' + attr.costo_adicional + '">' +
                        '</div>';
                    atributosContainer.insertAdjacentHTML('beforeend', attrHTML);
                        const colorInput = document.getElementById(`modal_attr_${attr.id}`);
                        const preview = document.getElementById(`modal_color_preview_${attr.id}`);
                        if (colorInput && preview) {
                            const updatePreview = () => { preview.style.backgroundColor = colorInput.value || '#000000'; };
                            colorInput.addEventListener('input', updatePreview);
                            updatePreview();
                        }
                    }
                });

                if (Array.isArray(valoresExistentes) && valoresExistentes.length > 0) {
                    valoresExistentes.forEach(v => {
                        const wrapper = document.querySelector(`.modal-attr-item[data-attr-id="${v.id}"]`);
                        if (!wrapper) return;
                        const radio = wrapper.querySelector(`input[type="radio"][value="${v.valor}"]`);
                        if (radio) {
                            radio.checked = true;
                            marcarOpcionAtributo(radio);
                        }
                        const input = wrapper.querySelector(`[data-attr-valor="${v.id}"]`);
                        if (input) {
                            input.value = v.valor || '';
                        }
                        const baseInput = document.getElementById(`modal_attr_costo_${v.id}`);
                        const baseCosto = baseInput ? baseInput.dataset.base : 0;
                        actualizarCostoAtributoModal(v.id, baseCosto || 0, v.costo || 0, v.valor);
                    });
                }
            } else {
                document.getElementById('atributos-container-modal').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error cargando atributos:', error);
            document.getElementById('atributos-container-modal').style.display = 'none';
        });
}

function actualizarCostoAtributoModal(attrId, costoBase, costoOpcion, valorSeleccionado) {
    const inputCosto = document.getElementById(`modal_attr_costo_${attrId}`);
    if (!inputCosto) return;

    const base = parseFloat(costoBase || 0);
    const opcion = parseFloat(costoOpcion || 0);
    const tieneValor = valorSeleccionado !== undefined && valorSeleccionado !== null && String(valorSeleccionado).trim() !== '';

    if (!tieneValor) {
        inputCosto.value = '0';
    } else if (opcion > 0) {
        inputCosto.value = opcion.toFixed(2);
    } else if (base > 0) {
        inputCosto.value = base.toFixed(2);
    } else {
        inputCosto.value = '0';
    }
}

function actualizarPrecioItemModal() {
    const productoId = document.getElementById('producto_id_modal')?.value;
    if (!productoId) return;

    const producto = productos.find(p => String(p.id) === String(productoId));
    if (producto?.tipo_precio === 'variable') {
        const ancho = parseFloat(document.getElementById('ancho_modal').value || 0);
        const alto = parseFloat(document.getElementById('alto_modal').value || 0);

        if (ancho > 0 && alto > 0) {
            fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    const precioBase = parseFloat(data.precio || 0);
                    const precioInput = document.getElementById('precio_modal');
                    precioInput.dataset.base = precioBase.toFixed(2);
                    precioInput.value = precioBase.toFixed(2);
                    const info = document.getElementById('precio-info-modal');
                    if (info) {
                        info.innerHTML = '✓ ' + data.precio_info;
                        info.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    }
}

function actualizarBasePrecioModal() {
    const precioInput = document.getElementById('precio_modal');
    if (!precioInput) return;
    precioInput.dataset.base = precioInput.value || '';
}

function obtenerItemDesdeDOM(index) {
    const row = document.getElementById(`item_${index}`);
    if (!row) return null;
    const getVal = (id) => document.getElementById(id)?.value || '';
    const productoId = getVal(`producto_id_${index}`);
    const producto = productos.find(p => String(p.id) === String(productoId));
    const atributos = [];

    row.querySelectorAll(`input[name^="items[${index}][atributos]"][name$="[nombre]"]`).forEach(input => {
        const match = input.name.match(/atributos\]\[(\d+)\]\[nombre\]/);
        if (!match) return;
        const attrId = match[1];
        const valorInput = row.querySelector(`input[name="items[${index}][atributos][${attrId}][valor]"]`);
        const costoInput = row.querySelector(`input[name="items[${index}][atributos][${attrId}][costo]"]`);
        const valor = valorInput?.value || '';
        const costo = parseFloat(costoInput?.value || 0) || 0;
        atributos.push({ id: attrId, nombre: input.value, valor, costo });
    });

    return {
        producto_id: productoId,
        productoLabel: producto ? productoLabel(producto) : '',
        nombre: getVal(`nombre_${index}`),
        descripcion: getVal(`descripcion_${index}`),
        ancho: getVal(`ancho_${index}`),
        alto: getVal(`alto_${index}`),
        cantidad: getVal(`cantidad_${index}`),
        precio: getVal(`precio_${index}`),
        atributos
    };
}

function obtenerAtributosDesdeModal() {
    const atributos = [];
    let faltan = false;

    document.querySelectorAll('#atributos-list-modal .modal-attr-item').forEach(wrapper => {
        const attrId = wrapper.dataset.attrId;
        const nombre = wrapper.dataset.attrNombre || '';
        const requerido = wrapper.dataset.required === '1';
        let valor = '';

        const radio = wrapper.querySelector('input[type="radio"]:checked');
        if (radio) {
            valor = radio.value;
        } else {
            const input = wrapper.querySelector(`[data-attr-valor="${attrId}"]`);
            if (input) valor = input.value;
        }

        if (requerido && !valor) {
            faltan = true;
        }

        const costoInput = document.getElementById(`modal_attr_costo_${attrId}`);
        const costo = parseFloat(costoInput?.value || 0) || 0;

        if (valor) {
            atributos.push({ id: attrId, nombre, valor, costo });
        }
    });

    if (faltan) {
        alert('Completa los atributos obligatorios antes de guardar.');
        return null;
    }

    return atributos;
}

function renderItemResumen(index, itemData) {
    const atributos = itemData.atributos || [];
    const atributosResumen = atributos.length
        ? atributos.map(function(a) {
            return '<span class="badge bg-light text-dark me-1">' + a.nombre + ': ' + a.valor + (a.costo > 0 ? ' (+$' + parseFloat(a.costo).toFixed(2) + ')' : '') + '</span>';
        }).join('')
        : '<span class="text-muted">Sin atributos</span>';

    const dimensiones = (itemData.ancho && itemData.alto)
        ? itemData.ancho + ' x ' + itemData.alto + ' cm'
        : '—';

    var html = '<div class="card mb-3 item-row" id="item_' + index + '">' +
        '<div class="card-body">' +
            '<div class="d-flex justify-content-between align-items-start flex-wrap gap-3">' +
                '<div class="flex-grow-1">' +
                    '<div class="item-resumen-title">' + (itemData.nombre || 'Producto sin nombre') + '</div>' +
                    (itemData.descripcion ? '<div class="item-resumen-meta">' + itemData.descripcion + '</div>' : '') +
                    '<div class="item-resumen-meta">Cantidad: <strong>' + itemData.cantidad + '</strong> · Medidas: <strong>' + dimensiones + '</strong></div>' +
                    '<div class="item-resumen-meta">Precio base: <strong>$' + parseFloat(itemData.precio || 0).toFixed(2) + '</strong></div>' +
                    '<div class="item-resumen-attrs mt-2">' + atributosResumen + '</div>' +
                '</div>' +
                '<div class="text-end">' +
                    '<div class="badge bg-primary-subtle text-primary border" style="font-size: 0.95rem;">' +
                        'Subtotal: $<span class="item-subtotal-text" id="item_subtotal_text_' + index + '">0.00</span>' +
                    '</div>' +
                    '<div class="mt-2">' +
                        '<button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalItem(' + index + ')">✏️ Editar</button>' +
                        '<button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem(' + index + ')">🗑️ Eliminar</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<input type="hidden" class="item-nombre" id="nombre_' + index + '" name="items[' + index + '][nombre]" value="' + (itemData.nombre || '') + '">' +
            '<input type="hidden" id="descripcion_' + index + '" name="items[' + index + '][descripcion]" value="' + (itemData.descripcion || '') + '">' +
            '<input type="hidden" class="item-ancho" id="ancho_' + index + '" name="items[' + index + '][ancho]" value="' + (itemData.ancho || '') + '">' +
            '<input type="hidden" class="item-alto" id="alto_' + index + '" name="items[' + index + '][alto]" value="' + (itemData.alto || '') + '">' +
            '<input type="hidden" class="item-cantidad" id="cantidad_' + index + '" name="items[' + index + '][cantidad]" value="' + (itemData.cantidad || 1) + '">' +
            '<input type="hidden" class="item-precio" id="precio_' + index + '" name="items[' + index + '][precio]" value="' + (itemData.precio || 0) + '" data-base="' + (itemData.precio || 0) + '">' +
            '<input type="hidden" id="producto_id_' + index + '" name="items[' + index + '][producto_id]" value="' + (itemData.producto_id || '') + '">' +
            '<input type="text" class="form-control item-subtotal" id="subtotal_' + index + '" readonly style="display:none;">' +
            '<div id="precio-info-' + index + '" style="display:none;"></div>' +
            atributos.map(function(a) {
                return '<input type="hidden" name="items[' + index + '][atributos][' + a.id + '][nombre]" value="' + a.nombre + '">' +
                    '<input type="hidden" name="items[' + index + '][atributos][' + a.id + '][valor]" value="' + a.valor + '">' +
                    '<input type="hidden" name="items[' + index + '][atributos][' + a.id + '][costo]" value="' + parseFloat(a.costo || 0).toFixed(2) + '">';
            }).join('') +
        '</div>' +
    '</div>';
    return html;
}

function guardarItemDesdeModal() {
    const form = document.getElementById('itemModalForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const atributos = obtenerAtributosDesdeModal();
    if (atributos === null) return;

    const itemData = {
        producto_id: document.getElementById('producto_id_modal').value || '',
        nombre: document.getElementById('nombre_modal').value.trim(),
        descripcion: document.getElementById('descripcion_modal').value.trim(),
        ancho: document.getElementById('ancho_modal').value,
        alto: document.getElementById('alto_modal').value,
        cantidad: document.getElementById('cantidad_modal').value,
        precio: document.getElementById('precio_modal').value,
        atributos
    };

    if (!itemData.nombre || !itemData.cantidad || !itemData.precio) {
        alert('Completa los campos obligatorios del item.');
        return;
    }

    let index = modalEditIndex;
    if (!index) {
        itemIndex++;
        index = itemIndex;
        const html = renderItemResumen(index, itemData);
        document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
    } else {
        const html = renderItemResumen(index, itemData);
        const row = document.getElementById(`item_${index}`);
        if (row) {
            row.outerHTML = html;
        }
    }

    calcularTotales();
    modalEditIndex = null;
    const modal = bootstrap.Modal.getInstance(document.getElementById('itemModal'));
    try {
        const modalEl = document.getElementById('itemModal');
        const active = document.activeElement;
        if (active && modalEl && modalEl.contains(active)) {
            const prev = modalEl._previouslyFocused || window.__lastFocusedBeforeModal;
            if (prev && typeof prev.focus === 'function') prev.focus(); else active.blur();
        }
    } catch(e){}
    if (modal) modal.hide();
}

function eliminarItem(index) {
    document.getElementById('item_' + index)?.remove();
    calcularTotales();
}

function calcularTotales() {
    let subtotal = 0;
    let descuentoListaTotal = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const cantidad = parseFloat(row.querySelector('.item-cantidad')?.value || 0);
        const precioInput = row.querySelector('.item-precio');
        if (precioInput && (precioInput.dataset.base === undefined || precioInput.dataset.base === '')) {
            precioInput.dataset.base = precioInput.value || '';
        }
        const precioBase = parseFloat(precioInput?.dataset.base || 0) || parseFloat(precioInput?.value || 0);
        let costoAtributos = 0;
        row.querySelectorAll('input[name*="[atributos]"][name$="[costo]"]').forEach(input => {
            costoAtributos += parseFloat(input.value || 0);
        });

        const subtotalItem = cantidad * (precioBase + costoAtributos);

        const subtotalInput = row.querySelector('.item-subtotal');
        if (subtotalInput) {
            subtotalInput.value = subtotalItem.toFixed(2);
        }
        const subtotalText = row.querySelector('.item-subtotal-text');
        if (subtotalText) {
            subtotalText.textContent = subtotalItem.toFixed(2);
        }

        subtotal += subtotalItem;

        const productoId = row.querySelector('input[type="hidden"][id^="producto_id_"]')?.value;
        if (productoId) {
            const precioLista = calcularPrecioConLista(productoId, precioBase);
            const descUnit = Math.max(0, precioBase - precioLista);
            descuentoListaTotal += descUnit * cantidad;
        }
    });

    const listaId = obtenerListaSeleccionada();
    const descuentoInput = document.getElementById('descuento');
    const descuentoInfo = document.getElementById('descuento_lista_info');

    if (listaId && descuentoInput) {
        descuentoInput.value = descuentoListaTotal.toFixed(2);
        if (descuentoInfo) {
            descuentoInfo.textContent = `Base: $${subtotal.toFixed(2)} | Descuento lista: $${descuentoListaTotal.toFixed(2)}`;
        }
    } else if (descuentoInfo) {
        descuentoInfo.textContent = '';
    }

    const descuento = parseFloat(descuentoInput?.value || 0);
    const descuentoCupon = parseFloat(document.getElementById('cupon_descuento')?.value || 0);
    const total = subtotal - descuento - descuentoCupon;

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
}

function aplicarCupon() {
    const codigo = document.getElementById('cupon_codigo')?.value?.trim() || '';
    const info = document.getElementById('cupon_info');
    const descuentoInput = document.getElementById('cupon_descuento');
    const subtotalText = document.getElementById('subtotal')?.textContent || '$0';
    const subtotal = parseFloat(subtotalText.replace(/[^0-9.]/g, '')) || 0;

    if (!codigo) {
        if (info) info.textContent = 'Ingresá un cupón.';
        if (descuentoInput) descuentoInput.value = '0';
        calcularTotales();
        return;
    }

    fetch(`cupones_validar.php?codigo=${encodeURIComponent(codigo)}&subtotal=${subtotal}`)
        .then(r => r.json())
        .then(data => {
            if (!data.valido) {
                if (info) info.textContent = data.mensaje || 'Cupón inválido.';
                if (descuentoInput) descuentoInput.value = '0';
            } else {
                if (descuentoInput) descuentoInput.value = data.descuento || 0;
                if (info) info.textContent = data.mensaje
                    ? `${data.mensaje} (-$${Number(data.descuento || 0).toFixed(2)})`
                    : `Descuento aplicado: $${Number(data.descuento || 0).toFixed(2)}`;
            }
            calcularTotales();
        })
        .catch(() => {
            if (info) info.textContent = 'No se pudo validar el cupón.';
        });
}

function marcarOpcionAtributo(radio) {
    if (!radio || !radio.name) return;
    const label = radio.closest('label');
    if (!label) return;
    const divPadre = label.parentElement;
    if (!divPadre || !divPadre.parentElement) return;
    const contenedorOpciones = divPadre.parentElement;

    contenedorOpciones.querySelectorAll('input[type="radio"][name="' + radio.name + '"]').forEach(r => {
        const l = r.closest('label');
        if (l) {
            const divOpcion = l.querySelector('.attr-option');
            if (divOpcion) {
                divOpcion.style.borderColor = '#ddd';
                divOpcion.style.boxShadow = 'none';
                divOpcion.style.background = '#fff';
            }
        }
    });

    if (radio.checked) {
        const divOpcion = label.querySelector('.attr-option');
        if (divOpcion) {
            divOpcion.style.borderColor = '#0d6efd';
            divOpcion.style.boxShadow = '0 0 0 2px rgba(13,110,253,.2)';
            divOpcion.style.background = '#e7f1ff';
        }
    }
}

function aplicarListaPrecios() {
    calcularTotales();
}

function obtenerIndexDesdeRow(row) {
    const id = row?.id || '';
    const match = id.match(/item_(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

function actualizarPreciosCotizacion() {
    const filas = Array.from(document.querySelectorAll('.item-row'));
    if (filas.length === 0) {
        return;
    }

    const tareas = filas.map(row => {
        const index = obtenerIndexDesdeRow(row);
        if (!index) return Promise.resolve();

        const productoId = document.getElementById(`producto_id_${index}`)?.value;
        if (!productoId) return Promise.resolve();

        const producto = productos.find(p => String(p.id) === String(productoId));
        if (!producto) return Promise.resolve();

        if (producto.tipo_precio === 'fijo') {
            const precioBase = parseFloat(producto.precio_base || 0);
            const precioInput = document.getElementById(`precio_${index}`);
            if (precioInput) {
                precioInput.dataset.base = precioBase.toFixed(2);
                precioInput.value = precioBase.toFixed(2);
            }
            const info = document.getElementById(`precio-info-${index}`);
            if (info) {
                info.innerHTML = '✓ Precio fijo actualizado';
                info.style.display = 'block';
            }
            return Promise.resolve();
        }

        const ancho = parseFloat(document.getElementById(`ancho_${index}`)?.value || 0);
        const alto = parseFloat(document.getElementById(`alto_${index}`)?.value || 0);
        if (ancho <= 0 || alto <= 0) {
            const info = document.getElementById(`precio-info-${index}`);
            if (info) {
                info.innerHTML = '⚠️ Ingrese ancho y alto para actualizar precio';
                info.style.display = 'block';
            }
            return Promise.resolve();
        }

        return fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    return;
                }
                const precioBase = parseFloat(data.precio || 0);
                const precioInput = document.getElementById(`precio_${index}`);
                if (precioInput) {
                    precioInput.dataset.base = precioBase.toFixed(2);
                    precioInput.value = precioBase.toFixed(2);
                }
                const info = document.getElementById(`precio-info-${index}`);
                if (info) {
                    info.innerHTML = '✓ ' + data.precio_info + ' (actualizado)';
                    info.style.display = 'block';
                }
            })
            .catch(() => {});
    });

    Promise.all(tareas).then(() => {
        calcularTotales();
    });
}

document.addEventListener('change', function(e) {
    const radio = e.target;
    if (radio && radio.matches('input[type="radio"].attr-radio')) {
        marcarOpcionAtributo(radio);
    }
});


// Cargar items existentes al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    if (Array.isArray(itemsExistentes) && itemsExistentes.length > 0) {
        itemsExistentes.forEach(function(item) {
            agregarItem(item);
        });
    }
    aplicarListaPrecios();
});
</script>

<?php require 'includes/footer.php'; ?>
__COTIZACION_EDITAR_TRASH__;
