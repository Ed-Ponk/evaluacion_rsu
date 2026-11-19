import React from 'react';
import { Pressable, ScrollView, StatusBar, StyleSheet, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';

export function Pantalla({ titulo, subtitulo, conductor = false, children }) {
    return (
        <SafeAreaView style={estilos.pantalla}>
            <StatusBar barStyle="dark-content" />
            <ScrollView keyboardShouldPersistTaps="handled" contentContainerStyle={estilos.contenido}>
                <View style={[estilos.cabecera, conductor && estilos.cabeceraConductor]}>
                    <Text style={estilos.marca}>RSU · {conductor ? 'Operaciones' : 'Servicio municipal'}</Text>
                    <Text accessibilityRole="header" style={estilos.titulo}>{titulo}</Text>
                    <Text style={estilos.subtitulo}>{subtitulo}</Text>
                </View>
                {children}
            </ScrollView>
        </SafeAreaView>
    );
}

export function Boton({ texto, onPress, disabled = false, secundario = false }) {
    return (
        <Pressable accessibilityRole="button" accessibilityState={{ disabled }} disabled={disabled} onPress={onPress}
            style={({ pressed }) => [estilos.boton, secundario && estilos.secundario, (pressed || disabled) && estilos.atenuado]}>
            <Text style={secundario ? estilos.texto : estilos.textoBoton}>{texto}</Text>
        </Pressable>
    );
}

export const estilos = StyleSheet.create({
    pantalla: { flex: 1, backgroundColor: '#f1f5f9' },
    contenido: { width: '100%', maxWidth: 680, alignSelf: 'center', padding: 20, gap: 16 },
    cabecera: { padding: 24, backgroundColor: '#14532d', borderRadius: 18, gap: 8 },
    cabeceraConductor: { backgroundColor: '#0f172a' },
    marca: { color: '#bbf7d0', fontSize: 14, fontWeight: '700' },
    titulo: { color: '#fff', fontSize: 32, fontWeight: '800' },
    subtitulo: { color: '#e2e8f0', fontSize: 16, lineHeight: 23 },
    tarjeta: { backgroundColor: '#fff', borderRadius: 14, padding: 16, gap: 10, borderWidth: 1, borderColor: '#e2e8f0' },
    encabezado: { color: '#0f172a', fontSize: 20, fontWeight: '700' },
    texto: { color: '#334155', fontSize: 16, lineHeight: 23 },
    estado: { color: '#166534', fontSize: 16, fontWeight: '700' },
    error: { color: '#991b1b', fontSize: 16, padding: 12, backgroundColor: '#fef2f2', borderRadius: 10 },
    boton: { minHeight: 48, justifyContent: 'center', alignItems: 'center', borderRadius: 10, backgroundColor: '#166534', padding: 12 },
    secundario: { backgroundColor: '#e2e8f0' },
    textoBoton: { color: '#fff', fontSize: 16, fontWeight: '700' },
    atenuado: { opacity: 0.65 },
    campo: { minHeight: 48, borderWidth: 1, borderColor: '#64748b', color: '#0f172a', padding: 12, borderRadius: 10, fontSize: 16 },
});
