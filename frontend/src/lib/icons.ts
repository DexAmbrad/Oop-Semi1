import {
  BookOpen,
  ClipboardList,
  Clock,
  GraduationCap,
  Sparkles,
  Trophy,
  Users,
  Check,
  BadgeCheck,
} from 'lucide-vue-next'
import type { Component } from 'vue'

const ICONS: Record<string, Component> = {
  users: Users,
  badge: BadgeCheck,
  graduation: GraduationCap,
  book: BookOpen,
  clock: Clock,
  clipboard: ClipboardList,
  sparkles: Sparkles,
  check: Check,
  trophy: Trophy,
}

export function iconFor(name: string): Component {
  return ICONS[name] ?? Sparkles
}
