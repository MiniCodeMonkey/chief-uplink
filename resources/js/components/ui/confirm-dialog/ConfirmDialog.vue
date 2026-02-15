<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        confirmLabel?: string;
        cancelLabel?: string;
        variant?: 'default' | 'destructive';
        confirmText?: string;
    }>(),
    {
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        variant: 'default',
    },
);

const emit = defineEmits<{
    (e: 'confirm'): void;
    (e: 'cancel'): void;
    (e: 'update:open', value: boolean): void;
}>();

const typedText = ref('');

const canConfirm = computed(() => {
    if (props.confirmText) {
        return typedText.value === props.confirmText;
    }
    return true;
});

function handleConfirm() {
    if (canConfirm.value) {
        emit('confirm');
        typedText.value = '';
    }
}

function handleCancel() {
    emit('cancel');
    emit('update:open', false);
    typedText.value = '';
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="
            (val) => {
                if (!val) handleCancel();
                $emit('update:open', val);
            }
        "
    >
        <DialogContent :show-close-button="false">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="confirmText" class="space-y-2">
                <p class="text-muted-foreground text-sm">
                    Type <strong class="text-foreground">{{ confirmText }}</strong> to
                    confirm.
                </p>
                <input
                    v-model="typedText"
                    type="text"
                    :placeholder="confirmText"
                    class="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px]"
                />
            </div>

            <DialogFooter>
                <Button variant="outline" @click="handleCancel">
                    {{ cancelLabel }}
                </Button>
                <Button
                    :variant="variant === 'destructive' ? 'destructive' : 'default'"
                    :disabled="!canConfirm"
                    @click="handleConfirm"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
