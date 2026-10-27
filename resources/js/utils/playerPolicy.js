export function playerPolicyPayload(model) {
  if (!model?.policy_accepted || !model?.policy_info) throw new Error('Please read and check the player profile agreement before saving.')
  return {policy_accepted:true, terms_version:model.policy_info.terms_version, privacy_version:model.policy_info.privacy_version, acknowledgment_version:model.policy_info.acknowledgment_version}
}
export function appendPlayerPolicy(form, model) {
  for (const [key, value] of Object.entries(playerPolicyPayload(model))) form.set(key, value === true ? '1' : value)
}
