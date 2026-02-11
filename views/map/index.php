<?php
/**
 * แผนที่ — แสดงตำแหน่งแปลงที่ดิน
 * ค้นหาด้วยเลขบัตร ปชช. / ชื่อ / รหัสแปลง แล้ว Zoom ไปที่แปลง
 */

$db = getDB();

// Load all plots with coordinates
$plotsStmt = $db->query("
    SELECT lp.plot_id, lp.plot_code, lp.latitude, lp.longitude, lp.polygon_coords,
           lp.area_rai, lp.area_ngan, lp.area_sqwa, lp.land_use_type, lp.status,
           lp.plot_image_path, lp.zone,
           v.first_name, v.last_name, v.prefix, v.id_card_number
    FROM land_plots lp
    JOIN villagers v ON lp.villager_id = v.villager_id
    WHERE lp.latitude IS NOT NULL AND lp.longitude IS NOT NULL
    ORDER BY lp.plot_code
");
$allPlots = $plotsStmt->fetchAll();

// Convert to JSON for Leaflet
$plotsJson = json_encode($allPlots, JSON_UNESCAPED_UNICODE);
?>

<style>
    .map-wrapper {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 20px;
        height: calc(100vh - var(--topbar-height) - 100px);
        min-height: 500px;
    }

    .map-sidebar {
        display: flex;
        flex-direction: column;
        gap: 16px;
        overflow: hidden;
    }

    .map-search-results {
        flex: 1;
        overflow-y: auto;
        padding-right: 4px;
    }

    .map-container {
        border-radius: var(--border-radius-lg);
        overflow: hidden;
        border: 1px solid var(--gray-200);
        box-shadow: var(--shadow-md);
    }

    #map {
        width: 100%;
        height: 100%;
        min-height: 500px;
    }

    .plot-card {
        padding: 14px;
        border: 1px solid var(--gray-200);
        border-radius: var(--border-radius-sm);
        cursor: pointer;
        transition: var(--transition);
        margin-bottom: 8px;
        background: white;
    }

    .plot-card:hover,
    .plot-card.active {
        border-color: var(--primary-500);
        background: var(--primary-50);
        box-shadow: var(--shadow-sm);
    }

    .plot-card .plot-code {
        font-weight: 600;
        color: var(--primary-700);
        font-size: 15px;
    }

    .plot-card .owner-name {
        font-size: 13px;
        color: var(--gray-600);
        margin-top: 2px;
    }

    .plot-card .plot-meta {
        font-size: 12px;
        color: var(--gray-400);
        margin-top: 6px;
        display: flex;
        gap: 12px;
    }

    @media (max-width: 900px) {
        .map-wrapper {
            grid-template-columns: 1fr;
        }

        .map-sidebar {
            max-height: 300px;
        }
    }
</style>

<div class="map-wrapper">
    <!-- Left: Search + Results -->
    <div class="map-sidebar">
        <div class="card" style="flex-shrink:0;">
            <div class="card-body" style="padding:14px;">
                <div class="search-bar" style="max-width:100%;">
                    <i class="bi bi-search"></i>
                    <input type="text" id="mapSearch" placeholder="ค้นหา: เลขบัตร / ชื่อ / รหัสแปลง..."
                        style="width:100%;">
                </div>
            </div>
        </div>

        <div class="map-search-results" id="searchResults">
            <div class="text-muted text-center" style="padding:40px 0;">
                <i class="bi bi-geo-alt" style="font-size:32px; opacity:0.3;"></i>
                <p style="margin-top:8px;">พิมพ์ค้นหาเพื่อดูแปลงที่ดินบนแผนที่</p>
                <p style="font-size:12px;">พบทั้งหมด
                    <?= count($allPlots) ?> แปลงที่มีพิกัด
                </p>
            </div>
        </div>
    </div>

    <!-- Right: Map -->
    <div class="map-container">
        <div id="map"></div>
    </div>
</div>

