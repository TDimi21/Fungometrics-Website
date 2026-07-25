import {
  DATA_HUB_EXTENSIONS,
  DATA_HUB_MAX_FILE_SIZE_BYTES,
  DATA_HUB_MIME_TYPES,
  fileExtension,
  platformSupportsFile,
} from '@/data/dataHubConfig.js'

export const validateDataHubFile = (file, platform = null) => {
  if (!file) return { valid: false, error: '', warning: '' }

  const extension = fileExtension(file)
  if (!DATA_HUB_EXTENSIONS.includes(extension)) {
    return { valid: false, error: 'Choose a supported CSV, XLSX, or TSV spreadsheet.', warning: '' }
  }
  if (file.size === 0) {
    return {
      valid: false,
      error: 'The selected file is empty. Choose a file containing data.',
      warning: '',
    }
  }
  if (file.size > DATA_HUB_MAX_FILE_SIZE_BYTES) {
    return {
      valid: false,
      error: `The selected file is larger than the ${Math.round(DATA_HUB_MAX_FILE_SIZE_BYTES / 1024 / 1024)} MB Phase 1 limit.`,
      warning: '',
    }
  }
  if (platform && !platformSupportsFile(platform, file)) {
    return {
      valid: false,
      error: `${platform.name} does not support ${extension.toUpperCase()} files.`,
      warning: '',
    }
  }

  const unexpectedMime = file.type && !DATA_HUB_MIME_TYPES[extension]?.includes(file.type)
  return {
    valid: true,
    error: '',
    warning: unexpectedMime
      ? 'The browser reported an unexpected file type. The extension is accepted, but the file will require server validation in a future phase.'
      : '',
  }
}

export const nextDataHubStep = (step, state) => {
  const allowed = {
    1: Boolean(state.platform),
    2: Boolean(state.file && state.fileValid),
    3: Boolean(state.team && state.sessionType),
  }
  if (step >= 4) return 4
  return allowed[step] ? step + 1 : step
}
