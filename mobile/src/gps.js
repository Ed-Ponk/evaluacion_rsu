import * as TaskManager from 'expo-task-manager';
import * as Location from 'expo-location';
import * as SQLite from 'expo-sqlite';
import { randomUUID } from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';
import { api } from './api';

const TAREA = 'rsu.recorrido.v3';
let conexion;
let sincronizando;
function base() {
    return conexion ??= SQLite.openDatabaseAsync('recorridos-v3.db').then(async db => {
        await db.execAsync('PRAGMA journal_mode=WAL; PRAGMA busy_timeout=3000; CREATE TABLE IF NOT EXISTS control_v3 (id INTEGER PRIMARY KEY CHECK(id=1), clave TEXT, inicio INTEGER, fin INTEGER); CREATE TABLE IF NOT EXISTS cola (id TEXT PRIMARY KEY, clave TEXT, fecha INTEGER, contenido TEXT); CREATE TABLE IF NOT EXISTS capturas (clave TEXT PRIMARY KEY, fecha INTEGER); CREATE TABLE IF NOT EXISTS ubicaciones_rechazadas (id TEXT PRIMARY KEY, clave TEXT, fecha INTEGER, contenido TEXT);');
        return db;
    });
}

TaskManager.defineTask(TAREA, async ({ data, error }) => {
    if (error || !data?.locations?.length) return;
    const db = await base();
    const activo = await db.getFirstAsync('SELECT clave, inicio, fin FROM control_v3 WHERE id=1');
    if (!activo || Date.now() >= activo.fin) {
        await detenerGPS();
        return;
    }
    await db.withExclusiveTransactionAsync(async tx => {
        const control = await tx.getFirstAsync('SELECT clave FROM control_v3 WHERE id=1');
        if (control?.clave !== activo.clave) return;
        const ultima = await tx.getFirstAsync('SELECT fecha FROM capturas WHERE clave=?', activo.clave);
        let anterior = ultima?.fecha ?? 0;
        for (const p of data.locations.sort((a, b) => a.timestamp - b.timestamp)) {
            if (p.timestamp < activo.inicio || p.timestamp >= activo.fin || p.timestamp <= anterior) continue;
            const operacion = randomUUID();
            const punto = { operacion, lat: p.coords.latitude, lng: p.coords.longitude,
                precision: p.coords.accuracy, momento: new Date(p.timestamp).toISOString() };
            await tx.runAsync('INSERT INTO cola VALUES (?,?,?,?)', operacion, activo.clave, p.timestamp, JSON.stringify(punto));
            anterior = p.timestamp;
        }
        if (anterior) await tx.runAsync('INSERT OR REPLACE INTO capturas VALUES (?,?)', activo.clave, anterior);
    });
    const guardada = await SecureStore.getItemAsync('rsu.v3.conductor');
    const sesion = guardada ? JSON.parse(guardada) : null;
    const [origen, id] = activo.clave.split('#');
    if (sesion?.token && sesion.origen === origen) {
        try {
            await enviarGPS(origen, sesion.token, { id });
        } catch (fallo) {
            if (fallo.status === 401 || (fallo.status === 422 && fallo.message.includes('ya fue finalizado'))) await detenerGPS();
        }
    }
});

export function claveGPS(origen, viaje) { return origen + '#' + viaje.id; }

export async function iniciarGPS(origen, viaje) {
    const db = await base();
    const clave = claveGPS(origen, viaje);
    const fin = Date.parse(viaje.captura_hasta);
    if (!Number.isFinite(fin) || Date.now() >= fin) return;
    await db.runAsync('INSERT OR REPLACE INTO control_v3 VALUES (1,?,?,?)', clave, Date.parse(viaje.inicio), fin);
    if (await Location.hasStartedLocationUpdatesAsync(TAREA)) return;
    await Location.startLocationUpdatesAsync(TAREA, {
        accuracy: Location.Accuracy.High, timeInterval: 5000, distanceInterval: 0,
        deferredUpdatesInterval: 0, pausesUpdatesAutomatically: false, showsBackgroundLocationIndicator: true,
        foregroundService: { notificationTitle: 'Recorrido en curso', notificationBody: 'RSU está registrando la ubicación del recorrido.' },
    });
}

export async function detenerGPS() {
    const db = await base();
    await db.runAsync('DELETE FROM control_v3');
    if (await Location.hasStartedLocationUpdatesAsync(TAREA)) await Location.stopLocationUpdatesAsync(TAREA);
}

export async function colaGPS(origen, viaje) {
    const db = await base();
    return db.getAllAsync('SELECT id, contenido FROM cola WHERE clave=? ORDER BY fecha,id LIMIT 100', claveGPS(origen, viaje));
}

export async function pendientesGPS(origen, viaje) {
    const db = await base();
    return (await db.getFirstAsync('SELECT COUNT(*) AS total FROM cola WHERE clave=?', claveGPS(origen, viaje))).total;
}

export async function rechazadasGPS(origen, viaje) {
    const db = await base();
    return (await db.getFirstAsync('SELECT COUNT(*) AS total FROM ubicaciones_rechazadas WHERE clave=?', claveGPS(origen, viaje))).total;
}

export function enviarGPS(origen, token, viaje) {
    if (sincronizando) return sincronizando;
    sincronizando = (async () => {
        const filas = await colaGPS(origen, viaje);
        if (!filas.length) return;
        const resultado = await api('conductor/recorridos/' + viaje.id + '/puntos', {
            origen, token, datos: { puntos: filas.map(fila => JSON.parse(fila.contenido)) },
        });
        const rechazadas = resultado.fuera_de_ventana ?? [];
        if (resultado.registrados + resultado.repetidos + rechazadas.length !== filas.length) {
            throw new Error('No se confirmó el lote. Los puntos siguen guardados para revisión.');
        }
        const db = await base();
        await db.withExclusiveTransactionAsync(async tx => {
            if (rechazadas.length) {
                await tx.runAsync('INSERT OR IGNORE INTO ubicaciones_rechazadas SELECT * FROM cola WHERE id IN (' + rechazadas.map(() => '?').join(',') + ')', rechazadas);
            }
            await tx.runAsync('DELETE FROM cola WHERE id IN (' + filas.map(() => '?').join(',') + ')', filas.map(fila => fila.id));
        });
    })().finally(() => { sincronizando = null; });
    return sincronizando;
}
