<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Banknote, CalendarDays, MapPin, Ticket } from '@lucide/vue';
import { watchDebounced } from '@vueuse/core';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import type { BadgeVariants } from '@/components/ui/badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AttendeeDialog from '@/components/AttendeeDialog.vue';
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

interface FilterForm {
    from: string;
    to: string;
    city: string;
}

// ── Props ─────────────────────────────────────────────────────────────────────

const props = defineProps<{ cities: string[] }>();

// ── Formatting ────────────────────────────────────────────────────────────────

const { formatDate, formatPrice } = useEventFormat();

// ── Listing state ─────────────────────────────────────────────────────────────

const rows = ref<EventRow[]>([]);
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const hasLoadedOnce = ref(false);
const sentinel = ref<HTMLDivElement | null>(null);

const hasMore = computed(() => lastPage.value === null || page.value < lastPage.value);

// ── Carousel ──────────────────────────────────────────────────────────────────

const carouselTick = ref(0);
let carouselTimer: ReturnType<typeof setInterval> | null = null;

function currentImage(event: EventRow): string {
    const urls = event.image_urls;
    if (!urls.length) return '';
    return urls[carouselTick.value % urls.length] ?? '';
}

function isDotActive(event: EventRow, i: number): boolean {
    return event.image_urls.length > 0 && carouselTick.value % event.image_urls.length === i;
}

// ── Type-keyed gradient fallbacks ─────────────────────────────────────────────

const TYPE_GRADIENTS: Record<string, string> = {
    concert: 'from-pink-500 to-rose-600',
    conference: 'from-blue-500 to-indigo-600',
    meetup: 'from-emerald-500 to-green-600',
    workshop: 'from-amber-400 to-yellow-500',
    festival: 'from-purple-500 to-violet-600',
    sports: 'from-orange-500 to-red-500',
    networking: 'from-indigo-400 to-blue-500',
    exhibition: 'from-teal-500 to-cyan-600',
};

function cardGradient(type: string): string {
    return TYPE_GRADIENTS[type] ?? 'from-slate-500 to-gray-600';
}

function handleImageError(e: Event): void {
    (e.target as HTMLImageElement).style.display = 'none';
}

// ── Filters ───────────────────────────────────────────────────────────────────

const filters = reactive<FilterForm>({ from: '', to: '', city: '' });

// ── Data fetching ─────────────────────────────────────────────────────────────

async function fetchPage(): Promise<void> {
    if (loading.value || !hasMore.value) return;
    loading.value = true;
    try {
        const params = new URLSearchParams({ page: String(page.value + 1) });
        if (filters.from) params.set('from', filters.from);
        if (filters.to) params.set('to', filters.to);
        if (filters.city) params.set('city', filters.city);

        const res = await fetch(`/events/data?${params.toString()}`, {
            headers: { Accept: 'application/json' },
        });
        const payload: DataResponse = await res.json();

        rows.value.push(...payload.data);
        page.value = payload.current_page;
        lastPage.value = payload.last_page;
        total.value = payload.total;
        hasLoadedOnce.value = true;
    } finally {
        loading.value = false;
    }
}

function resetAndFetch(): void {
    rows.value = [];
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoadedOnce.value = false;
    void fetchPage();
}

watchDebounced(() => ({ ...filters }), resetAndFetch, { debounce: 400, deep: true });

// ── Status badge ──────────────────────────────────────────────────────────────

function statusVariant(status: string): BadgeVariants['variant'] {
    if (status === 'published') return 'default';
    if (status === 'cancelled') return 'destructive';
    if (status === 'sold_out') return 'secondary';
    return 'outline';
}

// ── Register interest dialog ──────────────────────────────────────────────────

const selectedEvent = ref<EventRow | null>(null);
const dialogOpen = ref(false);

function openRegisterDialog(event: EventRow): void {
    selectedEvent.value = event;
    dialogOpen.value = true;
}

// ── Lifecycle ─────────────────────────────────────────────────────────────────

let observer: IntersectionObserver | null = null;

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) void fetchPage();
        },
        { rootMargin: '400px' },
    );
    if (sentinel.value) observer.observe(sentinel.value);
    void fetchPage();
    carouselTimer = setInterval(() => {
        carouselTick.value++;
    }, 3000);
});

onBeforeUnmount(() => {
    observer?.disconnect();
    if (carouselTimer !== null) clearInterval(carouselTimer);
});
</script>

