const THEME_KEY = 'ui_theme'
const DEFAULT_THEME = 'dark'

export const getUiTheme = () => {
  if (typeof window === 'undefined') return DEFAULT_THEME
  const saved = window.localStorage.getItem(THEME_KEY)
  return saved === 'light' || saved === 'dark' ? saved : DEFAULT_THEME
}

export const applyUiTheme = (theme) => {
  if (typeof document === 'undefined') return
  const next = theme === 'light' ? 'light' : 'dark'
  document.documentElement.setAttribute('data-ui-theme', next)
  window.localStorage.setItem(THEME_KEY, next)
  window.dispatchEvent(new CustomEvent('ui-theme-changed', { detail: { theme: next } }))
}
