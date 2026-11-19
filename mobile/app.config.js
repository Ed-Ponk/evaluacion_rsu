const conductor = process.env.EXPO_PUBLIC_RSU_APP === 'conductor';
const packageId = conductor ? 'pe.rsu.conductor' : 'pe.rsu.cliente';
const servidor = process.env.EXPO_PUBLIC_RSU_API_URL;
const laboratorio = /^http:\/\/127\.0\.0\.1:[0-9]+$/.test(servidor ?? '');

const plugins = conductor ? [
    ['expo-location', { locationWhenInUsePermission: 'RSU registra la ubicación del recorrido mientras usas la aplicación.' }],
    'expo-secure-store',
] : [];
if (laboratorio) plugins.push('./withLoopback');

module.exports = {
    name: conductor ? 'RSU Conductor' : 'RSU Recolección',
    slug: conductor ? 'rsu-conductor' : 'rsu-recoleccion',
    version: '1.0.0',
    orientation: 'portrait',
    extra: { variante: conductor ? 'conductor' : 'cliente', servidor, laboratorio },
    android: {
        package: packageId,
        blockedPermissions: conductor ? [] : [
            'android.permission.ACCESS_COARSE_LOCATION',
            'android.permission.ACCESS_FINE_LOCATION',
        ],
    },
    ios: { bundleIdentifier: packageId, supportsTablet: true },
    plugins,
};
