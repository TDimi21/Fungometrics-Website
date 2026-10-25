// Read only the page number from API links; never send credentials to a returned URL.
export function sessionPage(link) {
  if (!link?.url || link.active) return null;
  try {
    const page = Number(new URL(link.url, 'https://local.invalid').searchParams.get('page'));
    return Number.isInteger(page) && page > 0 ? page : null;
  } catch { return null; }
}

export async function playerSessionHistory(axiosGet, sessionType) {
  const endpoints = { B: 'batting', P: 'bullpen', C: 'cage', L: 'liveab', BS: 'liveab', LT: 'training', EV: 'training', WB: 'training' };
  const endpoint = endpoints[sessionType];
  if (!endpoint) return [];
  const params = ['LT', 'EV', 'WB'].includes(sessionType) ? { mode: sessionType } : {};
  const rows = [];
  let page = 1;
  while (true) {
    const response = await axiosGet(`player/sessions/${endpoint}`, { ...params, page });
    const data = response?.data?.data;
    if (!Array.isArray(data?.data)) throw Error('Invalid session response');
    rows.push(...data.data);
    if (!data.next_page_url) break;
    const next = sessionPage({ url: data.next_page_url });
    if (!next || next <= page) throw Error('Invalid session pagination');
    page = next;
  }
  return rows;
}
