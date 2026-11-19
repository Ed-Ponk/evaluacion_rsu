import React, { useEffect, useState } from 'react';
import { Text, View } from 'react-native';
import { api, hora } from './api';
import { Boton, Pantalla, estilos } from './ui';

const estados = {
    programado: 'Programado', reprogramado: 'Reprogramado', en_ruta: 'En ruta',
    completado: 'Recorrido completado', sin_confirmacion: 'Sin confirmación de recorrido',
};

export default function Ciudadania() {
    const [zona, elegirZona] = useState(null);
    const [respuesta, guardar] = useState(null);
    const [error, guardarError] = useState('');
    const [revision, actualizar] = useState(0);

    useEffect(() => {
        let vigente = true;
        let ocupado = false;
        async function consultar() {
            if (ocupado) return;
            ocupado = true;
            try {
                const datos = await api('v1/recoleccion' + (zona ? '?zona=' + zona.id : ''));
                if (vigente) {
                    guardar({ ...datos, zona: zona?.id });
                    guardarError('');
                }
            } catch (fallo) {
                if (vigente) guardarError(fallo.status ? fallo.message : 'No se pudo consultar el servidor. Comprueba tu conexión.');
            } finally {
                ocupado = false;
            }
        }
        consultar();
        const intervalo = zona ? setInterval(consultar, 30000) : null;
        return () => {
            vigente = false;
            clearInterval(intervalo);
        };
    }, [zona, revision]);

    const actual = respuesta?.zona === zona?.id ? respuesta : null;
    return (
        <Pantalla titulo="Recolección por zona" subtitulo="Consulta los horarios y el estado de la recolección municipal.">
            {!!error && <Text accessibilityRole="alert" style={estilos.error}>{error}</Text>}
            <View style={estilos.tarjeta}>
                <Text accessibilityRole="header" style={estilos.encabezado}>{zona?.name ?? 'Elige tu zona'}</Text>
                {zona ? (
                    <>
                        <Text style={estilos.texto}>{zona.district}</Text>
                        <Boton texto="Cambiar zona" secundario onPress={() => elegirZona(null)} />
                    </>
                ) : respuesta?.zonas.map(item => (
                    <Boton key={item.id} texto={`${item.name} · ${item.district}`} secundario onPress={() => elegirZona(item)} />
                ))}
                {!zona && respuesta?.zonas.length === 0 && <Text style={estilos.texto}>No hay zonas activas.</Text>}
                <Boton texto="Actualizar" onPress={() => actualizar(revision + 1)} />
            </View>
            {!actual && !error && <Text accessibilityLiveRegion="polite" style={estilos.texto}>Consultando…</Text>}
            {!!zona && !!actual && (
                <>
                    <Text style={estilos.texto}>Datos del {hora(actual.actualizado)} · Hora de Perú</Text>
                    {!actual.programaciones.length && <Text style={estilos.texto}>No hay turnos de recolección disponibles en esta zona.</Text>}
                    {actual.programaciones.map(turno => (
                        <View key={turno.id} style={estilos.tarjeta}>
                            <Text style={estilos.estado}>{estados[turno.estado]}</Text>
                            <Text style={estilos.texto}>{hora(turno.inicio)} a {hora(turno.fin)}</Text>
                        </View>
                    ))}
                </>
            )}
        </Pantalla>
    );
}
