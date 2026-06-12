<template>
  <AppLayout title="Meu perfil" show-back-button>
    <div v-if="loading" class="p-4 space-y-3">
      <Skeleton height="92px" />
      <Skeleton height="180px" />
      <Skeleton height="220px" />
    </div>

    <div v-else class="p-4 pb-24 space-y-4">
      <!-- Cabeçalho: avatar + identidade -->
      <div class="flex items-center gap-3.5 bg-gradient-to-br from-[#1C1C18] to-[#131313] border border-[#2A2A2A] rounded-[16px] p-4">
        <AvatarUpload
          v-model="profile.avatar_url"
          :name="profile.name"
          upload-url="/api/profile/avatar"
          delete-url="/api/profile/avatar"
          field-name="avatar"
          url-key="avatar_url"
          :size="56"
          class="flex-shrink-0"
        />
        <div class="min-w-0">
          <p class="text-[17px] font-semibold text-white truncate">{{ profile.name || '—' }}</p>
          <p class="text-[12px] text-[#A1A1A1] truncate">{{ profile.email }}</p>
          <span class="inline-block mt-1.5 text-[10px] font-medium px-2 py-0.5 rounded-full bg-[#FFD60A]/15 text-[#FFD60A]">
            {{ roleLabel }}
          </span>
        </div>
      </div>

      <!-- Card: dados pessoais -->
      <form class="bg-[#131313] border border-[#2A2A2A] rounded-[16px] p-4 space-y-4" @submit.prevent="saveProfile">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white">
          <UserRound :size="15" :stroke-width="2" class="text-[#A1A1A1]" />
          Dados pessoais
        </h3>

        <FormField
          v-model="form.name"
          type="text"
          label="Nome completo"
          :maxlength="100"
          placeholder=" "
          required
          :error="profileErrors.name"
        />
        <FormField
          v-model="form.email"
          type="email"
          label="Email"
          :maxlength="150"
          placeholder=" "
          required
          :error="profileErrors.email"
        />
        <FormField
          v-if="profile.has_barber"
          v-model="form.phone"
          type="tel"
          label="Telefone"
          placeholder=" "
          :error="profileErrors.phone"
          @input="onPhoneInput"
        />

        <Button type="submit" variant="primary" class="w-full" :loading="savingProfile" loading-text="Salvando...">
          Salvar dados
        </Button>
      </form>

      <!-- Card: senha -->
      <form class="bg-[#131313] border border-[#2A2A2A] rounded-[16px] p-4 space-y-4" @submit.prevent="changePassword">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-white">
          <Lock :size="15" :stroke-width="2" class="text-[#A1A1A1]" />
          Alterar senha
        </h3>

        <FormField
          v-model="pwd.current_password"
          type="password"
          label="Senha atual"
          autocomplete="current-password"
          placeholder=" "
          :error="pwdErrors.current_password"
        />
        <FormField
          v-model="pwd.password"
          type="password"
          label="Nova senha (mín. 8)"
          autocomplete="new-password"
          placeholder=" "
          :error="pwdErrors.password"
        />
        <FormField
          v-model="pwd.password_confirmation"
          type="password"
          label="Confirmar nova senha"
          autocomplete="new-password"
          placeholder=" "
          :error="pwdErrors.password_confirmation"
        />

        <Button type="submit" variant="primary" class="w-full" :loading="savingPwd" loading-text="Alterando...">
          Alterar senha
        </Button>

        <button
          type="button"
          :disabled="sendingReset"
          class="w-full text-center text-[13px] font-medium text-[#A1A1A1] hover:text-white transition-colors disabled:opacity-60"
          @click="forgotPassword"
        >
          {{ sendingReset ? 'Enviando link...' : 'Esqueci minha senha' }}
        </button>
      </form>

      <!-- Zona de risco: só o dono encerra a barbearia inteira -->
      <div v-if="profile.role === 'owner'" class="bg-[#131313] border border-[#EF4444]/25 rounded-[16px] p-4 space-y-3">
        <h3 class="flex items-center gap-1.5 text-[13px] font-semibold text-[#EF4444]">
          <TriangleAlert :size="15" :stroke-width="2" />
          Excluir conta
        </h3>
        <p class="text-[12px] text-[#A1A1A1] leading-relaxed">
          Encerra a barbearia e fecha o acesso. Você tem 30 dias para reativá-la: basta fazer login novamente dentro do prazo e o acesso continuará normalmente. Passado esse período, os dados são apagados em definitivo e o email é liberado.
        </p>
        <button
          type="button"
          class="w-full h-11 rounded-[10px] text-[14px] font-medium text-[#EF4444] border border-[#EF4444]/30 hover:bg-[#EF4444]/10 transition-colors"
          @click="openDeleteModal"
        >
          Excluir minha conta
        </button>
      </div>
    </div>

    <!-- Modal de confirmação: pede a senha de novo antes de excluir -->
    <Transition name="fade">
      <div v-if="showDelete" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center">
        <div class="absolute inset-0 bg-black/70" @click="closeDeleteModal" />
        <div class="relative w-full sm:max-w-[400px] bg-[#161616] border border-[#2A2A2A] rounded-t-[20px] sm:rounded-[20px] p-5 space-y-4 animate-sheet">
          <div class="space-y-1">
            <h3 class="text-[17px] font-bold text-white">Excluir a conta?</h3>
            <p class="text-[13px] text-[#A1A1A1] leading-relaxed">
              Essa ação encerra a barbearia. Você poderá reativá-la em até 30 dias: basta fazer login novamente. Confirme com sua senha para continuar.
            </p>
          </div>

          <FormField
            v-model="deletePassword"
            type="password"
            label="Sua senha"
            autocomplete="current-password"
            placeholder=" "
            :error="deleteError"
            @keyup.enter="confirmDelete"
          />

          <div class="flex gap-2.5 pt-1">
            <button
              type="button"
              class="flex-1 h-11 rounded-[10px] text-[14px] font-medium text-[#A1A1A1] bg-[#1F1F1F] hover:bg-[#262626] transition-colors"
              @click="closeDeleteModal"
            >
              Voltar
            </button>
            <Button variant="primary" class="flex-1 !bg-[#EF4444] !text-white" :loading="deletingAccount" loading-text="Excluindo..." @click="confirmDelete">
              Excluir conta
            </Button>
          </div>
        </div>
      </div>
    </Transition>
  </AppLayout>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { UserRound, Lock, TriangleAlert } from 'lucide-vue-next'
