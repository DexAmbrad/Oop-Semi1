import dayjs from 'dayjs'
import relativeTime from 'dayjs/plugin/relativeTime'
import calendar from 'dayjs/plugin/calendar'
import isSameOrBefore from 'dayjs/plugin/isSameOrBefore'
import isSameOrAfter from 'dayjs/plugin/isSameOrAfter'

dayjs.extend(relativeTime)
dayjs.extend(calendar)
dayjs.extend(isSameOrBefore)
dayjs.extend(isSameOrAfter)

export { dayjs }

export function fromNow(value: dayjs.ConfigType): string {
  if (!value) return '—'
  return dayjs(value).fromNow()
}

export function formatDate(value: dayjs.ConfigType, template = 'DD MMM YYYY'): string {
  if (!value) return '—'
  return dayjs(value).format(template)
}

export function formatDateTime(value: dayjs.ConfigType): string {
  if (!value) return '—'
  return dayjs(value).format('DD MMM YYYY · HH:mm')
}

export function formatDue(value: dayjs.ConfigType): string {
  if (!value) return 'No due date'
  const date = dayjs(value)
  const now = dayjs()

  if (date.isSame(now, 'day')) return `Today, ${date.format('HH:mm')}`
  if (date.isSame(now.add(1, 'day'), 'day')) return `Tomorrow, ${date.format('HH:mm')}`
  if (date.isSame(now.subtract(1, 'day'), 'day')) return `Yesterday, ${date.format('HH:mm')}`

  return date.format('ddd D MMM · HH:mm')
}

export function relativeDue(value: dayjs.ConfigType): string {
  if (!value) return '—'
  return dayjs(value).fromNow()
}

export function isOverdue(value: dayjs.ConfigType): boolean {
  if (!value) return false
  return dayjs(value).isBefore(dayjs())
}

export function initialsOf(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}

export function percentageTone(value: number | null | undefined): string {
  if (value === null || value === undefined) return '#8f8fa8'
  if (value >= 90) return '#34d399'
  if (value >= 80) return '#38bdf8'
  if (value >= 70) return '#fbbf24'
  return '#fb7185'
}

export function titleCase(value: string): string {
  return value
    .replace(/[_-]/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase())
}

export function truncate(value: string, length = 140): string {
  if (value.length <= length) return value
  return `${value.slice(0, length).trimEnd()}…`
}

export function humanAction(action: string): string {
  return titleCase(action.split('.').join(' · '))
}
