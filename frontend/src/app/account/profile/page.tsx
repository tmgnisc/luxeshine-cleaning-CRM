"use client";

import { ProfileForm } from "@/components/portal/profile-form";

export default function AccountProfilePage() {
  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-semibold tracking-tight">Profile &amp; settings</h1>
      <ProfileForm />
    </div>
  );
}