import AppLayout from '../../layouts/AppLayout.vue'
import FormField from '../../components/FormField.vue'
import Button from '../../components/Button.vue'
import Skeleton from '../../components/Skeleton.vue'
import AvatarUpload from '../../components/AvatarUpload.vue'
import { useApi } from '../../composables/useApi'
import { useToast } from '../../composables/useToast'
import { useFormatting } from '../../composables/useFormatting'

const api = useApi()
const toast = useToast()
const { formatPhone } = useFormatting()

const loading = ref(true)
const savingProfile = ref(false)
const savingPwd = ref(false)
const sendingReset = ref(false)

const showDelete = ref(false)
const deletePassword = ref('')
const deletingAccount = ref(false)
const deleteError = ref('')

const profile = reactive<{ name: string; email: string; role: string; avatar_url: string | null; phone: string; has_barber: boolean }>({
  name: '',
  email: '',
  role: '',
  avatar_url: null,
  phone: '',
  has_barber: false,
})
const form = reactive({ name: '', email: '', phone: '' })
const pwd = reactive({ current_password: '', password: '', password_confirmation: '' })

const profileErrors = reactive<Record<string, string>>({})
const pwdErrors = reactive<Record<string, string>>({})

const roleLabels: Record<string, string> = {
  owner: 'Dono',
  manager: 'Gerente',
  receptionist: 'Recepção',
  barber: 'Barbeiro',
}
const roleLabel = computed(() => roleLabels[profile.role] ?? 'Acesso')

const initials = computed(() =>
  (profile.name || '?')
    .trim()
    .split(/\s+/)
    .map((p) => p[0])
    .slice(0, 2)
    .join('')
    .toUpperCase()
)

