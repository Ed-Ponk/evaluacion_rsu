import React, { useEffect, useState } from 'react';
import { Alert, Text, TextInput, View } from 'react-native';
import * as Location from 'expo-location';
import * as SecureStore from 'expo-secure-store';
import { api, hora } from './api';
import { Boton, Pantalla, estilos } from './ui';

const claveSesion = 'rsu.conductor.sesion';

export default function Conductor() {
    const [token, guardarToken] = useState(null);
    const [correo, cambiarCorreo] = useState('');
    const [clave, cambiarClave] = useState('');
    const [panel, guardarPanel] = useState(null);
    const [detalle, guardarDetalle] = useState(null);
    const [historial, guardarHistorial] = useState(null);
    const [observacion, cambiarObservacion] = useState('');
    const [ocupado, marcarOcupado] = useState(true);
    const [error, guardarError] = useState('');
    const [aviso, guardarAviso] = useState('');

    function enviar(ruta, datos, sesion = token) {
        return api('conductor/' + ruta, { token: sesion, datos });
    }

    async function cargar(sesion) {
        const [programaciones, hoy, actual] = await Promise.all([
            enviar('programaciones', undefined, sesion),
            enviar('programaciones/hoy', undefined, sesion),
            enviar('recorridos/actual', undefined, sesion),
        ]);
        guardarPanel({ programaciones, actual: actual.data });
        guardarDetalle(hoy.data);
    }

    async function cerrarLocal() {
        await SecureStore.deleteItemAsync(claveSesion);
        guardarToken(null);
        guardarPanel(null);
        guardarDetalle(null);
        guardarHistorial(null);
        cambiarObservacion('');
    }

    async function operar(accion) {
        marcarOcupado(true);
        guardarError('');
        guardarAviso('');
        try {
            await accion();
        } catch (fallo) {
            if (fallo.status === 401) {
                await cerrarLocal();
                guardarError('Tu sesión terminó. Vuelve a iniciar sesión.');
            } else {
                guardarError(fallo.status ? fallo.message : (fallo.message === 'Network request failed'
                    ? 'No se pudo conectar. Actualiza antes de volver a enviar una operación.'
                    : fallo.message));
            }
        } finally {
            marcarOcupado(false);
        }
    }

    useEffect(() => {
        operar(async () => {
            const sesion = await SecureStore.getItemAsync(claveSesion);
            if (sesion) {
                guardarToken(sesion);
                await cargar(sesion);
            }
        });
    }, []);

    async function ingresar() {
        const sesion = await enviar('login', { email: correo.trim(), password: clave, dispositivo: 'RSU Conductor' });
        cambiarClave('');
        await SecureStore.setItemAsync(claveSesion, sesion.token);
        guardarToken(sesion.token);
        await cargar(sesion.token);
    }

    async function registrarUbicacion() {
        const permiso = await Location.requestForegroundPermissionsAsync();
        if (!permiso.granted) {
            guardarError('Activa el permiso de ubicación para registrar un punto.');
            return;
        }
        let completar;
        const lectura = new Promise(resolve => { completar = resolve; });
        const limite = setTimeout(() => completar(null), 15000);
        let suscripcion;
        let punto;
        try {
            suscripcion = await Location.watchPositionAsync({
                accuracy: Location.Accuracy.High,
                mayShowUserSettingsDialog: false,
            }, completar);
            punto = await lectura;
        } finally {
            clearTimeout(limite);
            suscripcion?.remove();
        }
        if (!punto) throw new Error('No se obtuvo una lectura de ubicación. Inténtalo en un lugar con señal.');
        const resultado = await enviar(`recorridos/${panel.actual.id}/puntos`, { puntos: [{
            lat: punto.coords.latitude, lng: punto.coords.longitude, precision: punto.coords.accuracy,
            momento: new Date(punto.timestamp).toISOString(),
        }] });
        guardarAviso(resultado.registrados ? 'Ubicación registrada.' : 'El servidor descartó el punto. Comprueba la hora del dispositivo.');
    }

    async function finalizar() {
        await enviar(`recorridos/${panel.actual.id}/finalizar`, { observacion: observacion.trim() });
        cambiarObservacion('');
        await cargar(token);
        guardarAviso('Recorrido finalizado.');
    }

    return (
        <Pantalla conductor titulo={token ? 'Mis recorridos' : 'Acceso del conductor'}
            subtitulo={panel?.programaciones.conductor.nombre_completo ?? 'Programación, asistencia y seguimiento de ruta.'}>
            {ocupado && <Text accessibilityLiveRegion="polite" style={estilos.texto}>Procesando…</Text>}
            {!!error && <Text accessibilityRole="alert" style={estilos.error}>{error}</Text>}
            {!!aviso && <Text accessibilityLiveRegion="polite" style={estilos.estado}>{aviso}</Text>}
            {!token ? (
                <View style={estilos.tarjeta}>
                    <Text style={estilos.texto}>Correo de personal</Text>
                    <TextInput accessibilityLabel="Correo de personal" style={estilos.campo} value={correo} onChangeText={cambiarCorreo}
                        autoCapitalize="none" autoComplete="email" keyboardType="email-address" />
                    <Text style={estilos.texto}>Clave de marcación</Text>
                    <TextInput accessibilityLabel="Clave de marcación" style={estilos.campo} value={clave} onChangeText={cambiarClave}
                        secureTextEntry autoComplete="current-password" />
                    <Boton texto="Iniciar sesión" disabled={ocupado || !correo.trim() || !clave} onPress={() => operar(ingresar)} />
                </View>
            ) : (
                <>
                    {panel?.actual && (
                        <View style={estilos.tarjeta}>
                            <Text accessibilityRole="header" style={estilos.encabezado}>Recorrido en curso</Text>
                            <Text style={estilos.texto}>{panel.actual.zona.nombre} · {hora(panel.actual.inicio)}</Text>
                            <Boton texto="Registrar ubicación" disabled={ocupado} onPress={() => operar(registrarUbicacion)} />
                            <Text style={estilos.texto}>Observación de cierre</Text>
                            <TextInput accessibilityLabel="Observación de cierre" style={estilos.campo} value={observacion}
                                onChangeText={cambiarObservacion} maxLength={500} multiline />
                            <Boton texto="Finalizar recorrido" disabled={ocupado || observacion.trim().length < 3}
                                onPress={() => Alert.alert('Finalizar recorrido', '¿Confirmas el cierre del recorrido?', [
                                    { text: 'Volver', style: 'cancel' },
                                    { text: 'Finalizar', onPress: () => operar(finalizar) },
                                ])} />
                        </View>
                    )}
                    {detalle && (
                        <View style={estilos.tarjeta}>
                            <Text accessibilityRole="header" style={estilos.encabezado}>{detalle.zona.nombre}</Text>
                            <Text style={estilos.texto}>{hora(detalle.inicio)} · {detalle.vehiculo.placa}</Text>
                            {detalle.asistencia.map(item => <Text key={item.id} style={estilos.texto}>{item.nombre} · {item.presente ? 'Presente' : 'Falta asistencia'}</Text>)}
                            {!!detalle.motivo && <Text style={estilos.texto}>{detalle.motivo}</Text>}
                            <Boton texto="Iniciar recorrido" disabled={ocupado || !detalle.puede_iniciar || !!panel?.actual}
                                onPress={() => Alert.alert('Iniciar recorrido', '¿Confirmas la salida?', [
                                    { text: 'Volver', style: 'cancel' },
                                    { text: 'Iniciar', onPress: () => operar(async () => {
                                        await enviar(`programaciones/${detalle.id}/recorrido`, {});
                                        await cargar(token);
                                    }) },
                                ])} />
                        </View>
                    )}
                    <View style={estilos.tarjeta}>
                        <Text accessibilityRole="header" style={estilos.encabezado}>Programaciones</Text>
                        {panel?.programaciones.data.length === 0 && <Text style={estilos.texto}>No hay programaciones pendientes.</Text>}
                        {panel?.programaciones.data.map(item => <Boton key={item.id} secundario disabled={ocupado}
                            texto={`${item.zona.nombre} · ${hora(item.inicio)}`}
                            onPress={() => operar(async () => guardarDetalle((await enviar(`programaciones/${item.id}`)).data))} />)}
                    </View>
                    <Boton texto="Actualizar asignaciones" secundario disabled={ocupado} onPress={() => operar(() => cargar(token))} />
                    <Boton texto="Ver historial" secundario disabled={ocupado} onPress={() => operar(async () => guardarHistorial(await enviar('recorridos')))} />
                    {historial && (
                        <View style={estilos.tarjeta}>
                            <Text accessibilityRole="header" style={estilos.encabezado}>Historial</Text>
                            {!historial.data.length && <Text style={estilos.texto}>Aún no hay recorridos.</Text>}
                            {historial.data.map(item => <Text key={item.id} style={estilos.texto}>{item.zona.nombre} · {hora(item.inicio)} · {item.estado_texto}</Text>)}
                            {historial.pagina < historial.ultima_pagina && <Boton texto="Cargar más" secundario disabled={ocupado}
                                onPress={() => operar(async () => {
                                    const siguiente = await enviar('recorridos?page=' + (historial.pagina + 1));
                                    guardarHistorial({ ...siguiente, data: [...historial.data, ...siguiente.data] });
                                })} />}
                        </View>
                    )}
                    <Boton texto="Cerrar sesión" secundario disabled={ocupado} onPress={() => operar(async () => {
                        await enviar('logout', {});
                        await cerrarLocal();
                    })} />
                </>
            )}
        </Pantalla>
    );
}
