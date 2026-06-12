<script setup lang="ts">
import { ref, computed } from 'vue'
import { Camera, Loader2, X } from 'lucide-vue-next'
import { useToast } from '../composables/useToast'

interface Props {
  modelValue?: string | null
  name?: string
  uploadUrl: string
  deleteUrl?: string
  fieldName?: string
  urlKey?: string
  shape?: 'circle' | 'square'
  size?: number
  editable?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: null,
  name: '',
  fieldName: 'photo',
  urlKey: 'photo_url',
  shape: 'circle',
  size: 96,
  editable: true,
})

const emit = defineEmits<{ 'update:modelValue': [string | null] }>()
const toast = useToast()

const uploading = ref(false)
const removing = ref(false)
const fileInput = ref<HTMLInputElement | null>(null)

const initials = computed(() =>
  (props.name || '?').trim().split(/\s+/).map((p) => p[0]).slice(0, 2).join('').toUpperCase()
)
const radiusClass = computed(() => (props.shape === 'circle' ? 'rounded-full' : 'rounded-[18px]'))

const xsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '')

const pick = () => fileInput.value?.click()

const onFile = async (e: Event) => {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  input.value = ''
  if (!file) return
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
    toast.error('Formato inválido. Use JPG, PNG ou WEBP.')
    return
  }
  if (file.size > 4 * 1024 * 1024) {
    toast.error('Imagem muito grande (máx. 4MB).')
    return
  }

  uploading.value = true
  try {
    const fd = new FormData()
    fd.append(props.fieldName, file)
    const res = await fetch(props.uploadUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
      body: fd,
    })
    if (res.ok) {
      const body = await res.json().catch(() => ({}))
      const url = body?.data?.[props.urlKey] ?? body?.[props.urlKey] ?? null
      emit('update:modelValue', url)
      toast.success('Foto atualizada')
    } else if (res.status === 422) {
      const body = await res.json().catch(() => ({}))
      toast.error(body?.errors?.[props.fieldName]?.[0] ?? 'Imagem inválida.')
    } else {
      toast.error(`Erro ${res.status}.`)
    }
  } catch {
    toast.error('Falha de rede.')
  } finally {
    uploading.value = false
  }
}

const remove = async () => {
  if (!props.deleteUrl) return
  removing.value = true
  try {
    const res = await fetch(props.deleteUrl, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': xsrf() },
    })
    if (res.ok) {
      emit('update:modelValue', null)
      toast.success('Foto removida')
    } else {
      toast.error('Não foi possível remover.')
    }
  } catch {
    toast.error('Falha de rede.')
  } finally {
    removing.value = false
  }
}
</script>

<template>
  <div class="relative inline-block" :style="{ width: size + 'px', height: size + 'px' }">
    <div
      class="w-full h-full overflow-hidden border border-[#2A2A2A] bg-[#1A1A1A] flex items-center justify-center"
      :class="radiusClass"
    >
      <img v-if="modelValue" :src="modelValue" alt="" class="w-full h-full object-cover" />
      <span v-else class="font-bold text-[#FFD60A]" :style="{ fontSize: Math.round(size * 0.34) + 'px' }">{{ initials }}</span>
    </div>

    <div
      v-if="uploading || removing"
      class="absolute inset-0 flex items-center justify-center bg-black/50"
      :class="radiusClass"
    >
      <Loader2 :size="Math.round(size * 0.3)" class="animate-spin text-white" />
    </div>

    <button
      v-if="editable"
      type="button"
      @click="pick"
      class="absolute -bottom-1 -right-1 w-8 h-8 rounded-full bg-[#FFD60A] text-[#0A0A0A] flex items-center justify-center border-2 border-[#0A0A0A] active:scale-95 transition-transform"
      aria-label="Trocar foto"
    >
      <Camera :size="15" :stroke-width="2.25" />
    </button>

    <button
      v-if="editable && modelValue && deleteUrl"
      type="button"
      @click="remove"
      class="absolute -top-1 -right-1 w-6 h-6 rounded-full bg-[#1A1A1A] text-[#A1A1A1] hover:text-white flex items-center justify-center border border-[#2A2A2A]"
      aria-label="Remover foto"
    >
      <X :size="13" :stroke-width="2.5" />
    </button>

    <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFile" />
  </div>
</template>
