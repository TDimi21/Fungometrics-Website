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
    sessionTypes: ['Cage', 'Batting Practice'],
  },
  {
    key: 'rapsodo',
    name: 'Rapsodo',
    initials: 'RA',
    fileTypes: ['xlsx'],
    description: 'Pitching and hitting performance measurements.',
    sessionTypes: ['Bullpen', 'Pitching Practice', 'Assessment'],
  },
  {
    key: 'blast-motion',
    name: 'Blast Motion',
    initials: 'BM',
    fileTypes: ['csv'],
    description: 'Bat-sensor swing metrics and movement data.',
    sessionTypes: ['Cage', 'Batting Practice', 'Assessment'],
  },
  {
    key: 'generic-csv',
    name: 'Generic Spreadsheet',
    initials: 'CSV',
    fileTypes: ['csv', 'xlsx', 'tsv'],
    description: 'A flexible starting point for other baseball data sources.',
    sessionTypes: ['Cage', 'Live AB', 'Bullpen', 'Strength', 'Mobility', 'Assessment', 'Batting Practice', 'Pitching Practice'],
  },
]

export const DATA_HUB_SESSION_TYPES = [
  'Live AB',
  'Cage',
  'Batting Practice',
  'Bullpen',
  'Pitching Practice',
  'Long Toss',
  'Weighted Balls',
  'Exit Velocity',
  'Assessment',
  'Strength',
  'Mobility',
  'Speed & Agility',
  'Recovery',
]

export const DATA_HUB_DESTINATION_GROUPS = [
  { label: 'Game & Competition', sessionTypes: ['Live AB'] },
  { label: 'Hitting', sessionTypes: ['Cage', 'Batting Practice'] },
  { label: 'Pitching', sessionTypes: ['Bullpen', 'Pitching Practice'] },
  { label: 'Throwing', sessionTypes: ['Long Toss', 'Weighted Balls'] },
  { label: 'Performance Testing', sessionTypes: ['Exit Velocity', 'Assessment', 'Strength', 'Mobility', 'Speed & Agility', 'Recovery'] },
]