<script>
    // ===== Plot Data =====
    const plotsData = <?= $plotsJson ?>;
    const statusColors = <?= json_encode(PLOT_STATUS_COLORS) ?>;
    const statusLabels = <?= json_encode(PLOT_STATUS_LABELS) ?>;
    const landUseLabels = <?= json_encode(LAND_USE_LABELS) ?>;

    // ===== Initialize Map =====
    const map = L.map('map').setView([13.75, 100.5], 6); // Thailand center

    // Base layers
    const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
    });

    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '© Esri',
        maxZoom: 19,
    });

    const topoLayer = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenTopoMap',
        maxZoom: 17,
    });

    osmLayer.addTo(map);

    L.control.layers({
        'แผนที่ปกติ': osmLayer,
        'ดาวเทียม': satelliteLayer,
        'ภูมิประเทศ': topoLayer,
    }, {
        'ขอบเขตแปลง': (() => {
            const boundaryLayer = L.layerGroup();
            // Load GeoJSON
            const geojsonUrl = '<?= rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") ?>/data/plots_boundaries.geojson';
            console.log('Loading boundaries from:', geojsonUrl);
            fetch(geojsonUrl)
                .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                .then(geojson => {
                    console.log('Loaded', geojson.features.length, 'boundary polygons');
                    const geoLayer = L.geoJSON(geojson, {
                        style: feature => {
                            const bt = feature.properties.ban_type;
                            const colors = { 1: '#22c55e', 2: '#f59e0b', 3: '#ef4444' };
                            return {
                                color: colors[bt] || '#3b82f6',
                                weight: 2,
                                fillOpacity: 0.15,
                                fillColor: colors[bt] || '#3b82f6',
                            };
                        },
                        onEachFeature: (feature, layer) => {
                            const p = feature.properties;
                            layer.bindPopup(`
                                <div style="font-family:Prompt; min-width:180px;">
                                    <strong style="color:#1e40af;">${p.plot_code}</strong>
                                    <hr style="margin:4px 0;border-color:#eee;">
                                    <p style="margin:3px 0;"><b>เจ้าของ:</b> ${p.owner}</p>
                                    <p style="margin:3px 0;"><b>พื้นที่:</b> ${p.area_rai} ไร่ ${p.area_ngan} งาน</p>
                                    <p style="margin:3px 0;"><b>ประเภท:</b> ${p.ptype || '-'}</p>
                                    <p style="margin:3px 0;"><b>อุทยาน:</b> ${p.park}</p>
                                    ${p.remark ? `<p style="margin:3px 0;color:#6b7280;"><i>${p.remark}</i></p>` : ''}
                                </div>
                            `);
                        }
                    });
                    geoLayer.addTo(boundaryLayer);
                })
                .catch(e => console.warn('ไม่สามารถโหลดขอบเขตแปลง:', e));
            boundaryLayer.addTo(map); // Show by default
            return boundaryLayer;
        })()
    }).addTo(map);

    // ===== Add Markers =====
    const markers = {};
    const markersGroup = L.featureGroup();

    plotsData.forEach(plot => {
        const color = statusColors[plot.status] || '#6b7280';

        const icon = L.divIcon({
            className: 'custom-marker',
            html: `<div style="
            width:28px; height:28px; border-radius:50%; 
            background:${color}; border:3px solid white; 
            box-shadow:0 2px 8px rgba(0,0,0,0.3);
            display:flex; align-items:center; justify-content:center;
            color:white; font-size:10px; font-weight:bold;
        ">📍</div>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14],
        });

        const marker = L.marker([plot.latitude, plot.longitude], { icon })
            .bindPopup(`
            <div style="font-family:Prompt; min-width:200px;">
                <strong style="color:${color}; font-size:15px;">${plot.plot_code}</strong>
                <hr style="margin:6px 0; border-color:#eee;">
                <p style="margin:4px 0;"><b>เจ้าของ:</b> ${plot.prefix || ''}${plot.first_name} ${plot.last_name}</p>
                <p style="margin:4px 0;"><b>พื้นที่:</b> ${plot.area_rai} ไร่ ${plot.area_ngan} งาน ${plot.area_sqwa} วา</p>
                <p style="margin:4px 0;"><b>การใช้:</b> ${landUseLabels[plot.land_use_type] || plot.land_use_type}</p>
                <p style="margin:4px 0;"><b>สถานะ:</b> <span style="color:${color}; font-weight:600;">${statusLabels[plot.status] || plot.status}</span></p>
                ${plot.plot_image_path ? `<img src="${plot.plot_image_path}" style="width:100%; margin-top:8px; border-radius:8px;">` : ''}
                <a href="index.php?page=plots&action=view&id=${plot.plot_id}" style="display:block; margin-top:8px; color:#16a34a; font-weight:500;">ดูรายละเอียด →</a>
            </div>
        `);

        marker.addTo(markersGroup);
        markers[plot.plot_id] = { marker, data: plot };

        // Draw polygon if available
        if (plot.polygon_coords) {
            try {
                const coords = typeof plot.polygon_coords === 'string' ? JSON.parse(plot.polygon_coords) : plot.polygon_coords;
                if (Array.isArray(coords) && coords.length > 2) {
                    L.polygon(coords, {
                        color: color,
                        weight: 2,
                        fillOpacity: 0.2,
                        fillColor: color,
                    }).addTo(markersGroup);
                }
            } catch (e) { }
        }
    });

    markersGroup.addTo(map);

    // Fit bounds if markers exist
    if (plotsData.length > 0) {
        map.fitBounds(markersGroup.getBounds(), { padding: [30, 30] });
    }

    // ===== Search =====
    const mapSearchInput = document.getElementById('mapSearch');
    const searchResultsDiv = document.getElementById('searchResults');

    mapSearchInput.addEventListener('input', function () {
        const q = this.value.trim().toLowerCase();
        if (q.length < 2) {
            searchResultsDiv.innerHTML = `<div class="text-muted text-center" style="padding:40px 0;">
            <i class="bi bi-geo-alt" style="font-size:32px; opacity:0.3;"></i>
            <p style="margin-top:8px;">พิมพ์อย่างน้อย 2 ตัวอักษร</p>
        </div>`;
            return;
        }

        const results = plotsData.filter(p =>
            p.plot_code.toLowerCase().includes(q) ||
            p.first_name.toLowerCase().includes(q) ||
            p.last_name.toLowerCase().includes(q) ||
            p.id_card_number.includes(q)
        );

        if (results.length === 0) {
            searchResultsDiv.innerHTML = `<div class="text-muted text-center" style="padding:40px 0;">
            <i class="bi bi-search" style="font-size:28px; opacity:0.3;"></i>
            <p style="margin-top:8px;">ไม่พบผลลัพธ์</p>
        </div>`;
            return;
        }

        searchResultsDiv.innerHTML = results.map(p => `
        <div class="plot-card" onclick="focusPlot(${p.plot_id})" data-id="${p.plot_id}">
            <div class="plot-code">${p.plot_code}</div>
            <div class="owner-name">${p.prefix || ''}${p.first_name} ${p.last_name}</div>
            <div class="plot-meta">
                <span><i class="bi bi-rulers"></i> ${p.area_rai} ไร่</span>
                <span><i class="bi bi-geo-alt"></i> ${p.zone || '-'}</span>
                <span style="color:${statusColors[p.status] || '#666'};">● ${statusLabels[p.status] || p.status}</span>
            </div>
        </div>
    `).join('');

        // Auto-focus first result
        if (results.length === 1) {
            focusPlot(results[0].plot_id);
        }
    });

    function focusPlot(plotId) {
        const item = markers[plotId];
        if (!item) return;

        map.setView([item.data.latitude, item.data.longitude], 16);
        item.marker.openPopup();

        // Highlight card
        document.querySelectorAll('.plot-card').forEach(c => c.classList.remove('active'));
        const card = document.querySelector(`.plot-card[data-id="${plotId}"]`);
        if (card) card.classList.add('active');
    }

    // Check URL params for auto-focus
    const urlParams = new URLSearchParams(window.location.search);
    const focusPlotId = urlParams.get('plot');
    if (focusPlotId) {
        setTimeout(() => focusPlot(parseInt(focusPlotId)), 500);
    }
</script>