export const copyEntries = (entries, from, to, count, totalDays, idFactory) =>
  ![from, to, count, totalDays].every(Number.isInteger) ||
  from < 0 ||
  to < 0 ||
  count < 1 ||
  totalDays < 1
    ? []
    : entries
        .filter((e) => e.day_offset >= from && e.day_offset < from + count)
        .map((e) => ({
          ...JSON.parse(JSON.stringify(e)),
          id: idFactory(),
          day_offset: to + e.day_offset - from,
        }))
        .filter((e) => e.day_offset >= 0 && e.day_offset < totalDays);
export const overlapWarnings = (entries) => {
  const warnings = [];
  const byPlayer = new Map();
  for (const e of entries) {
    if (!["High", "Maximum"].includes(e.snapshot?.intensity_label)) continue;
    for (const id of e.player_ids || []) {
      const days = byPlayer.get(id) || [];
      days.push(e.day_offset);
      byPlayer.set(id, days);
    }
  }
  for (const [id, days] of byPlayer) {
    days.sort((a, b) => a - b);
    if (days.some((d, i) => i > 0 && d - days[i - 1] <= 1))
      warnings.push(
        `Athlete ${id}: high-intensity workouts on the same or consecutive days. Review spacing before approval.`
      );
  }
  return warnings;
};
