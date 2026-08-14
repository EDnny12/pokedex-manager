<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { nextTick, ref, useTemplateRef } from 'vue';
import AuthButton from '@/Components/Auth/AuthButton.vue';
import AuthField from '@/Components/Auth/AuthField.vue';
import AuthLayout from '@/Components/Auth/AuthLayout.vue';
import { useFocusInvalidField } from '@/composables/useFocusInvalidField';
import { route } from '../../../../vendor/tightenco/ziggy';

type AuthFieldInstance = InstanceType<typeof AuthField>;

const recovery = ref(false);
const codeField = useTemplateRef<AuthFieldInstance>('codeField');
const recoveryField = useTemplateRef<AuthFieldInstance>('recoveryField');

const form = useForm({
    code: '',
    recovery_code: '',
});

const { focusFirstInvalidField } = useFocusInvalidField();

async function toggleRecovery(): Promise<void> {
    recovery.value = !recovery.value;
    form.clearErrors();

    if (recovery.value) {
        form.code = '';
    } else {
        form.recovery_code = '';
    }

    await nextTick();
    (recovery.value ? recoveryField : codeField).value?.focus();
}

function submit(): void {
    form.post(route('two-factor.login'), {
        onError: focusFirstInvalidField,
    });
}
</script>

<template>
    <Head title="Verificación en dos pasos" />

    <AuthLayout
        title="Verifica tu acceso"
        :description="recovery
            ? 'Escribe uno de tus códigos de recuperación para entrar a tu cuenta.'
            : 'Escribe el código generado por tu aplicación de autenticación.'"
    >
        <form class="flex flex-col gap-5" novalidate @submit.prevent="submit">
            <AuthField
                v-if="!recovery"
                id="code"
                ref="codeField"
                v-model="form.code"
                label="Código de autenticación"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123456"
                :error="form.errors.code"
                required
                autofocus
            />

            <AuthField
                v-else
                id="recovery_code"
                ref="recoveryField"
                v-model="form.recovery_code"
                label="Código de recuperación"
                autocomplete="one-time-code"
                :error="form.errors.recovery_code"
                required
            />

            <button
                type="button"
                class="min-h-11 self-start rounded-md text-sm font-semibold text-[#a92634] underline decoration-[#a92634]/30 underline-offset-4 transition-colors hover:text-[#7f1d2a] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#c62f3d] focus-visible:ring-offset-2 dark:text-[#f08b94] dark:hover:text-[#ffadb4] dark:focus-visible:ring-offset-[#181e2b]"
                @click="toggleRecovery"
            >
                {{ recovery ? 'Usar código de autenticación' : 'Usar código de recuperación' }}
            </button>

            <AuthButton :processing="form.processing">Verificar e iniciar sesión</AuthButton>
        </form>
    </AuthLayout>
</template>
