import React from 'react';
import { Text, View } from 'react-native';
import Svg, { Circle, Polygon, Polyline } from 'react-native-svg';
import { estilos } from './ui';

export default function Mapa({ vertices = [], puntos = [], ubicacion, finalizado, altura = 240 }) {
    const todas = [
        ...vertices.map(([lat, lng]) => ({ lat, lng })),
        ...puntos,
        ...(ubicacion ? [ubicacion] : []),
    ];
    if (!todas.length) return <Text style={estilos.texto}>Este recorrido no tiene ubicaciones registradas.</Text>;

    const latitudes = todas.map(p => +p.lat);
    const longitudes = todas.map(p => +p.lng);
    const sur = Math.min(...latitudes);
    const norte = Math.max(...latitudes);
    const oeste = Math.min(...longitudes);
    const este = Math.max(...longitudes);
    const cosLat = Math.cos((sur + norte) * Math.PI / 360);
    const escala = Math.min(278 / Math.max((este - oeste) * cosLat, 0.0005), 178 / Math.max(norte - sur, 0.0005));
    const xy = ({ lat, lng }) => [
        160 + (lng - (oeste + este) / 2) * cosLat * escala,
        110 - (lat - (sur + norte) / 2) * escala,
    ];
    const marca = (punto, color, radio) => {
        const [cx, cy] = xy(punto);
        return <Circle cx={cx} cy={cy} r={radio} fill={color} stroke="#fff" strokeWidth={2} />;
    };
    return <View accessibilityLabel={`Mapa de zona y trayectoria, ${puntos.length} ubicaciones registradas`}
        style={{ backgroundColor: '#e9f2f3', borderRadius: 10, overflow: 'hidden', padding: 8 }}>
        <Svg width="100%" height={altura} viewBox="0 0 320 220">
            {vertices.length >= 3 && <Polygon points={vertices.map(([lat, lng]) => xy({ lat, lng }).join(',')).join(' ')}
                fill="#b9def3" fillOpacity={0.65} stroke="#4884a6" strokeWidth={2} strokeDasharray="5 5" />}
            {puntos.length > 1 && <Polyline points={puntos.map(p => xy(p).join(',')).join(' ')}
                fill="none" stroke="#0759ae" strokeWidth={4} strokeLinejoin="round" strokeLinecap="round" />}
            {puntos.length > 0 && marca(puntos[0], '#15803d', finalizado ? 12 : 8)}
            {puntos.length > 0 && marca(puntos.at(-1), finalizado ? '#b91c1c' : '#0759ae', 6)}
            {ubicacion && marca(ubicacion, '#6d28d9', 6)}
        </Svg>
        <Text style={estilos.texto}>Zona · {finalizado ? 'Inicio y fin' : 'Última posición del camión'} · {puntos.length} ubicaciones{ubicacion ? ' · Tu ubicación' : ''}</Text>
    </View>;
}
