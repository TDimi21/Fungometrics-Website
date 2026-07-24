const configuredMaxSize = typeof document === 'undefined'
  ? null
  : document.querySelector('meta[name="fmtrx-data-hub-max-file-size"]')?.content

export const DATA_HUB_MAX_FILE_SIZE_BYTES = Number(configuredMaxSize || 25 * 1024 * 1024)

export const DATA_HUB_EXTENSIONS = ['csv', 'xlsx']

export const DATA_HUB_MIME_TYPES = {
  csv: ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
  xlsx: [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip',
  ],
}

export const fileExtension = file =>
  String(file?.name || '').split('.').pop()?.toLowerCase() || ''

export const platformSupportsFile = (platform, file) =>
  Boolean(platform && file && platform.fileTypes.includes(fileExtension(file)))
