function authoritativeData(response, label) {
  const contentType = response?.headers?.['content-type'] || response?.headers?.['Content-Type'] || ''
  const payload = response?.data?.data

  if (contentType.includes('text/html') || typeof response?.data === 'string' || !payload || typeof payload !== 'object' || Array.isArray(payload)) {
    throw new Error(`${label} returned HTML or invalid authoritative JSON.`)
  }

  return payload
}

export function createPlanFeaturesApi(axiosGet, axiosPut) {
  return {
    async load() {
      const [plans, entitlements] = await Promise.all([
        axiosGet('admin/billing/plans'),
        axiosGet('admin/billing/entitlements'),
      ])

      const plansData = authoritativeData(plans, 'Plans endpoint')
      const entitlementsData = authoritativeData(entitlements, 'Entitlements endpoint')

      if (!Array.isArray(plansData.plans) || !Array.isArray(entitlementsData.entitlements)) {
        throw new Error('Plan Features API returned an invalid authoritative matrix.')
      }

      return {
        ...plansData,
        entitlements: entitlementsData.entitlements,
        coverage: entitlementsData.coverage || [],
        coverage_summary: entitlementsData.coverage_summary || null,
      }
    },
    async update(plan, payload) {
      const response = await axiosPut(`admin/billing/plans/${encodeURIComponent(plan)}/entitlements`, payload)
      const data = authoritativeData(response, 'Plan update endpoint')

      if (!data.plan || typeof data.plan !== 'object' || Array.isArray(data.plan)) {
        throw new Error('Plan update endpoint returned invalid authoritative JSON.')
      }

      return data.plan
    },
  }
}
