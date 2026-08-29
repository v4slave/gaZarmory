<script setup>
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 18 },
})

const paths = {
  bell: ['M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9', 'M10 21h4'],
  home: ['M3 11 12 3l9 8', 'M5 10v10h14V10', 'M9 20v-6h6v6'],
  users: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8', 'M22 21v-2a4 4 0 0 0-3-3.87', 'M16 3.13a4 4 0 0 1 0 7.75'],
  groups: ['M4 6h6v6H4z', 'M14 4h6v6h-6z', 'M9 15h6v6H9z', 'M7 12v2.5h5', 'M17 10v2.5h-5'],
  sword: ['m14.5 4.5 5-2-2 5-8 8-3-3z', 'm5 13-2 2 6 6 2-2', 'm14 10 4 4'],
  play: ['M8 5v14l11-7z'],
  treasury: ['M4 7h16v13H4z', 'M8 7V4h8v3', 'M4 11h16'],
  auction: ['M4 19h16', 'm9 5 6 6', 'm7 7 6 6', 'm14 4 2-2 4 4-2 2', 'm6 12-2 2 4 4 2-2'],
  payout: ['M5 8c0-3 3-5 7-5s7 2 7 5v8c0 3-3 5-7 5s-7-2-7-5z', 'M8 10h8', 'M8 14h6'],
  readiness: ['M12 3 20 7v5c0 5-3.4 8-8 10-4.6-2-8-5-8-10V7z', 'm8 12 2.5 2.5L16 9'],
  attendance: ['M3 3v18h18', 'm7 15 3-3 3 2 5-6'],
  reconcile: ['M4 6h16', 'M7 3v6', 'M17 3v6', 'M6 13h4', 'm14 12-5 5-2-2'],
  settings: ['M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7', 'M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6 1.7 1.7 0 0 0 10 3v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z'],
  check: ['m5 12 4 4L19 6'],
  warning: ['M12 3 2 21h20z', 'M12 9v4', 'M12 17h.01'],
  error: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20', 'm15 9-6 6', 'm9 9 6 6'],
  info: ['M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20', 'M12 11v6', 'M12 7h.01'],
  close: ['m6 6 12 12', 'M18 6 6 18'],
}
</script>

<template>
  <svg class="app-icon" :width="size" :height="size" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    <path v-for="path in paths[name] ?? paths.info" :key="path" :d="path" />
  </svg>
</template>
