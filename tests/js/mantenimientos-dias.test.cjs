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

require('../../public/js/tads/mantenimientos-dias.js');

test('el modal del día muestra fecha legible, texto No realizado y miniatura ampliable', () => {
    const html = global.Tads.plantillaFormularioDia(
        {
            id: 12,
            fecha: '2026-12-07',
            estado: 'PENDIENTE',
            observacion: 'Revisar <filtros>',
            imagen: '/uploads/dia-12.jpg',
        },
        { urlDias: '/admin/mantenimientos/1/horarios/3/dias' }
    );

    assert.match(html, /id="diaFecha"[^>]*value="07\/12\/2026"[^>]*readonly/);
    assert.match(html, /Si no lo marca, el día se guardará como No realizado\./);
    assert.match(html, /data-ampliar-imagen="\/uploads\/dia-12\.jpg"/);
    assert.match(html, /alt="Imagen actual"/);
    assert.match(html, /Revisar &lt;filtros&gt;/);
});

test('el modal conserva la fecha ISO en el campo oculto', () => {
    const html = global.Tads.plantillaFormularioDia(
        {
            id: 13,
            fecha: '2026-12-08',
            estado: 'REALIZADO',
            observacion: '',
            imagen: '/images/sin-imagen.svg',
        },
        { urlDias: '/admin/mantenimientos/1/horarios/3/dias' }
    );

    assert.match(html, /<input type="hidden" name="fecha" value="2026-12-08">/);
    assert.doesNotMatch(html, /data-ampliar-imagen="\/images\/sin-imagen\.svg"/);
});
