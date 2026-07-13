/**
 * fieldMark.js — convert a batting-practice tapped hit location into a spray/depth
 * the field can plot (ported from the app). Batting practice doesn't record spray
 * or distance — the coach taps an 80×80 field grid, stored as `field_mark`
 * (idx = rh + (rw-1)*80). We turn that tap into spray_angle + distance_travel so the
 * velocity spray field can show batting hits. `velocity` is the real exit velocity.
 */
import { fenceAt, configFromLevel } from './ballFieldGeometry';

const HS_FENCES = configFromLevel('hs').fences;

// Nominal launch angle per trajectory code, used to bucket the filters.
const launchFromTraj = (t) => {
  const s = String(t || '').toUpperCase();
  if (s === 'PF') return 55; // pop fly
  if (s === 'FB') return 30; // fly ball
  if (s === 'LD') return 15; // line drive
  return 3; // GB / default
};

export function fieldMarkToBall(ball, config) {
  const fences = (config && config.fences) || HS_FENCES;
  const launch = launchFromTraj(ball.trajectory ?? ball.type_of_hit);
  const idx = Number(ball.point ?? ball.field_mark);
  const velocity = Number(ball.velocity ?? ball.exit_velocity ?? ball.launch_angle_velocity) || 0;
  const trajectory = String(ball.trajectory ?? ball.type_of_hit ?? '').toUpperCase();
  if (!idx || idx <= 0 || idx > 6400) {
    return { spray_angle: 0, launch_angle: launch, distance_travel: 0, velocity, trajectory };
  }
  const rw = Math.floor((idx - 1) / 80) + 1; // 1..80 horizontal
  const rh = ((idx - 1) % 80) + 1;           // 1..80 vertical (1 = deep, 80 = home)
  const u = (rw - 0.5) / 80;                 // 0 left .. 1 right
  const v = (rh - 0.5) / 80;                 // 0 deep .. 1 home
  const spray = (u - 0.5) * 90;              // ±45 across the fair field
  const fence = fenceAt(Math.max(-45, Math.min(45, spray)), fences) || 330;
  const distance = Math.max(0, (1 - v) * fence);
  return { spray_angle: spray, launch_angle: launch, distance_travel: distance, velocity, trajectory };
}

export const fieldMarkToBalls = (balls, config) => (balls || []).map((b) => fieldMarkToBall(b, config));
