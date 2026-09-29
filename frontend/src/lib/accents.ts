import type { Accent, CourseIcon, Role } from '@/types'
import {
  BookOpen,
  Compass,
  FlaskConical,
  Globe2,
  Music4,
  Palette,
  Scale,
  Terminal,
} from 'lucide-vue-next'

export interface AccentTheme {
  name: Accent
  base: string
  soft: string
  glow: string
  gradient: string
  text: string
  ring: string
}

export const ACCENTS: Record<Accent, AccentTheme> = {
  violet: {
    name: 'violet',
    base: '#a78bfa',
    soft: 'rgba(167, 139, 250, 0.16)',
    glow: 'rgba(139, 92, 246, 0.4)',
    gradient: 'linear-gradient(135deg, #7c3aed 0%, #a78bfa 55%, #c4b5fd 100%)',
    text: '#ddd6fe',
    ring: 'rgba(167, 139, 250, 0.45)',
  },
  sky: {
    name: 'sky',
    base: '#38bdf8',
    soft: 'rgba(56, 189, 248, 0.15)',
    glow: 'rgba(14, 165, 233, 0.4)',
    gradient: 'linear-gradient(135deg, #0369a1 0%, #38bdf8 60%, #7dd3fc 100%)',
    text: '#bae6fd',
    ring: 'rgba(56, 189, 248, 0.45)',
  },
  emerald: {
    name: 'emerald',
    base: '#34d399',
    soft: 'rgba(52, 211, 153, 0.15)',
    glow: 'rgba(16, 185, 129, 0.4)',
    gradient: 'linear-gradient(135deg, #047857 0%, #34d399 60%, #6ee7b7 100%)',
    text: '#a7f3d0',
    ring: 'rgba(52, 211, 153, 0.45)',
  },
  amber: {
    name: 'amber',
    base: '#fbbf24',
    soft: 'rgba(251, 191, 36, 0.15)',
    glow: 'rgba(245, 158, 11, 0.4)',
    gradient: 'linear-gradient(135deg, #b45309 0%, #fbbf24 60%, #fde68a 100%)',
    text: '#fde68a',
    ring: 'rgba(251, 191, 36, 0.45)',
  },
  rose: {
    name: 'rose',
    base: '#fb7185',
    soft: 'rgba(251, 113, 133, 0.15)',
    glow: 'rgba(244, 63, 94, 0.4)',
    gradient: 'linear-gradient(135deg, #be123c 0%, #fb7185 60%, #fda4af 100%)',
    text: '#fecdd3',
    ring: 'rgba(251, 113, 133, 0.45)',
  },
  indigo: {
    name: 'indigo',
    base: '#818cf8',
    soft: 'rgba(129, 140, 248, 0.16)',
    glow: 'rgba(99, 102, 241, 0.4)',
    gradient: 'linear-gradient(135deg, #4338ca 0%, #818cf8 60%, #a5b4fc 100%)',
    text: '#c7d2fe',
    ring: 'rgba(129, 140, 248, 0.45)',
  },
}

export const ACCENT_KEYS = Object.keys(ACCENTS) as Accent[]

export function accentOf(accent: Accent | string | null | undefined): AccentTheme {
  return ACCENTS[(accent as Accent) ?? 'violet'] ?? ACCENTS.violet
}

export const COURSE_ICONS: Record<CourseIcon, typeof BookOpen> = {
  book: BookOpen,
  flask: FlaskConical,
  code: Terminal,
  palette: Palette,
  globe: Globe2,
  music: Music4,
  scale: Scale,
  compass: Compass,
}

export const COURSE_ICON_KEYS = Object.keys(COURSE_ICONS) as CourseIcon[]

export function courseIcon(icon: CourseIcon | string | null | undefined) {
  return COURSE_ICONS[(icon as CourseIcon) ?? 'book'] ?? BookOpen
}

export const ROLE_LABEL: Record<Role, string> = {
  admin: 'Administrator',
  teacher: 'Teacher',
  student: 'Student',
}

export const ROLE_TONE: Record<Role, Accent> = {
  admin: 'amber',
  teacher: 'violet',
  student: 'sky',
}
