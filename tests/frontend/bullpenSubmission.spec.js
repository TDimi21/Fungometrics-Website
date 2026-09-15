import {describe, it, expect, vi} from 'vitest';
import {createBullpenSubmission} from '../../resources/js/utils/bullpenSubmission';

describe('bullpen retry identity', () => {
  it('retries a lost response with the same key and allocates a new key for the next real pitch', async () => {
    const post = vi.fn().mockRejectedValueOnce(new Error('timeout')).mockImplementation(async (_, data) => ({
      data: {status: 'success', client_request_id: data.client_request_id, data: {id: 'saved'}},
    }));
    const nextId = vi.fn().mockReturnValueOnce('pitch-a').mockReturnValueOnce('pitch-b');
    const send = createBullpenSubmission(post, nextId);
    await expect(send({miles_per_hour: 80})).rejects.toThrow('timeout');
    await send({miles_per_hour: 80});
    await send({miles_per_hour: 80});
    expect(post.mock.calls.map(call => call[1].client_request_id)).toEqual(['pitch-a', 'pitch-a', 'pitch-b']);
  });
  it('preserves an ambiguous pitch instead of submitting changed details under its identity', async () => {
    const post = vi.fn().mockRejectedValue(new Error('timeout'));
    const send = createBullpenSubmission(post, () => 'pitch-a');
    await expect(send({miles_per_hour: 80})).rejects.toThrow();
    await expect(send({miles_per_hour: 90})).rejects.toThrow('Retry the unsaved pitch');
    expect(post).toHaveBeenCalledTimes(1);
  });
  it('does not treat a response without a receipt as saved', async () => {
    const send = createBullpenSubmission(vi.fn().mockResolvedValue({data: {status: 'success'}}), () => 'pitch-a');
    await expect(send({})).rejects.toThrow('confirmation is pending');
  });
});
