<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthLayout from '@/Components/Auth/AuthLayout.vue';
import { useFocusInvalidField } from '@/composables/useFocusInvalidField';
import { route } from '../../../../vendor/tightenco/ziggy';

const form = useForm({
    password: '',
});

const { focusFirstInvalidField } = useFocusInvalidField();

function submit(): void {
    form.post(route('password.confirm'), {
        onError: focusFirstInvalidField,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Confirmar contraseña" />

    <AuthLayout
        title="Confirma que eres tú"
        description="Esta acción protege información sensible. Escribe tu contraseña para continuar."
    >
        <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
            <AuthField
                id="password"
                v-model="form.password"
                label="Contraseña"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                required
                autofocus
            />

            <AuthButton :processing="form.processing">Confirmar identidad</AuthButton>
        </form>
    </AuthLayout>
</template>
