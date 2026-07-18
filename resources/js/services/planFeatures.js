export function createPlanFeaturesApi(axiosGet, axiosPut) {
  return {
    async load() {
      const [plans, entitlements] = await Promise.all([
        axiosGet('/api/admin/billing/plans'),
        axiosGet('/api/admin/billing/entitlements'),
      ])
      return {
        ...plans.data.data,
        entitlements: entitlements.data.data.entitlements,
        coverage: entitlements.data.data.coverage || [],
        coverage_summary: entitlements.data.data.coverage_summary || null,
      }
    },
    async update(plan, payload) {
      const response = await axiosPut(`/api/admin/billing/plans/${encodeURIComponent(plan)}/entitlements`, payload)
      return response.data.data.plan
    },
  }
}
