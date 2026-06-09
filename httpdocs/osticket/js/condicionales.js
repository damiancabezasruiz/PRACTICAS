(function () {
    'use strict';

    // --- 1. CONFIGURACIÓN DE PLANES Y SECTORES ---
    const CONFIG_SECTORES = {
        'ASTURIAS': ['C-2024 (empleados)', 'NC-2024/25', 'NC-2024/2025'],
        'CASTILLA Y LEÓN': ['FC CYL 2025'],
        'C. VALENCIANA': ['AGROALIMENTARIA 2025', 'CERÁMICA 2025', 'CONSTRUCCIÓN 2025', 'METAL 2025'],
        'COMUNIDAD VALENCIANA': ['AGROALIMENTARIA 2025', 'CERÁMICA 2025', 'CONSTRUCCIÓN 2025', 'METAL 2025'],
        'ESTATAL': ['COMERCIO 2024', 'SANIDAD SECTORIAL 2024', 'SANIDAD ESPECIAL INTERÉS 2024', 'SERVICIOS OTROS 2024']
    };

    const PLANES_A_OCULTAR = ['BONIFICADA 2026', 'FORCAREM', 'CASTILLA LA MANCHA'];
    const TEXTO_SECTOR_DEFECTO = '— Selecciona el sector —';

    if (!window.backupSectoresTotal) {
        window.backupSectoresTotal = [];
    }

    if (!window.backupIncidenciasTotal) {
        window.backupIncidenciasTotal = [];
    }

    function normalizar(txt) {
        return (txt || '')
            .toUpperCase()
            .replace(/\*/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    // Función para encontrar la fila (tr) según el texto del label
    function buscarBloque(texto) {
        const buscado = normalizar(texto);
        const etiquetas = Array.from(document.querySelectorAll('label, b, strong, span, .control-label'));

        const encontrada = etiquetas.find(e => {
            const txt = normalizar(e.innerText || e.textContent || '');
            return txt === buscado || txt.startsWith(buscado);
        });

        return encontrada ? encontrada.closest('tr') : null;
    }

    function agregarOpcionPorDefecto(sSector) {
        sSector.add(new Option(TEXTO_SECTOR_DEFECTO, ''));
    }

    function restaurarSectoresOriginales(sSector) {
        if (!sSector || window.backupSectoresTotal.length === 0) return;

        const valorActual = sSector.value;
        sSector.innerHTML = '';

        // Primera opción fija
        agregarOpcionPorDefecto(sSector);

        window.backupSectoresTotal.forEach(opt => {
            // Evitar duplicar una opción vacía si ya existía en backup
            if (opt.v !== '') {
                sSector.add(new Option(opt.t, opt.v));
            }
        });

        const existe = window.backupSectoresTotal.some(opt => opt.v === valorActual && opt.v !== '');
        if (existe) {
            sSector.value = valorActual;
        } else {
            sSector.value = '';
            sSector.selectedIndex = 0;
        }
    }

    function filtrarSectores(sSector, permitidos) {
        if (!sSector || window.backupSectoresTotal.length === 0) return;

        const valorActual = sSector.value;
        sSector.innerHTML = '';

        // Primera opción fija
        agregarOpcionPorDefecto(sSector);

        const filtrados = window.backupSectoresTotal.filter(opt =>
            opt.v !== '' &&
            permitidos.some(p => normalizar(opt.t).includes(normalizar(p)))
        );

        filtrados.forEach(opt => {
            sSector.add(new Option(opt.t, opt.v));
        });

        const existe = filtrados.some(opt => opt.v === valorActual);
        if (existe) {
            sSector.value = valorActual;
        } else {
            sSector.value = '';
            sSector.selectedIndex = 0;
        }
    }

    function aplicarLogicaCompleta() {
        // --- PARTE 0: FILTRAR INCIDENCIAS SEGÚN EL PLAN ---
        const bPlan0 = buscarBloque('PLAN (DISPONIBLE EN EL ERP)') || buscarBloque('PLAN');
        const bInc0  = buscarBloque('INCIDENCIA');

        if (bPlan0 && bInc0) {
            const sPlan0 = bPlan0.querySelector('select');
            const sInc0  = bInc0.querySelector('select');

            if (sPlan0 && sInc0) {
                // Guardar backup de incidencias una sola vez (con TODAS las opciones originales del DOM)
                if (window.backupIncidenciasTotal.length === 0 && sInc0.options.length > 1) {
                    window.backupIncidenciasTotal = Array.from(sInc0.options).map(o => ({
                        v: o.value,
                        t: o.text.trim()
                    }));
                }

                // Si el backup no tiene NO AVANZA pero el DOM sí, rehacer el backup ahora
                const tienePruebaDOM    = Array.from(sInc0.options).some(o => normalizar(o.text) === 'NO AVANZA');
                const tienePruebaBackup = window.backupIncidenciasTotal.some(o => normalizar(o.t) === 'NO AVANZA');
                if (tienePruebaDOM && !tienePruebaBackup) {
                    window.backupIncidenciasTotal = Array.from(sInc0.options).map(o => ({
                        v: o.value,
                        t: o.text.trim()
                    }));
                }

                const planActivo0 = normalizar(sPlan0.options[sPlan0.selectedIndex]?.text || '');

                if (planActivo0 === 'BONIFICADA 2026') {
                    // Mostrar TODAS las opciones (incluyendo PRUEBA)
                    const valorActual0 = sInc0.value;
                    sInc0.innerHTML = '';
                    window.backupIncidenciasTotal.forEach(opt => {
                        sInc0.add(new Option(opt.t, opt.v));
                    });
                    const existeVal0 = window.backupIncidenciasTotal.some(o => o.v === valorActual0 && o.v !== '');
                    sInc0.value = existeVal0 ? valorActual0 : '';
                    if (!existeVal0) sInc0.selectedIndex = 0;

                } else if (window.backupIncidenciasTotal.length > 0) {
                    // Mostrar todas las opciones EXCEPTO PRUEBA
                    const valorActual0 = sInc0.value;
                    sInc0.innerHTML = '';
                    window.backupIncidenciasTotal.forEach(opt => {
                        if (normalizar(opt.t) !== 'NO AVANZA') {
                            sInc0.add(new Option(opt.t, opt.v));
                        }
                    });
                    const existeVal0 = Array.from(sInc0.options).some(o => o.value === valorActual0 && o.value !== '');
                    sInc0.value = existeVal0 ? valorActual0 : '';
                    if (!existeVal0) sInc0.selectedIndex = 0;
                }
            }
        }

        // --- PARTE A: SECTOR ---
        const bPlan = buscarBloque('PLAN (DISPONIBLE EN EL ERP)');
        const bSector = buscarBloque('SECTOR');

        if (bPlan && bSector) {
            const sPlan = bPlan.querySelector('select');
            const sSector = bSector.querySelector('select');

            if (sPlan && sSector) {
                // Guardar backup una sola vez
                if (window.backupSectoresTotal.length === 0 && sSector.options.length > 0) {
                    window.backupSectoresTotal = Array.from(sSector.options).map(o => ({
                        v: o.value,
                        t: o.text.trim()
                    }));
                }

                const planActivo = normalizar(
                    sPlan.options[sPlan.selectedIndex]?.text || ''
                );

                // IMPORTANTE: siempre quitar ocultación antes de evaluar
                bSector.classList.remove('mantener-hueco');

                if (PLANES_A_OCULTAR.includes(planActivo)) {
                    restaurarSectoresOriginales(sSector);
                    sSector.value = '';
                    sSector.selectedIndex = 0;
                    bSector.classList.add('mantener-hueco');
                } else {
                    const permitidos = CONFIG_SECTORES[planActivo];

                    if (permitidos && permitidos.length > 0) {
                        filtrarSectores(sSector, permitidos);
                    } else {
                        restaurarSectoresOriginales(sSector);
                    }
                }
            }
        }

        // --- PARTE B: CASCADA DE INCIDENCIAS ---
        const bInc = buscarBloque('INCIDENCIA');
        const bDetNoQuiere = buscarBloque('DETALLES POR LOS QUE NO QUIERE');
        const bDetDatosErr = buscarBloque('DETALLES DATOS ERRONEOS');
        const bMotivosDificultad = buscarBloque('MOTIVOS DE LA DIFICULTAD');
        const bRazon = buscarBloque('RAZÓN');

        [bDetNoQuiere, bDetDatosErr, bMotivosDificultad, bRazon].forEach(b => {
            if (b) b.classList.add('ocultar-total');
        });

        if (bInc) {
            const selInc = bInc.querySelector('select');
            const valInc = normalizar(selInc?.options[selInc.selectedIndex]?.text || '');

            if (valInc.includes('NO QUIERE HACER ESTE CURSO')) {
                if (bDetNoQuiere) bDetNoQuiere.classList.remove('ocultar-total');

                const selDet = bDetNoQuiere?.querySelector('select');
                const valDet = normalizar(selDet?.options[selDet.selectedIndex]?.text || '');

                if (valDet.includes('DIFICULTADES DEL ALUMNO')) {
                    if (bMotivosDificultad) bMotivosDificultad.classList.remove('ocultar-total');
                } else if (valDet.includes('NO ES LO QUE ESPERABA')) {
                    if (bRazon) bRazon.classList.remove('ocultar-total');
                }
            } else if (valInc.includes('DATOS ERRONEOS') || valInc.includes('DATOS PERSONALES')) {
                if (bDetDatosErr) bDetDatosErr.classList.remove('ocultar-total');
            }
        }

        // --- PARTE D: PRINCIPALES DIFICULTADES DETECTADAS → MOTIVOS ---
        const bDificultades = buscarBloque('PRINCIPALES DIFICULTADES DETECTADAS');
        const bMotivos = buscarBloque('MOTIVOS');

        if (bDificultades && bMotivos) {
            const selDif = bDificultades.querySelector('select');
            const valDif = normalizar(selDif?.options[selDif.selectedIndex]?.text || '');

            if (valDif === 'OTRAS') {
                bMotivos.classList.remove('ocultar-total');
            } else {
                bMotivos.classList.add('ocultar-total');
            }
        }

        // --- PARTE E: ¿SE HAN DETECTADO INCIDENCIAS? → EN CASO AFIRMATIVO, DESCRIBIR ---
        const bIncDetectadas = buscarBloque('¿SE HAN DETECTADO INCIDENCIAS IMPORTANTES DURANTE EL DESARROLLO DEL CURSO?')
                            || buscarBloque('SE HAN DETECTADO INCIDENCIAS IMPORTANTES DURANTE EL DESARROLLO DEL CURSO');
        const bEnCasoAfirmativo = buscarBloque('EN CASO AFIRMATIVO, DESCRIBIR');

        if (bIncDetectadas && bEnCasoAfirmativo) {
            const selInc2 = bIncDetectadas.querySelector('select');
            const valInc2 = normalizar(selInc2?.options[selInc2.selectedIndex]?.text || '');

            if (valInc2 === 'SI' || valInc2 === 'SÍ') {
                bEnCasoAfirmativo.classList.remove('ocultar-total');
            } else {
                bEnCasoAfirmativo.classList.add('ocultar-total');
            }
        }

        // --- PARTE C: LIMPIEZA VISUAL ---
        document.querySelectorAll('.uploads img, .file-upload img').forEach(img => {
            img.style.display = 'none';
        });
    }

    document.addEventListener('change', function () {
        try { aplicarLogicaCompleta(); } catch (e) {}
    }, true);

    setInterval(() => {
        try { aplicarLogicaCompleta(); } catch (e) {}
    }, 600);
	// --- LÓGICA UNIVERSAL PARA PLAN Y SECTOR ---
function ejecutarLogicaPlanSector() {
    // Busca 'PLAN' con el nombre largo o el corto del nuevo cuestionario
    const bPlan = buscarBloque('PLAN (DISPONIBLE EN EL ERP)') || buscarBloque('PLAN');
    const bSector = buscarBloque('SECTOR');

    if (bPlan && bSector) {
        const sPlan = bPlan.querySelector('select');
        const sSector = bSector.querySelector('select');

        if (sPlan && sSector) {
            // Guardar backup de sectores si aún no existe
            if (window.backupSectoresTotal.length === 0 && sSector.options.length > 1) {
                window.backupSectoresTotal = Array.from(sSector.options)
                    .filter(o => o.value !== '')
                    .map(o => ({ v: o.value, t: o.text.trim() }));
            }

            const planActivo = normalizar(sPlan.options[sPlan.selectedIndex]?.text || '');

            if (PLANES_A_OCULTAR.includes(planActivo)) {
                // Mantiene el hueco en blanco sin desplazar los campos de abajo
                bSector.style.visibility = 'hidden';
                sSector.value = '';
                sSector.selectedIndex = 0;
            } else {
                bSector.style.visibility = 'visible';
                const permitidos = CONFIG_SECTORES[planActivo];
                if (permitidos) {
                    filtrarSectores(sSector, permitidos);
                } else {
                    restaurarSectoresOriginales(sSector);
                }
            }
        }
    }
}

// Ejecución continua para asegurar que funcione en ambos formularios
setInterval(ejecutarLogicaPlanSector, 600);

})();