<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: [Number, String],
    placeholder: { type: String, default: '0' },
    className: { type: String, default: '' },
    required: { type: Boolean, default: false },
    max: [Number, String],
});

const emit = defineEmits(['update:modelValue']);

const displayValue = computed(() => {
    if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') return '';
    const cleanNum = String(props.modelValue).replace(/\D/g, '');
    if (!cleanNum) return '';
    return new Intl.NumberFormat('id-ID').format(cleanNum);
});

const onInput = (e) => {
    const rawVal = e.target.value.replace(/\D/g, '');
    let numVal = rawVal ? parseInt(rawVal, 10) : 0;
    if (props.max && numVal > Number(props.max)) {
        numVal = Number(props.max);
    }
    emit('update:modelValue', numVal);
};
</script>

<template>
    <input
        type="text"
        :value="displayValue"
        @input="onInput"
        :placeholder="placeholder"
        :class="`form-control ${className}`"
        :required="required"
    />
</template>
