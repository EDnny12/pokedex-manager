<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthLayout from '@/Components/Auth/AuthLayout.vue';
import { useFocusInvalidField } from '@/composables/useFocusInvalidField';
import { route } from '../../../../vendor/tightenco/ziggy';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const { focusFirstInvalidField } = useFocusInvalidField();

function submit(): void {
    form.transform((data) => ({
        ...data,
        remember: data.remember ? 'on' : '',
    })).post(route('login'), {
        onError: focusFirstInvalidField,
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Iniciar sesión" />

    <AuthLayout
        title="Vuelve a tu colección"
        description="Inicia sesión para organizar, explorar y analizar tu colección."
    >
        <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
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
                autofocus
            />

            <AuthField
                id="password"
                v-model="form.password"
                label="Contraseña"
                type="password"
                autocomplete="current-password"
                :error="form.errors.password"
                required
            />

            <label for="remember" class="flex min-h-10 cursor-pointer items-center gap-3 text-sm text-[#475467] dark:text-[#b8c0cf]">
                <input
                    id="remember"
                    v-model="form.remember"
                    name="remember"
                    type="checkbox"
                    class="size-5 rounded border-[#b9bec8] text-[#c62f3d] focus:ring-[#c62f3d] focus:ring-offset-2 dark:border-white/25 dark:bg-[#10141f] dark:focus:ring-[#f08b94] dark:focus:ring-offset-[#181e2b]"
                />
                Mantener la sesión iniciada
            </label>

            <AuthButton :processing="form.processing">Iniciar sesión</AuthButton>
        </form>

        <template #footer>
            ¿Aún no tienes cuenta?
            <Link
                :href="route('register')"
                class="rounded-md font-semibold text-[#a92634] underline decoration-[#a92634]/30 underline-offset-4 transition-colors hover:text-[#7f1d2a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 dark:text-[#f08b94] dark:hover:text-[#ffadb4] dark:focus-visible:ring-offset-[#181e2b]"
            >
                Crear cuenta
            </Link>
        </template>
    </AuthLayout>
</template>
