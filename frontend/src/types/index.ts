export type Role = 'admin' | 'teacher' | 'student'

export type Accent = 'violet' | 'sky' | 'emerald' | 'amber' | 'rose' | 'indigo'

export type CourseIcon = 'book' | 'flask' | 'code' | 'palette' | 'globe' | 'music' | 'scale' | 'compass'

export interface User {
  id: number
  name: string
  email: string
  role: Role
  initials: string
  avatar_path: string | null
  headline: string | null
  phone?: string | null
  status: 'active' | 'suspended'
  is_active: boolean
  last_seen_at: string | null
  created_at: string | null
  courses_count?: number
  taught_courses_count?: number
  submissions_count?: number
}

export interface Course {
  id: number
  code: string
  title: string
  subject: string | null
  description: string | null
  room_code: string
  accent: Accent
  icon: CourseIcon
  term: string | null
  status: 'active' | 'archived'
  capacity: number
  starts_at: string | null
  ends_at: string | null
  teacher: User | null
  students_count: number
  assignments_count: number
  announcements_count: number
  materials_count: number
  roster?: { id: number; name: string; email: string; initials: string; role_in_course: string }[]
  my_enrollment: { is_enrolled: boolean; role: string | null }
  is_teacher: boolean
  is_admin: boolean
  created_at: string | null
}

export interface MySubmission {
  id: number
  status: SubmissionStatus
  body: string | null
  link_url: string | null
  file_name: string | null
  submitted_at: string | null
  score: number | null
  feedback: string | null
  is_late: boolean
  graded_at: string | null
}

export type AssignmentType = 'assignment' | 'quiz' | 'project' | 'reading'
export type SubmissionStatus = 'draft' | 'submitted' | 'late' | 'graded' | 'returned'

export interface Assignment {
  id: number
  course_id: number
  title: string
  summary: string | null
  instructions: string | null
  type: AssignmentType
  max_points: number
  due_at: string | null
  allow_late: boolean
  published_at: string | null
  status: 'published' | 'draft'
  is_overdue: boolean
  is_due_soon: boolean
  author: User | null
  course: Course | null
  submissions_count: number
  graded_count?: number
  pending_count?: number
  my_submission: MySubmission | null
  created_at: string | null
}

export interface Submission {
  id: number
  assignment_id: number
  course_id: number
  user: User | null
  body: string | null
  link_url: string | null
  file_name: string | null
  has_file: boolean
  file_url: string | null
  status: SubmissionStatus
  submitted_at: string | null
  score: number | null
  feedback: string | null
  is_late: boolean
  is_graded: boolean
  grader: User | null
  graded_at: string | null
  assignment?: { id: number; title: string; max_points: number; due_at: string | null }
}

export interface AnnouncementComment {
  id: number
  body: string
  user: User | null
  created_at: string | null
}

export interface Announcement {
  id: number
  course_id: number
  title: string
  body: string
  category: 'general' | 'schedule' | 'resource' | 'event' | 'urgent'
  pinned: boolean
  published_at: string | null
  author: User | null
  course: Course | null
  comments: AnnouncementComment[]
  comments_count: number
  created_at: string | null
}

export interface Material {
  id: number
  course_id: number
  title: string
  description: string | null
  type: 'link' | 'file' | 'note'
  url: string | null
  file_name: string | null
  has_file: boolean
  file_url: string | null
  topic: string | null
  uploader: User | null
  created_at: string | null
}

export interface Grade {
  id: number
  course_id: number
  user_id: number
  user: User | null
  item_type: string
  item_id: number | null
  item_title: string
  score: number
  max_score: number
  weight: number
  percentage: number
  feedback: string | null
  graded_at: string | null
}

export interface Gradebook {
  course: { id: number; title: string; accent: Accent; code: string }
  items: { item_id: number; label: string; title: string }[]
  rows: {
    user: { id: number; name: string; email: string; initials: string }
    cells: { item_id: number; score: number | null; max_score: number | null }[]
    total: number
    possible: number
    percentage: number | null
  }[]
  class_average: number | null
}

export interface Conversation {
  partner: {
    id: number
    name: string
    initials: string
    role: Role
    last_seen_at: string | null
  }
  last_message: string
  last_at: string | null
  unread: number
}

export interface Message {
  id: number
  body: string
  sender_id: number
  recipient_id: number
  sender: User | null
  recipient: User | null
  course: { id: number; title: string; accent: Accent } | null
  read_at: string | null
  created_at: string | null
}

export interface Metric {
  label: string
  value: number | null
  suffix?: string
  icon: string
  tone: Accent
}

export interface UpcomingItem {
  id: number
  course_id: number
  title: string
  type: AssignmentType
  max_points?: number
  due_at: string | null
  is_overdue?: boolean
  course_title: string
  accent: Accent
  submitted?: boolean
  score?: number | null
}

export interface AdminDashboard {
  role: 'admin'
  metrics: Metric[]
  role_split: Record<string, number>
  course_load: { id: number; title: string; code: string; accent: Accent; teacher: string | null; students: number }[]
  recent_activity: { action: string; subject: string | null; user: string | null; created_at: string | null }[]
  recent_users: { id: number; name: string; email: string; role: Role; initials: string; status: string; created_at: string | null }[]
  signups_by_month: { label: string; total: number }[]
}

export interface TeacherDashboard {
  role: 'teacher'
  metrics: Metric[]
  courses: { id: number; title: string; code: string; accent: Accent; icon: CourseIcon; students_count: number; assignments_count: number }[]
  needs_grading: {
    id: number
    title: string
    due_at: string | null
    submitted: number
    ungraded: number
    course: { id: number; title: string; accent: Accent }
  }[]
  upcoming: UpcomingItem[]
}

export interface StudentDashboard {
  role: 'student'
  metrics: Metric[]
  upcoming: UpcomingItem[]
  announcements: {
    id: number
    course_id: number
    title: string
    body: string
    category: string
    pinned: boolean
    author: string | null
    course_title: string
    accent: Accent
    published_at: string | null
  }[]
  grade_summary: { course_id: number; title: string | null; accent: Accent; percentage: number | null; graded_count: number }[]
}

export type Dashboard = AdminDashboard | TeacherDashboard | StudentDashboard

export interface Paginated<T> {
  data: T[]
  links?: Record<string, string | null>
  meta?: {
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number | null
    to: number | null
  }
}

export interface AuthPayload {
  token: string
  user: User
}
