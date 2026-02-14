/**
 * Unified Map Popup — ใช้ร่วมกันทุกหน้าที่แสดงแผนที่แปลงที่ดิน
 * 
 * plotPopupHtml(opts) สร้าง HTML สำหรับ Leaflet .bindPopup()
 * 
 * @param {Object} opts
 * @param {string} opts.plotCode     — รหัสแปลง (required)
 * @param {string} opts.ownerName    — ชื่อผู้ครอบครอง (optional)
 * @param {number} opts.areaRai      — ไร่
 * @param {number} opts.areaNgan     — งาน
 * @param {number} opts.areaSqwa     — ตร.วา (optional, ถ้าไม่มีจะไม่แสดง)
 * @param {string} opts.statusLabel  — label สถานะ เช่น "สำรวจแล้ว"
 * @param {string} opts.statusColor  — hex color ของสถานะ
 * @param {number} opts.plotId       — plot_id สำหรับลิงก์ (optional, ถ้าไม่มีจะไม่แสดงลิงก์)
 * @param {string} opts.accentColor  — สีหลักของ popup header (default: statusColor)
 * @returns {string} HTML string
 */
function plotPopupHtml(opts) {
    const code       = opts.plotCode || '-';
    const owner      = opts.ownerName || '';
    const rai        = opts.areaRai ?? 0;
    const ngan       = opts.areaNgan ?? 0;
    const sqwa       = opts.areaSqwa;
    const stLabel    = opts.statusLabel || '';
    const stColor    = opts.statusColor || '#6b7280';
    const plotId     = opts.plotId;
    const accent     = opts.accentColor || stColor;

    // เนื้อที่: แสดง ไร่-งาน เสมอ, ตร.วา เฉพาะเมื่อมีค่า
    let areaText = `${rai} ไร่ ${ngan} งาน`;
    if (sqwa !== undefined && sqwa !== null && sqwa !== '') {
        areaText += ` ${sqwa} ตร.วา`;
    }

    let html = `<div style="font-family:Prompt,sans-serif; min-width:200px; max-width:260px; line-height:1.5;">`;

    // — Header: รหัสแปลง
    html += `<div style="font-weight:700; font-size:15px; color:${accent}; margin-bottom:2px;">${code}</div>`;

    // — Divider
    html += `<hr style="margin:6px 0; border:0; border-top:1px solid #e5e7eb;">`;

    // — Owner
    if (owner) {
        html += `<div style="font-size:13px; margin:4px 0; color:#374151;"><i class="bi bi-person-fill" style="color:#6b7280; margin-right:4px;"></i>${owner}</div>`;
    }

    // — เนื้อที่
    html += `<div style="font-size:13px; margin:4px 0; color:#374151;"><i class="bi bi-rulers" style="color:#6b7280; margin-right:4px;"></i>${areaText}</div>`;

    // — สถานะ
    if (stLabel) {
        html += `<div style="margin:6px 0 2px;">`;
        html += `<span style="display:inline-block; padding:2px 10px; border-radius:10px; background:${stColor}; color:#fff; font-size:11px; font-weight:600;">${stLabel}</span>`;
        html += `</div>`;
    }

    // — ลิงก์ดูรายละเอียด
    if (plotId) {
        html += `<a href="index.php?page=plots&action=view&id=${plotId}" `
             +  `style="display:block; text-align:center; margin-top:8px; padding:5px 0; `
             +  `background:${accent}; color:#fff; border-radius:6px; text-decoration:none; `
             +  `font-size:12px; font-weight:600; letter-spacing:0.3px;">`
             +  `ดูรายละเอียดแปลง →</a>`;
    }

    html += `</div>`;
    return html;
}

/**
 * addMapLayers(map, opts) — เพิ่ม base layer switcher + ขอบเขตอุทยานฯ ให้แผนที่ทุกหน้า
 *
 * @param {L.Map} map          — Leaflet map instance
 * @param {Object} [opts]
 * @param {boolean} [opts.showPark]     — แสดงขอบเขตอุทยานฯ เป็นค่าเริ่มต้น (default: true)
 * @param {string} [opts.defaultBase]   — 'osm' | 'satellite' | 'topo' (default: 'satellite')
 * @param {Object} [opts.extraOverlays] — overlay เพิ่มเติม เช่น { 'ขอบเขตแปลง': someLayer }
 */
