/**
 * Practice Planner helpers — timeline math, totals, plain-text export and local
 * persistence. Mirrors the mobile app's PracticeCreatorScreen logic so a coach
 * gets the same planner on the web. Plans are stored locally (localStorage),
 * exactly like the app stores them on the device.
 */

const STORE_KEY = 'fmtrx_saved_practices';

export function minutesToTimestamp(totalMinutes) {
  const t = Math.max(0, Math.round(Number(totalMinutes) || 0));
  const h = Math.floor(t / 60);
  const m = t % 60;
  return h > 0 ? `${h}h ${m}m` : `${m}m`;
}

/** Slots run top-to-bottom; blocks inside a slot run in parallel (max wins). */
export function buildTimeline(slots = []) {
  let cursor = 0;
  return (slots || []).map((slot) => {
    const duration = (slot.blocks || []).reduce((mx, b) => Math.max(mx, Number(b.minutes) || 0), 0);
    const start = cursor;
    cursor += duration;
    const fmt = (m) => `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`;
    return { ...slot, duration, startLabel: fmt(start), endLabel: fmt(cursor) };
  });
}

export const allBlocksOf = (slots = []) => (slots || []).flatMap((s) => s.blocks || []);

export const scheduledMinutesOf = (slots = []) =>
  (slots || []).reduce((sum, slot) => sum + (slot.blocks || []).reduce((mx, b) => Math.max(mx, Number(b.minutes) || 0), 0), 0);

const playerName = (p) =>
  `${p?.name?.first || p?.first_name || ''} ${p?.name?.last || p?.last_name || ''}`.trim() || (p?.name?.full || p?.name || '');

/** Plain-text version of the plan for copy / export. */
export function buildShareText(plan) {
  const { title = '', date = '', focus = '', totalDuration = '', notes = '', slots = [] } = plan || {};
  const tl = buildTimeline(slots);
  const blocks = allBlocksOf(slots);
  const lines = [
    `PRACTICE PLAN — ${String(title).toUpperCase()}`,
    `Date: ${date}   Focus: ${focus}   Planned: ${totalDuration} min`,
    '─'.repeat(42),
    '',
  ];
  tl.forEach((slot) => {
    lines.push(`${slot.startLabel} → ${slot.endLabel}  (${slot.duration} min)`);
    (slot.blocks || []).forEach((b) => {
      const lead = (slot.blocks || []).length > 1 ? '  ⟺ ' : '  • ';
      lines.push(`${lead}${b.name}  [${b.group}]  ${b.focus}`);
      if (b.location) lines.push(`       Location: ${b.location}`);
      if ((b.equipment || []).length) lines.push(`       Equipment: ${b.equipment.join(', ')}`);
      if (b.drillNotes) lines.push(`       Notes: ${b.drillNotes}`);
      if ((b.players || []).length) lines.push(`       Players: ${b.players.map(playerName).filter(Boolean).join(', ')}`);
    });
    lines.push('');
  });
  lines.push('─'.repeat(42));
  lines.push(`Total Drills: ${blocks.length}   Scheduled: ${minutesToTimestamp(scheduledMinutesOf(slots))}`);
  if (String(notes).trim()) lines.push(`Notes: ${notes}`);
  return lines.join('\n');
}

// ── Local persistence ────────────────────────────────────────────────────────
export function loadSavedPractices() {
  try {
    const raw = localStorage.getItem(STORE_KEY);
    const list = raw ? JSON.parse(raw) : [];
    return Array.isArray(list) ? list : [];
  } catch {
    return [];
  }
}

export function persistPractice(record) {
  const list = loadSavedPractices();
  const idx = list.findIndex((p) => p.id === record.id);
  const next = idx >= 0 ? list.map((p) => (p.id === record.id ? record : p)) : [record, ...list];
  try { localStorage.setItem(STORE_KEY, JSON.stringify(next)); } catch { /* storage full / unavailable */ }
  return next;
}

export function deletePractice(id) {
  const next = loadSavedPractices().filter((p) => p.id !== id);
  try { localStorage.setItem(STORE_KEY, JSON.stringify(next)); } catch { /* noop */ }
  return next;
}
