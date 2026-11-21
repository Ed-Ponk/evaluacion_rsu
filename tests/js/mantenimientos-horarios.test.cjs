const test = require('node:test');
const assert = require('node:assert/strict');

const escapar = (valor) => String(valor ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');

global.window = global;
global.document = { querySelector: () => ({ content: 'token-de-prueba' }) };
global.Tads = { escapar, Modulos: {}, iniciarModulos: () => {} };

require('../../public/js/tads/mantenimientos-horarios.js');

test('la edición agrega el vehículo inactivo actual como opción seleccionada', () => {
    const html = global.Tads.plantillaFormularioHorario(
        {
            id: 3,
            vehiculo_id: 7,
            vehiculo: 'Camión viejo',
            placa: 'ABC-123',
            responsable_id: 5,
            responsable: 'Técnico Fase4b',
            tipo: 'PREVENTIVO',
            weekday: 1,
            inicio: '08:00',
            fin: '10:00',
        },
        { urlRecurso: '/admin/mantenimientos/1/horarios' },
        {
            vehiculos: [{ id: 9, name: 'Camión activo', plate: 'XYZ-999' }],
            personal: [{ id: 11, name: 'Ana', last_name: null }],
            tipos: [],
            dias: [],
        }
    );

    assert.match(html, /<option value="7" selected>Camión viejo \(ABC-123\) \(inactivo\)<\/option>/);
    assert.match(html, /<option value="5" selected>Técnico Fase4b \(inactivo\)<\/option>/);
    assert.match(html, /<option value="9"\s*>Camión activo \(XYZ-999\)<\/option>/);
    assert.doesNotMatch(html, /Ana null/);
});

test('no agrega una opción inactiva cuando el valor sigue en el catálogo', () => {
    const html = global.Tads.plantillaFormularioHorario(
        {
            id: 4,
            vehiculo_id: 9,
            vehiculo: 'Camión activo',
            placa: 'XYZ-999',
            responsable_id: 11,
            responsable: 'Ana',
            tipo: 'LIMPIEZA',
            weekday: 2,
            inicio: '10:00',
            fin: '12:00',
        },
        { urlRecurso: '/admin/mantenimientos/1/horarios' },
        {
            vehiculos: [{ id: 9, name: 'Camión activo', plate: 'XYZ-999' }],
            personal: [{ id: 11, name: 'Ana', last_name: null }],
            tipos: [],
            dias: [],
        }
    );

    assert.doesNotMatch(html, /\(inactivo\)/);
    assert.match(html, /<option value="9" selected>Camión activo \(XYZ-999\)<\/option>/);
});
