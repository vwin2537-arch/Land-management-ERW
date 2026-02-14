/**
 * Convert NPRK_1012(เอราวัณ)_DNP_WGS1984.shp → data/erawan_boundary.geojson
 * Source CRS: WGS_1984_UTM_Zone_47N (EPSG:32647) → WGS84 lat/lng (EPSG:4326)
 */
const shapefile = require('shapefile');
const proj4 = require('proj4');
const fs = require('fs');
const path = require('path');

const utmProj = '+proj=utm +zone=47 +datum=WGS_1984 +units=m +no_defs';
const wgs84 = '+proj=longlat +datum=WGS84 +no_defs';

function reprojectRing(ring) {
    return ring.map(c => {
        const [lng, lat] = proj4(utmProj, wgs84, [c[0], c[1]]);
        return [Math.round(lng * 100000) / 100000, Math.round(lat * 100000) / 100000];
    });
}

async function main() {
    // Find the .shp file dynamically (Thai chars in filename)
    const dir = path.join(__dirname, 'MAP_ERW');
    const files = fs.readdirSync(dir);
    const shpName = files.find(f => f.endsWith('.shp') && f.startsWith('NPRK'));
    const dbfName = files.find(f => f.endsWith('.dbf') && f.startsWith('NPRK'));
    if (!shpName || !dbfName) { console.error('NPRK .shp/.dbf not found'); process.exit(1); }

    const shpPath = path.join(dir, shpName);
    const dbfPath = path.join(dir, dbfName);
    console.log('Reading:', shpName);

    const source = await shapefile.open(shpPath, dbfPath, { encoding: 'utf-8' });
    const features = [];
    let count = 0;

    while (true) {
        const { done, value } = await source.read();
        if (done) break;
        count++;

        const geom = value.geometry;
        if (!geom) continue;

        const props = {};
        // Copy all properties
        for (const [k, v] of Object.entries(value.properties)) {
            props[k.toLowerCase()] = typeof v === 'string' ? v.trim() : v;
        }

        let newGeom;
        if (geom.type === 'Polygon') {
            newGeom = { type: 'Polygon', coordinates: geom.coordinates.map(reprojectRing) };
        } else if (geom.type === 'MultiPolygon') {
            newGeom = { type: 'MultiPolygon', coordinates: geom.coordinates.map(poly => poly.map(reprojectRing)) };
        } else {
            continue;
        }

        features.push({ type: 'Feature', properties: props, geometry: newGeom });
    }

    console.log(`Read ${count} records, kept ${features.length} features`);

    const geojson = { type: 'FeatureCollection', features };
    const outPath = path.join(__dirname, 'data', 'erawan_boundary.geojson');
    fs.mkdirSync(path.dirname(outPath), { recursive: true });
    fs.writeFileSync(outPath, JSON.stringify(geojson));

    const sizeKB = (fs.statSync(outPath).size / 1024).toFixed(1);
    console.log(`Saved ${outPath} (${sizeKB} KB, ${features.length} features)`);
}

main().catch(e => { console.error(e); process.exit(1); });
