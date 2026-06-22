<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useEventFormat } from '@/composables/useEventFormat';

export interface AttendeeDialogEvent {
    id: string;
    name: string;
    starts_at_iso: string | null;
}

const props = defineProps<{
    modelValue: boolean;
    event: AttendeeDialogEvent | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const { formatDate } = useEventFormat();

const isOpen = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const form = useForm({ name: '', email: '' });

watch(
    () => props.modelValue,
    (opened) => {
        if (opened) form.reset();
    },
);

function submit(): void {
    if (!props.event) return;
    form.post(`/events/${props.event.id}/attendees`, {
        onSuccess: () => {
            isOpen.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle>Register interest</DialogTitle>
                <DialogDescription class="line-clamp-2">
                    {{ event?.name ?? '' }}
                    <template v-if="event?.starts_at_iso">
                        · {{ formatDate(event.starts_at_iso) }}
                    </template>
                </DialogDescription>
            </DialogHeader>

            <form class="flex flex-col gap-4 pt-1" @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <Label for="ad-name">Your name</Label>
                    <Input
                        id="ad-name"
                        v-model="form.name"
                        type="text"
                        required
                        placeholder="Jane Smith"
                        :aria-invalid="!!form.errors.name || undefined"
                    />
                    <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="ad-email">Email address</Label>
                    <Input
                        id="ad-email"
                        v-model="form.email"
                        type="email"
                        required
                        placeholder="jane@example.com"
                        :aria-invalid="!!form.errors.email || undefined"
                    />
                    <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
                </div>

                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="isOpen = false">Cancel</Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Registering…' : 'Register interest' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
