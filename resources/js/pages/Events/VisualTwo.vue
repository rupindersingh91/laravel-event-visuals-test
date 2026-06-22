<script setup lang="ts">
// only EVENT IMAGES must be local per the brief; OSM map tiles are a separate concern
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster';
import 'leaflet.markercluster/dist/MarkerCluster.css';
import 'leaflet.markercluster/dist/MarkerCluster.Default.css';
import { Head } from '@inertiajs/vue3';
import { Loader2 } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AttendeeDialog from '@/components/AttendeeDialog.vue';
import type { AttendeeDialogEvent } from '@/components/AttendeeDialog.vue';
import { useEventFormat } from '@/composables/useEventFormat';

// ── Types ─────────────────────────────────────────────────────────────────────

interface EventRow {
    id: string;
    name: string;
    description: string;
    type: string;
    status: string;
    created_time: number | null;
    starts_at_iso: string | null;
    latitude: number | null;
    longitude: number | null;
    location_name: string;
    image_urls: string[];
    min_price: number | null;
    venue: string;
    user: { id: number; name: string } | null;
}

interface DataResponse {
    data: EventRow[];
    current_page: number;
    last_page: number;
    total: number;
}

type CityCoords = Record<string, [number, number]>;

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{
    cities: string[];
    cityCoords: CityCoords;
}>();

// ── Formatting ────────────────────────────────────────────────────────────────

const { formatDate } = useEventFormat();

// ── Map state ─────────────────────────────────────────────────────────────────

const mapContainer = ref<HTMLDivElement | null>(null);
let mapInstance: L.Map | null = null;
let clusterGroup: L.MarkerClusterGroup | null = null;
let eventById: Map<string, EventRow> = new Map();

// ── UI state ──────────────────────────────────────────────────────────────────

const loading = ref(false);
const total = ref<number | null>(null);
const filters = reactive({ from: '', to: '', city: '' });
const dialogEvent = ref<AttendeeDialogEvent | null>(null);
const dialogOpen = ref(false);

// ── Type → marker colour map ──────────────────────────────────────────────────

const TYPE_COLORS: Record<string, string> = {
    concert: '#ec4899',
    conference: '#3b82f6',
    meetup: '#10b981',
    workshop: '#f59e0b',
    festival: '#8b5cf6',
    sports: '#f97316',
    networking: '#6366f1',
    exhibition: '#14b8a6',
};

function markerIcon(type: string): L.DivIcon {
    const color = TYPE_COLORS[type] ?? '#64748b';
    return L.divIcon({
        className: '',
        html: `<span style="display:block;width:10px;height:10px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.45)"></span>`,
        iconSize: [10, 10],
        iconAnchor: [5, 5],
        popupAnchor: [0, -8],
    });
}

// ── HTML escape ───────────────────────────────────────────────────────────────

