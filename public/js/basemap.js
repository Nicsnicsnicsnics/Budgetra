/**
 * The one place the app's Leaflet basemap is configured.
 *
 * This used to be three copies of a CartoDB Voyager tile URL. CARTO has since
 * closed anonymous access to its raster basemaps: the requests still return
 * HTTP 200 with a real PNG, but every tile is stamped "API KEY REQUIRED", so
 * nothing in the app errored — the maps just quietly went bad. Swapped to
 * OpenStreetMap's standard tiles, which need no key.
 *
 * The options below are not interchangeable with the CARTO ones:
 *
 *   - No {r}. OSM serves no @2x tiles; on a retina screen Leaflet expands {r}
 *     to "@2x" and every tile 400s.
 *   - No {s}/subdomains. The canonical host is a single one — 'd' in the old
 *     subdomains:'abcd' does not resolve at all, so a quarter of the tiles
 *     would fail. HTTP/2 makes the sharding pointless anyway.
 *   - maxZoom 19, not 20. OSM 400s past z19, and because L.map inherits its
 *     max zoom from the tile layer this is also what stops the user zooming
 *     into a blank grid.
 *
 * Attribution is required by OSM's licence — keep it on every map. Maps that
 * pass attributionControl:false must re-add L.control.attribution themselves.
 */
window.budgetraBasemap = function (map) {
    return L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);
};
