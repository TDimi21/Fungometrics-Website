<template>
  <div class="workout-builder-layout">
    <div class="workout-builder-fields"><slot /></div>
    <aside class="workout-builder-preview" aria-label="Live workout preview">
      <div class="live-preview-heading"><span class="live-dot"></span> Live workout preview</div>
      <p class="live-preview-hint">Updates as you build. Your changes are saved when you save the workout.</p>
      <slot name="preview" />
    </aside>
  </div>
</template>
<style scoped>
.workout-builder-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,390px);align-items:start;gap:24px;width:100%}.workout-builder-fields{min-width:0}.workout-builder-preview{position:sticky;top:16px;min-width:0;max-height:calc(100dvh - 32px);overflow-y:auto;overscroll-behavior:contain;padding:16px;border:1px solid #294158;border-radius:14px;background:#081523}.live-preview-heading{display:flex;align-items:center;gap:8px;color:#edf5ff;font-weight:800;font-size:16px}.live-dot{width:7px;height:7px;border-radius:50%;background:#50dba0}.live-preview-hint{color:#9ebbd6;font-size:12px;line-height:1.5;margin:8px 0}.workout-builder-preview :deep(.preview-caption){display:none}.workout-builder-preview :deep(.player-preview-wrap){margin:16px 0 0}.workout-builder-preview :deep(.player-phone){padding:14px 10px;border-radius:14px;box-shadow:none}.workout-builder-preview :deep(.player-plan){padding:12px}.workout-builder-preview :deep(.player-plan h2){font-size:24px}@media(max-width:1050px){.workout-builder-layout{grid-template-columns:minmax(0,1fr)}.workout-builder-preview{position:static;max-height:none;overflow:visible}.workout-builder-preview :deep(.preview-caption){display:block}}
</style>