function esc(s: string): string {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Build popup HTML (inline styles – Leaflet DOM is outside Vue scope) ───────

function buildPopupHtml(event: EventRow): string {
    const typeColor = TYPE_COLORS[event.type] ?? '#64748b';
    const imgBlock = event.image_urls[0]
        ? `<img src="${esc(event.image_urls[0])}" alt="" style="width:100%;height:80px;object-fit:cover;border-radius:6px 6px 0 0;display:block" onerror="this.style.display='none'">`
        : `<div style="height:50px;border-radius:6px 6px 0 0;background:linear-gradient(135deg,${typeColor}cc,${typeColor}66)"></div>`;

    return `<div style="min-width:200px;max-width:240px;font-family:system-ui,sans-serif">
        ${imgBlock}
        <div style="padding:10px 12px 12px">
            <div style="font-weight:600;font-size:13px;line-height:1.35;margin-bottom:5px">${esc(event.name)}</div>
            <div style="font-size:11px;color:#64748b;margin-bottom:2px">📍 ${esc(event.location_name)}</div>
            <div style="font-size:11px;color:#64748b;margin-bottom:10px">🗓 ${esc(formatDate(event.starts_at_iso))}</div>
            <button data-register-id="${esc(event.id)}" style="width:100%;padding:6px 0;background:#18181b;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;letter-spacing:.01em">
                Register interest
            </button>
        </div>
    </div>`;
}

// ── Add markers to cluster group ──────────────────────────────────────────────

function addMarkers(events: EventRow[]): void {
    if (!clusterGroup) return;
    for (const event of events) {
        if (event.latitude === null || event.longitude === null) continue;
        if (eventById.has(event.id)) continue;
        eventById.set(event.id, event);
        const marker = L.marker([event.latitude, event.longitude], { icon: markerIcon(event.type) });
        marker.bindPopup(buildPopupHtml(event), { maxWidth: 260, minWidth: 210 });
        clusterGroup.addLayer(marker);
    }
}

// ── Fetch one page from /events/data ─────────────────────────────────────────

async function fetchPageData(page: number): Promise<DataResponse> {
    const params = new URLSearchParams({ page: String(page) });
    if (filters.from) params.set('from', filters.from);
    if (filters.to) params.set('to', filters.to);
    if (filters.city) params.set('city', filters.city);
    const res = await fetch(`/events/data?${params.toString()}`, {
        headers: { Accept: 'application/json' },
    });
    return (await res.json()) as DataResponse;
}

// ── Load up to MAX_PAGES pages concurrently ───────────────────────────────────

const MAX_PAGES = 10;

async function loadData(): Promise<void> {
    if (loading.value) return;
    loading.value = true;
    try {
        const first = await fetchPageData(1);
        total.value = first.total;
        addMarkers(first.data);
        if (first.last_page <= 1) return;
        const maxPage = Math.min(first.last_page, MAX_PAGES);
        const remaining = Array.from({ length: maxPage - 1 }, (_, i) => i + 2);
        await Promise.all(remaining.map((p) => fetchPageData(p).then((r) => addMarkers(r.data))));
    } finally {
        loading.value = false;
    }
}

// ── Reset markers and reload with current filters ─────────────────────────────

function resetAndLoad(): void {
    clusterGroup?.clearLayers();
    eventById = new Map();
    total.value = null;
    void loadData();
}

// ── Popup click delegation – register button lives inside Leaflet DOM ─────────

function handleMapClick(e: MouseEvent): void {
    const btn = (e.target as Element).closest('[data-register-id]');
    if (!btn) return;
    const id = btn.getAttribute('data-register-id') ?? '';
    const event = eventById.get(id);
    if (event) {
        dialogEvent.value = event;
        dialogOpen.value = true;
    }
}

// ── Initialise Leaflet ────────────────────────────────────────────────────────

function initMap(): void {
    if (!mapContainer.value) return;
    mapInstance = L.map(mapContainer.value, { center: [30, 0], zoom: 2 });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(mapInstance);

    clusterGroup = L.markerClusterGroup({ chunkedLoading: true, maxClusterRadius: 50 });
    mapInstance.addLayer(clusterGroup);
    mapContainer.value.addEventListener('click', handleMapClick);
}

// ── City select → fly to anchor ───────────────────────────────────────────────

watch(
    () => filters.city,
    (city) => {
        if (!city || !mapInstance) return;
        const coords = props.cityCoords[city];
        if (coords) mapInstance.flyTo(coords, 11, { duration: 1.5 });
    },
);

// ── Debounced data reload on any filter change ────────────────────────────────

watchDebounced(() => ({ ...filters }), resetAndLoad, { debounce: 400, deep: true });

// ── Lifecycle ─────────────────────────────────────────────────────────────────

onMounted(() => {
    initMap();
    void loadData();
});

onBeforeUnmount(() => {
    mapContainer.value?.removeEventListener('click', handleMapClick);
    mapInstance?.remove();
    mapInstance = null;
    clusterGroup = null;
});
</script>

<template>
    <Head title="Events – Map" />

    <!-- Full-height container; calc subtracts the AppSidebarHeader height (~3.5rem / 56 px) -->
    <div class="relative overflow-hidden" style="height: calc(100svh - 3.5rem)">
        <!-- Leaflet map fills this container absolutely -->
        <div ref="mapContainer" class="absolute inset-0" />

        <!-- Floating filter panel – z-[500] keeps it above Leaflet controls (z ~400) -->
        <div
            class="panel-enter absolute right-4 top-4 z-[500] w-72 rounded-xl border border-border/50 bg-background/92 p-4 shadow-xl backdrop-blur-md"
        >
            <div class="mb-4 flex items-center justify-between">
                <h2 class="font-semibold tracking-tight">Events Map</h2>
                <span class="flex items-center gap-1.5 text-sm text-muted-foreground">
                    <Loader2 v-if="loading" class="size-3.5 animate-spin" />
                    <template v-else>
                        <span v-if="total !== null" class="font-medium text-foreground">
                            {{ total.toLocaleString() }}
                        </span>
                        <span v-else>—</span>
                        <span>events</span>
                    </template>
                </span>
            </div>

            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-medium text-muted-foreground">City</label>
                    <select
                        v-model="filters.city"
                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                    >
                        <option value="">All cities</option>
                        <option v-for="city in props.cities" :key="city" :value="city">
                            {{ city }}
                        </option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-muted-foreground">From</label>
                        <input
                            v-model="filters.from"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-2 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                        />
                    </div>
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-medium text-muted-foreground">To</label>
                        <input
                            v-model="filters.to"
                            type="date"
                            class="h-9 rounded-md border border-input bg-background px-2 text-xs focus:ring-2 focus:ring-ring focus:outline-none"
                        />
                    </div>
                </div>
            </div>

            <!-- Type legend -->
            <div class="mt-4 border-t border-border/50 pt-3">
                <p class="mb-2 text-xs font-medium text-muted-foreground">Event type</p>
                <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                    <div
                        v-for="(color, type) in TYPE_COLORS"
                        :key="type"
                        class="flex items-center gap-1.5"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full border border-white/40 shadow-sm"
                            :style="{ background: color }"
                        />
                        <span class="truncate text-xs text-muted-foreground">{{ type }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shared attendee registration dialog -->
    <AttendeeDialog v-model="dialogOpen" :event="dialogEvent" />
</template>

<style scoped>
@keyframes panel-in {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.panel-enter {
    animation: panel-in 0.3s ease-out both;
}
</style>
