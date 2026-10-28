export const validateVideoDuration = duration => {
  if (!Number.isFinite(duration) || duration <= 0) throw new Error('Could not read the clip length. Choose another video.')
  if (duration >= 15) throw new Error('Clips must be shorter than 15 seconds. Trim your video and choose it again.')
}

export const readVideoDuration = file => new Promise((resolve, reject) => {
  const video = document.createElement('video')
  const url = URL.createObjectURL(file)
  const cleanup = () => {
    clearTimeout(timeout)
    video.onloadedmetadata = null
    video.onerror = null
    video.removeAttribute('src')
    video.load()
    URL.revokeObjectURL(url)
  }
  const fail = () => { cleanup(); reject(new Error('Could not read this video’s length. Try an MP4 clip shorter than 15 seconds.')) }
  const timeout = setTimeout(fail, 15000)
  video.preload = 'metadata'
  video.onloadedmetadata = () => {
    const duration = video.duration
    cleanup()
    try { validateVideoDuration(duration); resolve(duration) } catch (error) { reject(error) }
  }
  video.onerror = fail
  video.src = url
})
