export function plannerSessionReturn(storage, sessionId, userId, fallback) {
  try {
    const context = JSON.parse(
      storage.getItem("fmtrx-planner-session-return") || "null"
    );
    if (context?.session_id !== sessionId || context.user_id !== userId)
      return fallback;
    storage.removeItem("fmtrx-planner-session-return");
    return { path: "/player-dashboard", query: { tab: "workout" } };
  } catch {
    return fallback;
  }
}
