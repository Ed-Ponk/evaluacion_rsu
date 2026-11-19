import Constants from 'expo-constants';
import { Platform } from 'react-native';

const configuracion = Constants.expoConfig?.extra;
const servidor = configuracion?.servidor?.replace(/\/+$/, '');

export async function api(ruta, { token, datos } = {}) {
    const loopback = Platform.OS === 'android' && configuracion?.laboratorio;
    if (!servidor || (!__DEV__ && !loopback && !servidor.startsWith('https://'))) {
        throw new Error('Configura una dirección HTTPS para el servidor RSU.');
    }
    const abortar = new AbortController();
    const limite = setTimeout(() => abortar.abort(), 12000);
    try {
        const respuesta = await fetch(servidor + '/api/' + ruta, {
            method: datos === undefined ? 'GET' : 'POST',
            headers: {
                Accept: 'application/json',
                ...(datos !== undefined && { 'Content-Type': 'application/json' }),
                ...(token && { Authorization: 'Bearer ' + token }),
            },
            body: datos === undefined ? undefined : JSON.stringify(datos),
            signal: abortar.signal,
            redirect: 'error',
        });
        const contenido = await respuesta.json().catch(() => null);
        if (!respuesta.ok) {
            const error = new Error(contenido?.message || 'El servidor no pudo completar la operación.');
            error.status = respuesta.status;
            throw error;
        }
        if (!contenido) throw new Error('El servidor devolvió una respuesta inválida.');
        return contenido;
    } finally {
        clearTimeout(limite);
    }
}

export function hora(fecha) {
    return new Intl.DateTimeFormat('es-PE', {
        timeZone: 'America/Lima', weekday: 'short', day: 'numeric', month: 'short',
        hour: 'numeric', minute: '2-digit',
    }).format(new Date(fecha));
}
