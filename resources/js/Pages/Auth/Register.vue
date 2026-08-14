<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthLayout from '@/Components/Auth/AuthLayout.vue';
import { useFocusInvalidField } from '@/composables/useFocusInvalidField';
import { route } from '../../../../vendor/tightenco/ziggy';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const { focusFirstInvalidField } = useFocusInvalidField();

function submit(): void {
    form.post(route('register'), {
        onError: focusFirstInvalidField,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Crear cuenta" />

    <AuthLayout
        title="Crea tu perfil de entrenador"
        description="Crea tu cuenta para guardar, organizar y analizar tu colección."
    >
        <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
            <AuthField
                id="name"
                v-model="form.name"
                label="Nombre"
                autocomplete="name"
                placeholder="Alex"
                :error="form.errors.name"
                required
                autofocus
            />

            <AuthField
                id="email"
                v-model="form.email"
                label="Correo electrónico"
                type="email"
                inputmode="email"
                autocomplete="username"
                placeholder="entrenador@ejemplo.com"
                :error="form.errors.email"
                required
            />

            <AuthField
                id="password"
                v-model="form.password"
                label="Contraseña"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password"
                required
            />

            <AuthField
                id="password_confirmation"
                v-model="form.password_confirmation"
                label="Confirmar contraseña"
                type="password"
                autocomplete="new-password"
                :error="form.errors.password_confirmation"
                required
            />

            <AuthButton :processing="form.processing">Crear cuenta</AuthButton>
        </form>

        <template #footer>
            ¿Ya tienes cuenta?
            <Link
                :href="route('login')"
                class="rounded-md font-semibold text-[#a92634] underline decoration-[#a92634]/30 underline-offset-4 transition-colors hover:text-[#7f1d2a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 dark:text-[#f08b94] dark:hover:text-[#ffadb4] dark:focus-visible:ring-offset-[#181e2b]"
            >
                Iniciar sesión
            </Link>
        </template>
    </AuthLayout>
</template>
