// Keep the same payload and identity until the server confirms the pitch.
export function createBullpenSubmission(post, createId = () => crypto.randomUUID()) {
  let pending = null;
  return async payload => {
    const signature = JSON.stringify(payload);
    if (pending && pending.signature !== signature) {
      throw new Error('Retry the unsaved pitch before changing its details.');
    }
    if (!pending) pending = {signature, data: {...payload, client_request_id: createId()}};
    try {
      const response = await post('result/bullpen', pending.data);
      if (response?.data?.status !== 'success' || !response.data.data?.id
        || response.data.client_request_id !== pending.data.client_request_id) {
        throw new Error('Pitch confirmation is pending. Retry to confirm it.');
      }
      pending = null;
      return response;
    } catch (error) {
      // A validation rejection has not recorded a pitch; allow correction.
      if (error?.response?.status === 422) pending = null;
      throw error;
    }
  };
}