<template>
    <Head title="Events – Grid" />

    <div class="flex flex-col gap-6 px-4 py-6">
        <!-- Page header -->
        <div>
            <h1 class="text-xl font-semibold">Events</h1>
            <p class="mt-0.5 text-sm text-muted-foreground">
                {{ total !== null ? `${total.toLocaleString()} events` : 'Loading…' }}
            </p>
        </div>

        <!-- Filters bar -->
        <div class="flex flex-wrap items-end gap-3 rounded-xl border bg-muted/30 px-4 py-3">
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-muted-foreground" for="v1-from">From</label>
                <input
                    id="v1-from"
                    v-model="filters.from"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-muted-foreground" for="v1-to">To</label>
                <input
                    id="v1-to"
                    v-model="filters.to"
                    type="date"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                />
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-muted-foreground" for="v1-city">City</label>
                <select
                    id="v1-city"
                    v-model="filters.city"
                    class="h-9 min-w-[180px] rounded-md border border-input bg-background px-3 text-sm focus:ring-2 focus:ring-ring focus:outline-none"
                >
                    <option value="">All cities</option>
                    <option v-for="city in props.cities" :key="city" :value="city">{{ city }}</option>
                </select>
            </div>
        </div>

        <!-- Card grid -->
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <article
                v-for="(event, index) in rows"
                :key="event.id"
                class="card-enter group flex flex-col overflow-hidden rounded-xl border bg-card shadow-sm transition-all duration-200 ease-out hover:-translate-y-1 hover:shadow-md"
                :style="{ animationDelay: `${Math.min(index % 15, 14) * 50}ms` }"
            >
                <!-- Image / carousel -->
                <div
                    class="relative h-44 overflow-hidden"
                    :class="`bg-gradient-to-br ${cardGradient(event.type)}`"
                >
                    <img
                        v-if="event.image_urls.length > 0"
                        :key="currentImage(event)"
                        :src="currentImage(event)"
                        :alt="event.name"
                        class="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
                        @error="handleImageError"
                    />

                    <!-- Top badges -->
                    <div class="absolute left-2 top-2 flex flex-wrap gap-1.5">
                        <Badge :variant="statusVariant(event.status)" class="text-[10px]">
                            {{ event.status.replace('_', ' ') }}
                        </Badge>
                        <Badge variant="outline" class="bg-background/80 text-[10px] backdrop-blur-sm">
                            {{ event.type }}
                        </Badge>
                    </div>

                    <!-- Carousel dots -->
                    <div
                        v-if="event.image_urls.length > 1"
                        class="absolute bottom-2 left-0 right-0 flex justify-center gap-1"
                    >
                        <span
                            v-for="(_, i) in event.image_urls"
                            :key="i"
                            class="h-1.5 w-1.5 rounded-full transition-colors duration-300"
                            :class="isDotActive(event, i) ? 'bg-white' : 'bg-white/40'"
                        />
                    </div>
                </div>

                <!-- Card body -->
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div>
                        <h2 class="line-clamp-1 font-semibold leading-snug">{{ event.name }}</h2>
                        <p
                            v-if="event.description"
                            class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ event.description }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-1.5 text-sm text-muted-foreground">
                        <span class="flex items-center gap-1.5">
                            <MapPin class="size-3.5 shrink-0" />
                            <span class="truncate">{{ event.location_name }}</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <CalendarDays class="size-3.5 shrink-0" />
                            <span>{{ formatDate(event.starts_at_iso) }}</span>
                        </span>
                    </div>

                    <!-- Footer row -->
                    <div class="mt-auto flex items-center justify-between pt-1">
                        <span class="flex items-center gap-1 text-sm font-medium">
                            <Banknote class="size-3.5 shrink-0 text-muted-foreground" />
                            {{ formatPrice(event.min_price) }}
                        </span>
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-7 gap-1 text-xs"
                            @click="openRegisterDialog(event)"
                        >
                            <Ticket class="size-3" />
                            Register
                        </Button>
                    </div>
                </div>
            </article>

            <!-- Skeleton placeholders while loading -->
            <template v-if="loading">
                <div
                    v-for="i in 6"
                    :key="`skel-${i}`"
                    class="flex flex-col overflow-hidden rounded-xl border bg-card"
                >
                    <div class="h-44 animate-pulse bg-muted" />
                    <div class="flex flex-col gap-3 p-4">
                        <div class="h-4 w-3/4 animate-pulse rounded-md bg-muted" />
                        <div class="h-3 w-full animate-pulse rounded-md bg-muted" />
                        <div class="h-3 w-2/3 animate-pulse rounded-md bg-muted" />
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty state -->
        <div
            v-if="hasLoadedOnce && !loading && rows.length === 0"
            class="flex flex-col items-center gap-3 py-24 text-center"
        >
            <Ticket class="size-10 text-muted-foreground/30" />
            <p class="font-medium">No events found</p>
            <p class="text-sm text-muted-foreground">Try adjusting the date range or city filter.</p>
        </div>

        <!-- Infinite scroll sentinel -->
        <div ref="sentinel" class="h-px" />

        <!-- Loading footer -->
        <p v-if="loading && hasLoadedOnce" class="text-center text-sm text-muted-foreground">
            Loading more events…
        </p>
    </div>

    <!-- Register interest dialog (shared component) -->
    <AttendeeDialog v-model="dialogOpen" :event="selectedEvent" />
</template>

<style scoped>
@keyframes card-in {
    from {
        opacity: 0;
        transform: translateY(14px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.card-enter {
    animation: card-in 0.35s ease-out both;
}
</style>
