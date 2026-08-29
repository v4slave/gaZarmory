<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useLocale } from '../i18n.js'

const props = defineProps({
  open: Boolean,
  title: { type: String, default: '' },
  closeLabel: { type: String, default: 'Закрыть' },
  dismissible: { type: Boolean, default: true },
  dirty: Boolean,
})
const emit = defineEmits(['close'])
const dialog = ref(null)
const { t } = useLocale()
let previousFocus

function requestClose() {
  if (!props.dismissible) return
  if (props.dirty && !window.confirm(t('Закрыть без сохранения изменений?'))) return
  emit('close')
}
function onKeydown(event) {
  if (!props.open) return
  if (event.key === 'Escape') { event.preventDefault(); requestClose(); return }
  if (event.key !== 'Tab' || !dialog.value) return
  const focusable = [...dialog.value.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])')]
  if (!focusable.length) { event.preventDefault(); dialog.value.focus(); return }
  const first = focusable[0]; const last = focusable.at(-1)
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}
async function activate() {
  previousFocus = document.activeElement
  document.body.classList.add('modal-open')
  await nextTick()
  dialog.value?.querySelector('[autofocus],input,select,textarea,button')?.focus()
}
function deactivate() {
  document.body.classList.remove('modal-open')
  previousFocus?.focus?.()
}
watch(() => props.open, value => value ? activate() : deactivate())
onMounted(() => { document.addEventListener('keydown', onKeydown); if (props.open) activate() })
onBeforeUnmount(() => { document.removeEventListener('keydown', onKeydown); deactivate() })
</script>

<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div v-if="open" class="modal app-modal" @mousedown.self="requestClose">
        <div ref="dialog" class="app-modal-dialog" role="dialog" aria-modal="true" :aria-label="title || t(closeLabel)" tabindex="-1">
          <slot />
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
