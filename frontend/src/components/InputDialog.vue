<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useLocale } from '../i18n.js'
import { useInputDialogStore } from '../stores/input-dialog.js'

const dialogStore = useInputDialogStore()
const { t } = useLocale()
const value = ref('')
const dialog = ref(null)
const input = ref(null)
let previousFocus

function cancel() { dialogStore.finish(null) }
function submit() { dialogStore.finish(value.value) }
function onKeydown(event) {
  if (event.key !== 'Tab' || !dialogStore.open) return
  const focusable = [...dialog.value.querySelectorAll('button:not(:disabled), input:not(:disabled)')]
  const first = focusable[0], last = focusable.at(-1)
  if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
  else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
}

watch(() => dialogStore.open, async open => {
  if (open) {
    previousFocus = document.activeElement
    value.value = dialogStore.initialValue
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    input.value?.focus()
    input.value?.select()
  } else {
    document.removeEventListener('keydown', onKeydown)
    previousFocus?.focus?.()
  }
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <div v-if="dialogStore.open" class="input-dialog-backdrop">
      <form ref="dialog" class="input-dialog" role="dialog" aria-modal="true" aria-labelledby="input-dialog-title" :aria-describedby="dialogStore.message ? 'input-dialog-description' : undefined" @submit.prevent="submit">
        <h2 id="input-dialog-title">{{ t(dialogStore.title) }}</h2>
        <p v-if="dialogStore.message" id="input-dialog-description">{{ t(dialogStore.message) }}</p>
        <label>{{ t(dialogStore.label) }}
          <input ref="input" v-model="value" :type="dialogStore.inputType" :min="dialogStore.min" :max="dialogStore.max" :step="dialogStore.step" :maxlength="dialogStore.maxLength" required autocomplete="off">
        </label>
        <footer><button type="button" @click="cancel">{{ t('Отмена') }}</button><button class="primary">{{ t(dialogStore.confirmLabel) }}</button></footer>
      </form>
    </div>
  </Teleport>
</template>

<style scoped>
.input-dialog-backdrop{position:fixed;z-index:1000;inset:0;display:grid;place-items:center;padding:16px;background:rgba(0,0,0,.76);backdrop-filter:blur(4px)}
.input-dialog{width:min(520px,100%);padding:24px;border:1px solid rgba(201,134,38,.48);background:#100b08;box-shadow:0 24px 80px rgba(0,0,0,.7)}
h2{margin:0;color:var(--cream)}p{margin:12px 0;color:var(--muted);line-height:1.6}label{display:grid;gap:8px;margin-top:18px;color:var(--muted);font-size:.78rem}input{width:100%}footer{display:flex;justify-content:flex-end;gap:8px;margin-top:22px}
</style>
