import {describe, it, expect} from 'vitest'
import {AxiosHeaders} from 'axios'
import {prepareApiRequest, apiBaseUrl} from '../../resources/js/utils/apiTransport.js'
const origin = new URL(apiBaseUrl, 'https://app.example.com').origin
const apiPath = new URL(apiBaseUrl, origin).pathname
describe('cookie API transport', () => {
  it.each(['Bearer', 'Bearer undefined', 'Bearer null', 'Bearer [object Object]'])('removes %s so the backend can use its session cookie', value => {
    const config = prepareApiRequest({url: apiBaseUrl + 'player-videos/context', headers: new AxiosHeaders({Authorization: value})}, origin)
    expect(config.headers.get('Authorization')).toBeUndefined()
    expect(config.withCredentials).toBe(true)
  })
  it('preserves real restricted bearer credentials', () => {
    const config = prepareApiRequest({url: apiBaseUrl + 'player/join', headers: {Authorization: 'Bearer real-token'}}, origin)
    expect(config.headers.Authorization).toBe('Bearer real-token')
  })
  it('does not enable credential sharing to external services', () => {
    const config = prepareApiRequest({url: 'https://external.example.com/api/files', headers: {}}, origin)
    expect(config.withCredentials).toBeUndefined()
  })
  it('handles relative Axios base URLs', () => {
    expect(prepareApiRequest({url: 'me/access', baseURL: apiPath}, origin).withCredentials).toBe(true)
  })
})
