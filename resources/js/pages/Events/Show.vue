<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';

interface EventDetail {
    id: string;
    type: string;
    status: string;
    created_time: number | null;
    latitude: number | null;
    longitude: number | null;
    payload: Record<string, unknown>;
}

const props = defineProps<{ event: EventDetail }>();

const prettyPayload = computed(() => JSON.stringify(props.event.payload, null, 2));
const eventName = computed(() => (props.event.payload?.name as string | undefined) ?? props.event.id);

const form = useForm({ name: '', email: '' });

function register() {
    form.post(`/events/${props.event.id}/attendees`, {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="eventName" />

    <div class="flex flex-col gap-6 p-4">
        <Link href="/events" class="text-sm text-primary hover:underline">← Back to events</Link>

        <h1 class="text-lg font-semibold">{{ eventName }}</h1>

        <!-- Registration form -->
        <div class="w-full max-w-sm rounded-lg border p-5">
            <h2 class="mb-4 font-semibold">Register for this event</h2>
            <form @submit.prevent="register" class="flex flex-col gap-3">
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground" for="attendee-name">Your name</label>
                    <input
                        id="attendee-name"
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="Jane Smith"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <span v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</span>
                </div>
                <div class="flex flex-col gap-1">
                    <label class="text-xs text-muted-foreground" for="attendee-email">Email address</label>
                    <input
                        id="attendee-email"
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="jane@example.com"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                    />
                    <span v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</span>
                </div>
                <Button type="submit" :disabled="form.processing" class="mt-1">
                    {{ form.processing ? 'Registering…' : 'Register' }}
                </Button>
            </form>
        </div>

        <!-- Raw payload -->
        <details class="rounded-lg border" open>
            <summary class="cursor-pointer px-4 py-3 text-sm font-medium">Event payload</summary>
            <pre class="overflow-x-auto p-4 text-xs">{{ prettyPayload }}</pre>
        </details>
    </div>
</template>
