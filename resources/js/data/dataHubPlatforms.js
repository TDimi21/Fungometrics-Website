export const DATA_HUB_PLATFORMS = [
  {
    key: 'trackman',
    name: 'TrackMan',
    initials: 'TM',
    fileTypes: ['csv', 'xlsx'],
    description: 'Ball-flight, batting, pitching, and game-session data.',
    sessionTypes: ['Cage', 'Live AB', 'Batting Practice', 'Pitching Practice'],
  },
  {
    key: 'hittrax',
    name: 'HitTrax',
    initials: 'HT',
    fileTypes: ['csv', 'xlsx'],
    description: 'Indoor hitting, game, and batted-ball session data.',
    sessionTypes: ['Cage', 'Live AB', 'Batting Practice'],
  },
  {
    key: 'rapsodo',
    name: 'Rapsodo',
    initials: 'RA',
    fileTypes: ['csv', 'xlsx'],
    description: 'Pitching and hitting performance measurements.',
    sessionTypes: ['Cage', 'Bullpen', 'Batting Practice', 'Pitching Practice'],
  },
  {
    key: 'blast-motion',
    name: 'Blast Motion',
    initials: 'BM',
    fileTypes: ['csv', 'xlsx'],
    description: 'Bat-sensor swing metrics and movement data.',
    sessionTypes: ['Cage', 'Batting Practice', 'Assessment'],
  },
  {
    key: 'generic-csv',
    name: 'Generic CSV',
    initials: 'CSV',
    fileTypes: ['csv', 'xlsx'],
    description: 'A flexible starting point for other baseball data sources.',
    sessionTypes: ['Cage', 'Live AB', 'Bullpen', 'Strength', 'Mobility', 'Assessment', 'Batting Practice', 'Pitching Practice'],
  },
]

export const DATA_HUB_SESSION_TYPES = [
  'Cage',
  'Live AB',
  'Bullpen',
  'Strength',
  'Mobility',
  'Assessment',
  'Batting Practice',
  'Pitching Practice',
]