const clearErrors = (obj: Record<string, string>) => Object.keys(obj).forEach((k) => delete obj[k])
const fillErrors = (target: Record<string, string>, errors?: Record<string, string[]> | null) => {
  if (!errors) return
  for (const [k, v] of Object.entries(errors)) target[k] = v[0]
}

onMounted(async () => {
  const res = await api.get('/api/profile')
  if (res.ok && res.data) {
    profile.name = res.data.name ?? ''
    profile.email = res.data.email ?? ''
    profile.role = res.data.role ?? ''
    profile.avatar_url = res.data.avatar_url ?? null
    profile.has_barber = !!res.data.has_barber
    profile.phone = res.data.phone ?? ''
    form.name = profile.name
    form.email = profile.email
    form.phone = formatPhone(profile.phone)
  }
  loading.value = false
})

const onPhoneInput = (e: Event) => {
  const input = e.target as HTMLInputElement
  let v = input.value.replace(/\D/g, '').slice(0, 11)
  if (v.length > 7) v = `(${v.slice(0, 2)}) ${v.slice(2, 7)}-${v.slice(7)}`
  else if (v.length > 2) v = `(${v.slice(0, 2)}) ${v.slice(2)}`
  else if (v.length > 0) v = `(${v}`
  form.phone = v
  input.value = v
}

const saveProfile = async () => {
  clearErrors(profileErrors)
  savingProfile.value = true
  try {
    const res = await api.put('/api/profile', { name: form.name, email: form.email, phone: form.phone })
    if (res.ok && res.data) {
      profile.name = res.data.name ?? form.name
      profile.email = res.data.email ?? form.email
      profile.phone = res.data.phone ?? profile.phone
      toast.success('Dados atualizados')
    } else if (res.status === 422) {
      fillErrors(profileErrors, res.errors)
    } else {
      toast.error(res.message || 'Não foi possível salvar.')
    }
  } finally {
    savingProfile.value = false
  }
}

const changePassword = async () => {
  clearErrors(pwdErrors)
  savingPwd.value = true
  try {
    const res = await api.put('/api/profile/password', { ...pwd })
    if (res.ok) {
      pwd.current_password = ''
      pwd.password = ''
      pwd.password_confirmation = ''
      toast.success('Senha alterada com sucesso')
    } else if (res.status === 422) {
      fillErrors(pwdErrors, res.errors)
    } else {
      toast.error(res.message || 'Não foi possível alterar a senha.')
    }
  } finally {
    savingPwd.value = false
  }
}

const forgotPassword = async () => {
  sendingReset.value = true
  try {
    const res = await api.post('/api/auth/forgot-password', { email: profile.email })
    if (res.ok) {
      toast.success(`Enviamos um link de redefinição para ${profile.email}`)
    } else {
      toast.error(res.message || 'Não foi possível enviar o link.')
    }
  } finally {
    sendingReset.value = false
  }
}

const openDeleteModal = () => {
  deletePassword.value = ''
  deleteError.value = ''
  showDelete.value = true
}

const closeDeleteModal = () => {
  if (deletingAccount.value) return
  showDelete.value = false
}

const xsrf = () =>
  decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const confirmDelete = async () => {
  deleteError.value = ''
  if (!deletePassword.value) {
    deleteError.value = 'Informe sua senha.'
    return
  }
  deletingAccount.value = true
  try {
    const res = await fetch('/api/account', {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-XSRF-TOKEN': xsrf(),
      },
      body: JSON.stringify({ current_password: deletePassword.value }),
    })
    if (res.ok) {
      const json = await res.json().catch(() => ({}))
      // Sessão já foi derrubada no back: recarrega de fato pra tela de login.
      window.location.href = json.redirect ?? '/login'
      return
    }
    const b = await res.json().catch(() => ({}))
    deleteError.value = b.errors?.current_password?.[0] ?? b.message ?? `Erro ${res.status}.`
  } catch {
    deleteError.value = 'Falha de rede. Tente novamente.'
  } finally {
    deletingAccount.value = false
  }
}
</script>
