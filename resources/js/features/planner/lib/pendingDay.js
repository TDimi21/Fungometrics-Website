// Preserve pending input while accepting completion owned by canonical sessions.
export function mergePendingPlannerDay(server, pending) {
  if (!pending?.pending) return server;
  const items = { ...pending.progress.items };
  for (const [id, actual] of Object.entries(server.actual_results || {})) {
    if (actual.session_id)
      items[id] = {
        ...items[id],
        session_id: actual.session_id,
        session_status: actual.session_status,
        done: actual.done,
      };
  }
  return {
    ...pending,
    actual_results: server.actual_results,
    session_links: server.session_links,
    progress: { ...pending.progress, items },
  };
}
