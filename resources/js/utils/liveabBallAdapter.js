// Adapter: maps fungoweb's `ball_x_ball` rows (LiveABPracticeResult + nested
// batting/pitching relations) into the flat shape the FMTRX app's scoring logic
// expects (ball.total_bases, ball.contact_quality, ...).
//
// Field/value maps below were verified against the live DB:
//   - bases (numeric 1-8, 0 = mid-AB)        -> total_bases  (0 => '' so it is
//     not counted as a completed PA)
//   - count_b_s ('0-0' B-S string)           -> pitch_count
//   - batting.quality_of_contact (W/H/A/N/MF)-> contact_quality (Weak/Hard/...)
//   - batting.type_of_hit (GB/LD/FB/SM/TK)   -> contact_trajectory
//   - pitching.type_throw (FB/CB/SL/CH)      -> pitch_type
//   - is_strike (bool)                       -> is_strike

const QUALITY_MAP = {
  W: 'Weak',
  A: 'Average',
  H: 'Hard',
  MF: 'Mishit',
  N: '', // no contact (take / swing-miss)
}

// type_of_hit codes already line up with what the app's logic reads
// (LD / GB / FB / SM); TK (take) is not a batted-ball trajectory.
const TRAJECTORY_MAP = {
  GB: 'GB',
  LD: 'LD',
  FB: 'FB',
  SM: 'SM',
  TK: '',
}

const fullName = (profile) =>
  profile ? `${profile.first_name ?? ''} ${profile.last_name ?? ''}`.trim() : ''

export function adaptBall(row) {
  const batting = row?.batting ?? {}
  const pitching = row?.pitching ?? {}
  const basesNum = Number(row?.bases)
  const isInProgress = !Number.isFinite(basesNum) || basesNum === 0

  return {
    // identity
    batter_id: batting.batter_id ?? null,
    pitcher_id: pitching.pitcher_id ?? null,
    batter_name: fullName(batting.profile),
    pitcher_name: fullName(pitching.profile),

    // outcome — keep numeric base code as the app's normalizer understands it,
    // but blank out mid-AB pitches so they are not counted as completed PAs.
    total_bases: isInProgress ? '' : String(row.bases),
    play_result: row?.play_result ?? null,

    // count / pitch
    pitch_count: row?.count_b_s ?? '0-0',
    pitch_type: pitching.type_throw ?? '',
    is_strike: row?.is_strike ?? null,

    // contact
    contact_quality: QUALITY_MAP[batting.quality_of_contact] ?? '',
    contact_trajectory: TRAJECTORY_MAP[batting.type_of_hit] ?? '',

    // velocities
    velocity: batting.velocity ?? null,
    pitch_velocity: pitching.miles_per_hour ?? null,

    // keep the raw row for anything not yet mapped
    _raw: row,
  }
}

export function adaptBallByBall(ballByBall = []) {
  return (ballByBall ?? []).map(adaptBall)
}
