# Hall of Fame TV soak procedure

This is the release-gate procedure for the Hall of Fame wall. Run it for at least
two continuous hours (four preferred) on the facility browser and display that
will be used in service. Use a dedicated coach account with `coach_pro` access
and a non-production test team containing real-looking, non-sensitive fixtures.

## Before the run

1. Use Node 22 and the production web build.
2. Open browser Task Manager and record the Hall of Fame tab's memory footprint.
3. Open DevTools Network and Console with log preservation enabled.
4. Record the team, start time, browser version, display resolution, and starting
   memory below. Do not record account credentials or player personal data.
5. Confirm the wall begins on the selected team, changes category every five
   seconds, and the countdown never becomes negative.

## Exercise schedule

- Minutes 0–20: enter fullscreen and observe uninterrupted five-second rotation.
- Minutes 20–35: leave and re-enter fullscreen five times. Confirm one transition
  and one countdown tick per interval; no duplicate fullscreen handlers.
- Minutes 35–55: switch between two authorized teams ten times. Confirm no name,
  score, photo, or response from the previous team survives the switch.
- Minutes 55–75: background the tab for five minutes, then foreground it. Confirm
  the wall resumes with one timer and a valid countdown.
- Minutes 75–95: interrupt networking for two minutes. Confirm an explicit error,
  no stale cross-team grant, and successful recovery after networking returns.
- Minutes 95–115: remove `performance_overview` from the test plan through the
  protected admin workflow. Confirm the wall disappears and stale in-flight data
  cannot render. Restore the entitlement with an audit reason and confirm reload.
- Remaining time: leave fullscreen rotation running. Check the tab every 15
  minutes for frozen countdowns, repeated requests, console errors, listener
  duplication, visual corruption, or increasing memory.

## Acceptance record

| Field | Result |
| --- | --- |
| Date / operator | Pending |
| Browser / version | Pending |
| Display / resolution | Pending |
| Duration | Pending (minimum 2 hours) |
| Starting memory | Pending |
| Ending memory | Pending |
| Peak memory | Pending |
| Fullscreen cycles | Pending |
| Team switches | Pending |
| Background / foreground | Pending |
| Network loss / recovery | Pending |
| Entitlement revoke / restore | Pending |
| Duplicate intervals/listeners | Pending |
| Frozen countdowns | Pending |
| Failed or repeated requests | Pending |
| Final verdict | Pending |

The soak passes only when every scenario succeeds, there are no duplicate timers
or listeners, and memory stabilizes rather than growing throughout the run.
