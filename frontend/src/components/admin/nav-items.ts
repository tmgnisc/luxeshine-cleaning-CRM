import {
  CalendarDays,
  LayoutDashboard,
  ClipboardList,
  Users,
  UserCog,
  Sparkles,
  Star,
  Settings,
  ShieldCheck,
  History,
  PlugZap,
  type LucideIcon,
} from "lucide-react";

export interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  superAdminOnly?: boolean;
  exact?: boolean;
}

export const ADMIN_NAV: NavItem[] = [
  { label: "Dashboard", href: "/admin", icon: LayoutDashboard, exact: true },
  { label: "Bookings", href: "/admin/bookings", icon: ClipboardList },
  { label: "Schedule", href: "/admin/schedule", icon: CalendarDays },
  { label: "Integrations", href: "/admin/integrations", icon: PlugZap },
  { label: "Staff", href: "/admin/staff", icon: UserCog },
  { label: "Customers", href: "/admin/customers", icon: Users },
  { label: "Services", href: "/admin/services", icon: Sparkles },
  { label: "Reviews", href: "/admin/reviews", icon: Star },
  { label: "Settings", href: "/admin/settings", icon: Settings },
  { label: "Admin Users", href: "/admin/users", icon: ShieldCheck, superAdminOnly: true },
  { label: "Activity Log", href: "/admin/activity", icon: History },
];
