const destinationDomains = {
  'Live AB': ['hitting', 'pitching', 'game_outcome', 'session_context'],
  Cage: ['hitting', 'session_context'],
  'Batting Practice': ['hitting', 'session_context'],
  Bullpen: ['pitching', 'session_context'],
  'Pitching Practice': ['pitching', 'session_context'],
  'Long Toss': ['throwing', 'session_context'],
  'Weighted Balls': ['throwing', 'session_context'],
  'Exit Velocity': ['hitting', 'session_context'],
  Assessment: ['assessment', 'hitting', 'pitching', 'throwing', 'strength', 'mobility', 'speed_agility', 'body_composition', 'recovery', 'session_context'],
  Strength: ['strength', 'body_composition', 'speed_agility', 'session_context'],
  Mobility: ['mobility', 'session_context'],
  'Speed & Agility': ['speed_agility', 'strength', 'session_context'],
  Recovery: ['recovery', 'session_context'],
}

export const compatibilityForConcept = (destination, concept, domains = []) => {
  if (!concept) return { level: 'not_importing', reason: 'No Baseball Concept connected.' }
  const domain = domains.find(item => item.id === concept.domain_id)?.key
  const compatible = destinationDomains[destination] || []
  if (compatible.includes(domain)) return { level: 'compatible', reason: `${concept.display_name} is compatible with ${destination}.` }
  if (domain === 'session_context') return { level: 'compatible', reason: 'Session context supports every destination.' }
  if (['pitching', 'game_outcome'].includes(domain) && ['Cage', 'Batting Practice'].includes(destination)) {
    return { level: 'warning', reason: `${concept.display_name} is source context inside a hitting session; confirm the HitTrax meaning before importing.` }
  }
  if (['assessment', 'body_composition', 'speed_agility'].includes(domain)) {
    return { level: 'warning', reason: `${concept.display_name} is unusual for ${destination}; confirm this context is intentional.` }
  }
  return { level: 'incompatible', reason: `${concept.display_name} is not compatible with ${destination}. Choose another concept or Not Importing.` }
}

export const rankConceptsForDestination = (concepts, destination, domains = []) =>
  [...concepts].sort((left, right) => {
    const score = concept => ({ compatible: 0, warning: 1, incompatible: 2 }[compatibilityForConcept(destination, concept, domains).level] ?? 3)
    return score(left) - score(right) || left.display_name.localeCompare(right.display_name)
  })
