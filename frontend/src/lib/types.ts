export type Role = "super_admin" | "admin" | "cleaner" | "customer";

export type BookingStatus =
  | "pending"
  | "confirmed"
  | "in_progress"
  | "completed"
  | "cancelled";

export type VerificationStatus = "pending" | "verified" | "rejected";

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: Role;
  avatar_url: string | null;
  initials: string;
  is_active: boolean;
  email_verified: boolean;
  last_login_at: string | null;
  created_at: string | null;
  cleaner_id?: number | null;
  customer_id?: number | null;
}

export interface PublicSettings {
  site_title: string;
  tagline: string | null;
  hero_headline: string | null;
  hero_subline: string | null;
  support_phone: string | null;
  support_email: string | null;
  office_address: string | null;
  currency: string;
  business_open_time: string | null;
  business_close_time: string | null;
  service_areas: string[];
  accepting_bookings: boolean;
  maintenance_mode: boolean;
  maintenance_message: string | null;
}

export interface ServiceCategory {
  id: number;
  name: string;
  slug: string;
  sort_order: number;
  services_count?: number;
  services?: Service[];
}

export interface Service {
  id: number;
  category_id: number;
  category?: ServiceCategory;
  name: string;
  slug: string;
  short_description: string;
  description: string | null;
  image_url: string | null;
  price_per_hour: number;
  default_duration_hours: number;
  min_duration_hours: number;
  max_duration_hours: number;
  is_live: boolean;
  sort_order: number;
  bookings_count?: number;
}

export interface Address {
  id: number;
  customer_id: number;
  label: string;
  line1: string;
  line2: string | null;
  area: string;
  city: string;
  access_instructions: string | null;
  is_default: boolean;
}

export interface Booking {
  id: number;
  reference: string;
  status: BookingStatus;
  source: "website" | "admin";
  scheduled_date: string;
  start_time: string;
  end_time: string;
  duration_hours: number;
  price_per_hour: number;
  total_price: number;
  price_overridden: boolean;
  is_paid: boolean;
  paid_at: string | null;
  customer_notes: string | null;
  internal_notes?: string | null;
  cancellation_reason: string | null;
  cancelled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  address_snapshot: Record<string, string | null> | null;
  customer_id: number;
  service_id: number;
  cleaner_id: number | null;
  address_id: number | null;
  customer?: { id: number; name: string; phone: string | null; email: string };
  service?: { id: number; name: string; slug: string };
  cleaner?: { id: number; name: string; phone: string | null } | null;
  address?: Address;
  review?: Review | null;
  status_history?: BookingStatusHistory[];
  created_at: string;
}

export interface BookingStatusHistory {
  id: number;
  from_status: string | null;
  to_status: string;
  note: string | null;
  changed_by?: string | null;
  created_at: string;
}

export interface Cleaner {
  id: number;
  user_id: number;
  name: string;
  email: string;
  phone: string | null;
  avatar_url: string | null;
  initials: string;
  is_active: boolean;
  verification_status: VerificationStatus;
  verification_notes: string | null;
  is_available: boolean;
  joined_at: string | null;
  bio: string | null;
  rating_avg: number;
  rating_count: number;
  completed_jobs_count: number;
  upcoming_jobs_count?: number;
  skills?: Array<{ id: number; name: string }> | Service[];
  working_hours?: WorkingHour[];
  time_off?: TimeOff[];
}

export interface WorkingHour {
  id?: number;
  day_of_week: number;
  start_time: string | null;
  end_time: string | null;
  is_off: boolean;
}

export interface TimeOff {
  id: number;
  start_date: string;
  end_date: string;
  reason: string | null;
}

export interface Customer {
  id: number;
  user_id: number;
  name: string;
  email: string;
  phone: string | null;
  is_active: boolean;
  customer_since: string | null;
  notes: string | null;
  addresses?: Address[];
  addresses_count?: number;
  bookings_count?: number;
  lifetime_spend?: number | null;
}

export interface Review {
  id: number;
  booking_id: number;
  booking_reference?: string;
  customer_id: number;
  customer_name?: string;
  cleaner_id: number;
  cleaner_name?: string;
  service_name?: string;
  rating: number;
  comment: string;
  is_published: boolean;
  admin_reply: string | null;
  moderated_at: string | null;
  created_at: string;
}

export interface PublicReview {
  id: number;
  rating: number;
  comment: string;
  customer_first_name: string;
  cleaner_first_name: string | null;
  service: string | null;
  admin_reply: string | null;
  created_at: string;
}

export interface AppNotification {
  id: string;
  type: string;
  data: {
    event: string;
    title: string;
    message: string;
    booking_id?: number;
    booking_reference?: string;
  };
  read: boolean;
  read_at: string | null;
  created_at: string;
}

export interface AvailabilitySlot {
  start_time: string;
  available: boolean;
}

export interface DashboardStats {
  total_bookings: number;
  today_scheduled: number;
  revenue: number;
  collected: number;
  active_jobs: number;
  customers: number;
  staff_total: number;
  staff_available: number;
  alerts: { unassigned_soon: number; reviews_awaiting: number; overdue: number };
}