function addMapLayers(map, opts) {
    opts = opts || {};
    const showPark = opts.showPark !== false;
    const defaultBase = opts.defaultBase || 'satellite';

    // Base layers
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '\u00a9 OpenStreetMap', maxZoom: 19,
    });
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '\u00a9 Esri', maxZoom: 19,
    });
    const topoLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        attribution: '\u00a9 OpenTopoMap', maxZoom: 17,
    });

    // Add default base
    if (defaultBase === 'osm') osmLayer.addTo(map);
    else if (defaultBase === 'topo') topoLayer.addTo(map);
    else satelliteLayer.addTo(map);

    // Park boundary overlay
    const parkLayer = L.layerGroup();
    const parkUrl = 'data/erawan_boundary.geojson';
    fetch(parkUrl)
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(geojson) {
            L.geoJSON(geojson, {
                style: function() {
                    return {
                        color: '#0ea5e9',
                        weight: 2.5,
                        dashArray: '8 4',
                        fillColor: '#0ea5e9',
                        fillOpacity: 0.05,
                    };
                },
                onEachFeature: function(feature, layer) {
                    layer.bindPopup(
                        '<div style="font-family:Prompt,sans-serif; text-align:center; min-width:180px;">' +
                        '<div style="font-size:15px; font-weight:700; color:#0ea5e9; margin-bottom:4px;">\u0e2d\u0e38\u0e17\u0e22\u0e32\u0e19\u0e41\u0e2b\u0e48\u0e07\u0e0a\u0e32\u0e15\u0e34\u0e40\u0e2d\u0e23\u0e32\u0e27\u0e31\u0e13</div>' +
                        '<hr style="margin:6px 0; border:0; border-top:1px solid #e0f2fe;">' +
                        '<div style="font-size:12px; color:#64748b;">Erawan National Park</div>' +
                        '<div style="font-size:12px; color:#64748b; margin-top:2px;">\u0e1e\u0e37\u0e49\u0e19\u0e17\u0e35\u0e48\u0e1b\u0e23\u0e30\u0e21\u0e32\u0e13 550 \u0e15\u0e23.\u0e01\u0e21.</div>' +
                        '</div>'
                    );
                }
            }).addTo(parkLayer);
        })
        .catch(function(e) { console.warn('\u0e44\u0e21\u0e48\u0e2a\u0e32\u0e21\u0e32\u0e23\u0e16\u0e42\u0e2b\u0e25\u0e14\u0e02\u0e2d\u0e1a\u0e40\u0e02\u0e15\u0e2d\u0e38\u0e17\u0e22\u0e32\u0e19:', e); });

    if (showPark) parkLayer.addTo(map);

    // Overlays object — park + any extras
    var overlays = {};
    overlays['\u0e2d\u0e38\u0e17\u0e22\u0e32\u0e19\u0e41\u0e2b\u0e48\u0e07\u0e0a\u0e32\u0e15\u0e34\u0e40\u0e2d\u0e23\u0e32\u0e27\u0e31\u0e13'] = parkLayer;
    if (opts.extraOverlays) {
        for (var key in opts.extraOverlays) {
            overlays[key] = opts.extraOverlays[key];
        }
    }

    // Layer control
    L.control.layers({
        '\u0e41\u0e1c\u0e19\u0e17\u0e35\u0e48\u0e1b\u0e01\u0e15\u0e34': osmLayer,
        '\u0e14\u0e32\u0e27\u0e40\u0e17\u0e35\u0e22\u0e21': satelliteLayer,
        '\u0e20\u0e39\u0e21\u0e34\u0e1b\u0e23\u0e30\u0e40\u0e17\u0e28': topoLayer,
    }, overlays).addTo(map);

    return { osmLayer: osmLayer, satelliteLayer: satelliteLayer, topoLayer: topoLayer, parkLayer: parkLayer };
}
