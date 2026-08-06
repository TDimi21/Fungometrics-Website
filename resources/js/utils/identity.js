// Single place to resolve "which player / team is this user" from the
// persisted user store payload, whose shape varies by login path (some logins
// nest everything under `user`, some expose `player` / `team` at the top).

export const resolvePlayerId = (userData) =>
  userData?.player?.id ||
  userData?.user?.player?.id ||
  userData?.user?.id ||
  userData?.id ||
  null

export const resolveTeamId = (userData) =>
  userData?.team?.id_team ||
  userData?.team?.id ||
  userData?.user?.team?.id_team ||
  userData?.user?.team?.id ||
  null
