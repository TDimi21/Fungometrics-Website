import {describe, it, expect, vi, afterEach} from 'vitest'
import {validateVideoDuration, readVideoDuration} from '../../resources/js/features/video/videoDuration.js'
describe('video duration limit', () => {
  it.each([0.1, 14, 14.999])('accepts %s seconds', value => expect(() => validateVideoDuration(value)).not.toThrow())
  it.each([15, 15.001, 30, Infinity, NaN, 0, -1])('rejects %s seconds', value => expect(() => validateVideoDuration(value)).toThrow())
  afterEach(() => vi.unstubAllGlobals())
  it('reads metadata and releases the temporary file URL', async () => {
    const video = {duration: 14.9, removeAttribute: vi.fn(), load: vi.fn()}
    const revokeObjectURL = vi.fn()
    vi.stubGlobal('document', {createElement: () => video})
    vi.stubGlobal('URL', {createObjectURL: () => 'blob:test', revokeObjectURL})
    const pending = readVideoDuration({})
    video.onloadedmetadata()
    await expect(pending).resolves.toBe(14.9)
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:test')
  })
})
