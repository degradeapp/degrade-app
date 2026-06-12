import { createApp, h, DefineComponent } from 'vue'
import { createInertiaApp, router } from '@inertiajs/vue3'
import Toast from '@/components/Toast.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import { useToast } from '@/composables/useToast'
import '../css/app.css'

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob<{ default: DefineComponent }>('./pages/**/*.vue')
    const resolvePageComponent = (path: string) => {
      const page = pages[path]
      if (!page) throw new Error(`Page not found: ${name}`)
      return page()
    }

    return resolvePageComponent(`./pages/${name}.vue`)
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) })
      .use(plugin)
      .component('Toast', Toast)
      .component('ConfirmDialog', ConfirmDialog)

    app.mount(el)
  },
})

// Auto-dispara toast quando flash chegar via Inertia shared props
router.on('finish', () => {
  const flash = (router as any)?.page?.props?.flash ?? {}
  const { success, error: showError, info } = useToast()
  if (flash.success) success(flash.success)
  if (flash.error) showError(flash.error)
  if (flash.info) info(flash.info)
})
